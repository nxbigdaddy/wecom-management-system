# 03 - 核心框架设计

> 企业微信管理系统 · 原生 PHP · 核心框架层 · 设计文档

---

## 3.1 核心框架概述

核心框架层（`core/`）是整个系统的基石，提供：

| 组件 | 文件 | 职责 |
|------|------|------|
| **Config** | `Config.php` | 配置加载与读取（PHP 数组 + DB 覆盖） |
| **TokenManager** | `TokenManager.php` | access_token 中控管理（Redis 主 + MySQL 兜底） |
| **HttpClient** | `HttpClient.php` | 统一 HTTP 请求（自动注入 token、错误重试、日志） |
| **Crypt** | `Crypt.php` | 消息加解密（AES-256-CBC）+ 签名验证 |
| **Database** | `Database.php` | MySQL PDO 封装（单例连接池） |
| **RedisClient** | `RedisClient.php` | Redis 操作封装 |
| **Router** | `Router.php` | 简易路由（管理后台用） |
| **Logger** | `Logger.php` | 日志系统（文件 + DB 双写） |
| **Session** | `Session.php` | 管理后台会话管理 |
| **Validator** | `Validator.php` | 参数校验工具 |
| **Wxwork** | `Wxwork.php` | 主入口 / 门面类 |

---

## 3.2 Config — 配置管理

### 设计思路

配置来源优先级：**PHP 文件 → 环境变量 → 数据库**

- `config.php` 定义默认值
- 数据库 `wxwork_config` 表可在运行时覆盖（通过管理后台修改）
- 环境变量用于敏感信息（生产环境推荐）

### 接口设计

```php
namespace Wxwork\Core;

class Config
{
    private array $items = [];
    
    /**
     * 构造函数：加载 config.php
     */
    public function __construct(array $config = []);
    
    /**
     * 从数据库加载配置（覆盖文件配置）
     */
    public function loadFromDb(): self;
    
    /**
     * 获取配置值，支持点号分隔的多级 key
     * 示例: $config->get('token.ahead_refresh', 300)
     */
    public function get(string $key, mixed $default = null): mixed;
    
    /**
     * 设置配置值（运行时）
     */
    public function set(string $key, mixed $value): self;
    
    /**
     * 获取所有配置
     */
    public function all(): array;
    
    // 快捷方法
    public function getCorpId(): string;
    public function getCorpSecret(): string;
    public function getAgentId(): string;
    public function getApiDomain(): string;      // https://qyapi.weixin.qq.com
    public function getToken(): string;           // 回调验证 Token
    public function getEncodingAesKey(): string;  // 回调加解密 Key
    public function getHttpTimeout(): int;
}
```

---

## 3.3 TokenManager — access_token 中控

### 核心问题

企业微信 access_token 特性：
- 有效期 **7200 秒**（2 小时）
- 同一 secret 的 token **全局唯一**，新 token 生成后旧 token 立即失效
- 获取频率限制：**2000 次/天/secret**

这就导致经典的**并发刷新问题**：多个进程同时发现 token 过期，同时去请求新 token，互相踢掉。

### 解决方案：Redis 分布式锁 + MySQL 兜底

```
获取 token 流程：
┌─────────────────────────────────────┐
│  TokenManager::getToken()           │
│                                     │
│  1. 从 Redis 读取 token             │
│     ├── 命中且未过期 → 直接返回 ✅   │
│     └── 未命中或过期 ↓              │
│                                     │
│  2. 尝试获取 Redis 刷新锁           │
│     ├── 获取成功 → 进入刷新流程     │
│     └── 获取失败 ↓                  │
│        ├── sleep 100ms 后重试 Redis │
│        └── 3 次后 → 走 DB 兜底      │
│                                     │
│  3. 刷新流程（获取新 token）        │
│     ├── 调企业微信 API 获取 token   │
│     ├── 写入 Redis（TTL=expires-300）│
│     ├── 写入 MySQL 备份              │
│     ├── 释放 Redis 锁               │
│     └── 返回新 token ✅             │
│                                     │
│  4. DB 兜底（Redis 完全不可用时）    │
│     ├── 读 MySQL 最新有效 token     │
│     ├── 未过期 → 返回 ✅            │
│     ├── 已过期 → 走刷新流程         │
│     └── 写入 Redis（如已恢复）      │
└─────────────────────────────────────┘
```

### 接口设计

```php
namespace Wxwork\Core;

class TokenManager
{
    private Config $config;
    private ?\Redis $redis;
    private ?\PDO $db;
    
    // Redis key 前缀
    const REDIS_KEY_TOKEN  = 'YOUR_SECRET';
    const REDIS_KEY_LOCK   = 'wxwork:token_lock';
    const LOCK_TIMEOUT     = 10;  // 锁超时秒数
    const AHEAD_REFRESH    = 300; // 提前刷新秒数
    
    /**
     * 获取有效的 access_token
     * @throws WxworkException
     */
    public function getToken(): string;
    
    /**
     * 强制刷新 token（忽略缓存）
     */
    public function refreshToken(): string;
    
    /**
     * 清除所有缓存的 token
     */
    public function clearCache(): void;
    
    /**
     * 从企业微信 API 获取新 token
     */
    private function fetchTokenFromApi(): array;  // [token, expires_in]
    
    /**
     * 获取 Redis 刷新锁
     */
    private function acquireLock(): bool;
    
    /**
     * 释放 Redis 刷新锁
     */
    private function releaseLock(): void;
}
```

---

## 3.4 HttpClient — HTTP 请求客户端

### 设计思路

- 基于 `curl_multi` 的**同步阻塞**请求（但保留并发扩展可能）
- 自动注入 access_token（URL 参数 `?access_token=XXX`）
- Token 过期自动重试（errcode=42001 时刷新 token 再重试 **1 次**）
- 统一异常处理
- 自动记录 API 日志到数据库
- 支持文件上传（multipart/form-data）

### 接口设计

```php
namespace Wxwork\Core;

class HttpClient
{
    private Config $config;
    private TokenManager $tokenManager;
    private int $timeout = 10;
    private int $retryCount = 1;
    
    /**
     * GET 请求
     * 
     * @param string $path     API 路径，如 '/cgi-bin/user/get'
     * @param array  $query    查询参数，如 ['userid'=>'zhangsan']
     * @param bool   $needToken 是否附加 access_token（默认 true）
     */
    public function get(string $path, array $query = [], bool $needToken = true): array;
    
    /**
     * POST 请求（JSON body）
     */
    public function post(string $path, array $data = [], bool $needToken = true): array;
    
    /**
     * POST 请求（raw body，用于回调回复等场景）
     */
    public function postRaw(string $path, string $body, bool $needToken = true): array;
    
    /**
     * 文件上传（multipart/form-data）
     * 
     * @param string $path     如 '/cgi-bin/media/upload'
     * @param string $filePath 本地文件路径
     * @param string $type     如 'image', 'voice', 'video', 'file'
     * @param array  $extraFields 额外字段
     */
    public function upload(string $path, string $filePath, string $type, array $extraFields = []): array;
    
    /**
     * 设置超时时间
     */
    public function setTimeout(int $seconds): self;
    
    /**
     * 设置重试次数
     */
    public function setRetry(int $times): self;
    
    /**
     * 核心请求方法
     */
    private function request(string $method, string $url, ?string $body, array $headers, ?string $filePath): array;
    
    /**
     * 记录 API 日志到 wxwork_api_log 表
     */
    private function logApiCall(string $method, string $url, ?string $requestBody, array $response, int $elapsedMs, int $retryCount): void;
}
```

### 错误处理策略

```php
// HttpClient 返回统一格式：
// 成功：['errcode' => 0, 'errmsg' => 'ok', ...data]
// 失败：['errcode' => 40001, 'errmsg' => 'invalid credential...', ...]

// 特殊情况处理：
// - errcode == 42001 (token过期)  → 自动刷新token + 重试1次
// - errcode == 45009 (接口调用超限) → 抛出异常提示降频
// - errcode == 0 但 HTTP 状态码非200 → 记录日志
```

---

## 3.5 Crypt — 加解密

### 企业微信消息加解密机制

采用 **AES-256-CBC** 模式：

```
加密流程（企业微信发送给我们的消息）：
明文 XML → AES-256-CBC 加密 → Base64 编码 → 生成签名

解密流程（我们收到消息后）：
验证签名 → Base64 解码 → AES-256-CBC 解密 → 去掉 PKCS7 padding → 去掉 16 字节随机数 + 4字节长度 → 得到明文 XML
```

### 接口设计

```php
namespace Wxwork\Core;

class Crypt
{
    private string $token;
    private string $encodingAesKey;
    private string $corpid;  // 或 suiteId（服务商场景）
    
    const BLOCK_SIZE = 32;    // AES block size
    
    /**
     * 验证 URL 有效性（首次配置回调 URL 时）
     * 
     * @param string $msgSignature 企业微信传来的签名
     * @param string $timestamp    时间戳
     * @param string $nonce        随机数
     * @param string $echostr      加密的随机字符串
     * @return string 解密后的 echostr（明文）
     */
    public function verifyURL(string $msgSignature, string $timestamp, string $nonce, string $echostr): string;
    
    /**
     * 解密消息
     * 
     * @param string $msgSignature 签名
     * @param string $timestamp    时间戳
     * @param string $nonce        随机数
     * @param string $encryptedXml 加密的 XML 字符串
     * @return array 解密后的数据 ['xml' => '<xml>...</xml>', 'receiveid' => 'corpid']
     */
    public function decryptMsg(string $msgSignature, string $timestamp, string $nonce, string $encryptedXml): array;
    
    /**
     * 加密消息（回复消息给企业微信时）
     * 
     * @param string $replyXml    明文的回复 XML
     * @param string $timestamp   时间戳
     * @param string $nonce       随机数
     * @return string 加密后的 XML（可直接返回）
     */
    public function encryptMsg(string $replyXml, string $timestamp, string $nonce): string;
    
    /**
     * 生成签名
     * SHA1(sort(token, timestamp, nonce, msgEncrypt))
     */
    private function getSignature(string $timestamp, string $nonce, string $encrypt): string;
    
    /**
     * PKCS7 编码（补位）
     */
    private static function pkcs7Pad(string $text): string;
    
    /**
     * PKCS7 解码（去补位）
     */
    private static function pkcs7Unpad(string $text): string;
}
```

---

## 3.6 Database — MySQL PDO 封装

### 设计思路

- 单例模式（一个请求内复用同一连接）
- PDO 长连接（`PDO::ATTR_PERSISTENT => true`）
- 异常模式（`PDO::ERRMODE_EXCEPTION`）
- 提供简易查询方法（非 ORM，保持轻量）

### 接口设计

```php
namespace Wxwork\Core;

class Database
{
    private static ?Database $instance = null;
    private \PDO $pdo;
    
    /**
     * 获取单例
     */
    public static function getInstance(): self;
    
    /**
     * 获取原生 PDO 对象
     */
    public function getPdo(): \PDO;
    
    /**
     * 执行查询，返回所有行
     */
    public function select(string $sql, array $params = []): array;
    
    /**
     * 执行查询，返回单行
     */
    public function selectOne(string $sql, array $params = []): ?array;
    
    /**
     * 执行查询，返回单列值
     */
    public function selectValue(string $sql, array $params = [], $default = null): mixed;
    
    /**
     * 执行 INSERT/UPDATE/DELETE，返回影响行数
     */
    public function execute(string $sql, array $params = []): int;
    
    /**
     * INSERT 并返回自增 ID
     */
    public function insert(string $sql, array $params = []): int;
    
    /**
     * 事务操作
     */
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;
}
```

---

## 3.7 RedisClient — Redis 封装

### 接口设计

```php
namespace Wxwork\Core;

class RedisClient
{
    private static ?RedisClient $instance = null;
    private ?\Redis $redis = null;
    private bool $available = false;
    
    public static function getInstance(): self;
    
    /**
     * 检查 Redis 是否可用
     */
    public function isAvailable(): bool;
    
    /**
     * 获取字符串
     */
    public function get(string $key): ?string;
    
    /**
     * 设置字符串（带 TTL）
     */
    public function set(string $key, string $value, int $ttl = 0): bool;
    
    /**
     * 设置带过期时间的 key（SET EX NX 原子操作，用作分布式锁）
     */
    public function setnx(string $key, string $value, int $ttl): bool;
    
    /**
     * 删除 key
     */
    public function del(string $key): bool;
    
    /**
     * 检查 key 是否存在
     */
    public function exists(string $key): bool;
    
    /**
     * 获取 TTL
     */
    public function ttl(string $key): int;
}
```

---

## 3.8 Router — 简易路由

管理后台使用，不引入第三方路由库：

```php
namespace Wxwork\Core;

class Router
{
    /**
     * 注册路由
     * 
     * @param string $method   GET|POST
     * @param string $pattern  路径模式，如 '/contacts/departments/{id}'
     * @param callable|string $handler 处理器
     */
    public function add(string $method, string $pattern, callable|string $handler): self;
    
    /**
     * 便捷方法
     */
    public function get(string $pattern, callable|string $handler): self;
    public function post(string $pattern, callable|string $handler): self;
    
    /**
     * 分发请求
     * 
     * @param string $method  GET|POST
     * @param string $uri     请求 URI
     */
    public function dispatch(string $method, string $uri): mixed;
    
    /**
     * 获取路由参数（在 handler 内调用）
     */
    public function getParams(): array;
}
```

---

## 3.9 Logger — 日志系统

```php
namespace Wxwork\Core;

class Logger
{
    const LEVEL_DEBUG = 0;
    const LEVEL_INFO  = 1;
    const LEVEL_WARN  = 2;
    const LEVEL_ERROR = 3;
    
    /**
     * 记录日志（文件写入）
     */
    public function log(int $level, string $message, array $context = []): void;
    
    // 便捷方法
    public function debug(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function warn(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    
    /**
     * 设置日志目录
     */
    public function setLogDir(string $dir): self;
}
```

日志文件格式（放在项目外的 `logs/` 目录）：

```
logs/
├── wxwork-2025-01-01.log
├── wxwork-2025-01-02.log
├── api-2025-01-01.log         # API 调用详情日志
├── callback-2025-01-01.log    # 回调详情日志
└── error-2025-01-01.log       # 错误日志
```

---

## 3.10 Session — 会话管理

管理后台登录会话，使用 PHP 原生 Session + Redis 存储：

```php
namespace Wxwork\Core;

class Session
{
    /**
     * 设置用户登录状态
     */
    public function login(int $userId, array $userData): void;
    
    /**
     * 检查是否登录
     */
    public function isLoggedIn(): bool;
    
    /**
     * 获取当前登录用户
     */
    public function getUser(): ?array;
    
    /**
     * 退出登录
     */
    public function logout(): void;
    
    /**
     * CSRF Token 生成与验证
     */
    public function csrfToken(): string;
    public function validateCsrf(string $token): bool;
}
```

---

## 3.11 Validator — 参数校验

简单的参数校验工具，用于 API 参数和管理后台表单验证：

```php
namespace Wxwork\Core;

class Validator
{
    /**
     * 校验数据
     * 
     * @param array $data  待校验数据
     * @param array $rules 规则定义
     * @return array ['valid' => bool, 'errors' => ['field' => 'message']]
     * 
     * 规则示例：
     * [
     *   'userid' => 'required|string|max:64',
     *   'mobile' => 'string|pattern:/^1\d{10}$/',
     *   'email'  => 'email',
     *   'age'    => 'int|min:1|max:150',
     * ]
     */
    public static function validate(array $data, array $rules): array;
    
    /**
     * 快速校验，不通过直接抛异常
     */
    public static function validateOrFail(array $data, array $rules): array;
}
```

---

## 3.12 Wxwork — 主入口 / 门面

这是用户代码唯一需要接触的入口类：

```php
namespace Wxwork\Core;

/**
 * @property \Wxwork\Api\AddressBook       $addressbook
 * @property \Wxwork\Api\Message           $message
 * @property \Wxwork\Api\Auth              $auth
 * @property \Wxwork\Api\ExternalContact   $externalContact
 * @property \Wxwork\Api\Media             $media
 * @property \Wxwork\Api\AppManage         $appManage
 * @property \Wxwork\Api\Approval          $approval
 * @property \Wxwork\Api\Calendar          $calendar
 * @property \Wxwork\Api\Checkin           $checkin
 * @property \Wxwork\Api\Meeting           $meeting
 * @property \Wxwork\Api\Kf                $kf
 * @property \Wxwork\Api\MsgAudit          $msgAudit
 * @property \Wxwork\Callback\Server       $callback
 */
class Wxwork
{
    private Config $config;
    private TokenManager $tokenManager;
    private HttpClient $httpClient;
    private Crypt $crypt;
    
    // API 模块实例缓存
    private array $modules = [];
    
    /**
     * 构造函数
     * 
     * @param array $config 配置数组（可覆盖 config.php）
     * 
     * 示例：
     * $wx = new Wxwork([
     *     'corpid'     => 'ww1234567890abcdef',
     *     'corpsecret' => 'your-secret-key',
     *     'agent_id'   => 1000002,
     * ]);
     */
    public function __construct(array $config = []);
    
    /**
     * 魔术方法：懒加载 API 模块
     * $wx->addressbook → new AddressBook($this)
     */
    public function __get(string $name): object;
    
    /**
     * 获取 HttpClient（供手动调用）
     */
    public function getHttpClient(): HttpClient;
    
    /**
     * 获取 TokenManager
     */
    public function getTokenManager(): TokenManager;
    
    /**
     * 获取 Crypt
     */
    public function getCrypt(): Crypt;
    
    /**
     * 获取 Config
     */
    public function getConfig(): Config;
    
    // ========== 快捷方法 ==========
    
    /**
     * 快捷 GET 请求
     */
    public function get(string $path, array $query = []): array;
    
    /**
     * 快捷 POST 请求
     */
    public function post(string $path, array $data = []): array;
    
    /**
     * 快捷上传
     */
    public function upload(string $path, string $filePath, string $type): array;
}
```

### 使用示例

```php
<?php
// 引入自动加载
require __DIR__ . '/vendor/autoload.php';

use Wxwork\Core\Wxwork;

// 初始化
$wx = new Wxwork([
    'corpid'     => 'ww1234567890abcdef',
    'corpsecret' => 'your-secret-key-here',
    'agent_id'   => 1000002,
]);

// 获取部门列表
$depts = $wx->addressbook->departmentList();

// 获取成员详情
$user = $wx->addressbook->userGet('zhangsan');

// 发送文本消息
$wx->message->sendText([
    'touser'  => 'zhangsan',
    'content' => '你好，这是一条测试消息',
]);

// 发送图文消息
$wx->message->sendNews([
    'touser' => 'zhangsan|lisi',
    'articles' => [
        [
            'title'       => '标题',
            'description' => '描述',
            'url'         => 'https://example.com',
            'picurl'      => 'https://example.com/pic.jpg',
        ],
    ],
]);
```

---

## 3.13 异常体系

```php
namespace Wxwork\Core;

/**
 * 基础异常
 */
class WxworkException extends \Exception {}

/**
 * Token 相关异常
 */
class TokenException extends WxworkException {}

/**
 * API 调用异常
 */
class ApiException extends WxworkException
{
    private int $errcode;
    private array $responseData;
    
    public function getErrcode(): int;
    public function getResponseData(): array;
}

/**
 * 加解密异常
 */
class CryptException extends WxworkException {}

/**
 * 参数校验异常
 */
class ValidationException extends WxworkException
{
    private array $errors;
    
    public function getErrors(): array;
}
```

---

## 3.14 PSR-4 Autoload 配置

```json
{
  "name": "wxwork/manager",
  "description": "企业微信管理系统 - 原生 PHP",
  "type": "project",
  "autoload": {
    "psr-4": {
      "Wxwork\\Core\\": "core/",
      "Wxwork\\Api\\": "api/",
      "Wxwork\\Callback\\": "callback/",
      "Wxwork\\Callback\\Handlers\\": "callback/handlers/",
      "Wxwork\\Models\\": "models/",
      "Wxwork\\Sync\\": "sync/"
    }
  },
  "require": {
    "php": ">=8.0",
    "ext-curl": "*",
    "ext-openssl": "*",
    "ext-pdo": "*",
    "ext-pdo_mysql": "*",
    "ext-redis": "*",
    "ext-mbstring": "*",
    "ext-xml": "*",
    "ext-json": "*"
  }
}
```

---

> 下一章节：[04 - API 模块设计](./04-api-modules.md)

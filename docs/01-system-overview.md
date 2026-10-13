# 01 - 系统总览

> 企业微信管理系统（WxWork Manager）· 原生 PHP · MySQL + Redis · 设计文档

---

## 1.1 项目定位

构建一套**完整的**企业微信管理系统，覆盖企业微信所有核心 API，提供：

| 能力 | 说明 |
|------|------|
| **API SDK** | 封装所有企业微信 API，可独立被其他项目引用 |
| **管理后台** | Web 界面，可视化管理通讯录、推送消息、查看回调日志等 |
| **回调系统** | 接收企业微信事件推送，支持自定义事件处理器 |
| **数据同步** | 将企业微信通讯录、客户等数据定时同步到本地 MySQL |

---

## 1.2 技术选型

| 层级 | 技术 | 说明 |
|------|------|------|
| 语言 | PHP 8.0+ | 原生 PHP，不依赖任何框架 |
| 数据库 | MySQL 8.0 | InnoDB，utf8mb4 |
| 缓存 | Redis | access_token 中控、会话、队列 |
| HTTP | cURL | 原生 curl 扩展 |
| 加解密 | openssl | AES-256-CBC，SHA1 |
| 前端 | 原生 HTML + JS | 可选轻量 CSS（如 Pico.css 或纯手写） |
| 依赖管理 | Composer（仅 autoload）| 不引入第三方框架 |

### 为什么不使用框架？

- **零学习成本**：代码结构完全由我们定义，看到即理解
- **极致可控**：每个环节都自主掌控，方便调试和定制
- **部署简单**：无需 Composer install（除 autoload），上传即用
- **企业微信 SDK 在框架中往往水土不服**：token 管理、回调处理等更适合自建

---

## 1.3 项目目录结构

```
wxwork2026/
│
├── public/                         # Web 根目录（Nginx/Apache 指向这里）
│   ├── index.php                   # 管理后台入口
│   ├── callback.php                # 企业微信回调入口 ⭐
│   ├── assets/                     # 静态资源
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   └── .htaccess                   # Apache 重写规则
│
├── core/                           # ═══ 核心框架层 ═══
│   ├── Wxwork.php                  # 主入口 / 门面类
│   ├── Config.php                  # 配置管理
│   ├── TokenManager.php            # access_token 中控（Redis + DB 双写）
│   ├── HttpClient.php              # HTTP 请求客户端（curl）
│   ├── Crypt.php                   # 加解密（消息加解密 + 签名验证）
│   ├── Logger.php                  # 日志系统
│   ├── Router.php                  # 简易路由（管理后台用）
│   ├── Database.php                # MySQL PDO 封装
│   ├── RedisClient.php             # Redis 封装
│   ├── Session.php                 # 管理后台会话
│   └── Validator.php               # 参数校验
│
├── api/                            # ═══ API 模块层 ═══
│   ├── BaseApi.php                 # API 基类（注入 httpClient / tokenManager）
│   ├── AddressBook.php             # 通讯录管理（部门/成员/标签）
│   ├── Message.php                 # 消息推送（应用消息/群机器人）
│   ├── Auth.php                    # 身份认证（OAuth2/扫码/JS-SDK）
│   ├── ExternalContact.php         # 客户联系/SCRM
│   ├── Media.php                   # 素材管理
│   ├── AppManage.php               # 应用管理/菜单
│   ├── Approval.php                # OA 审批
│   ├── Calendar.php                # 日程
│   ├── Checkin.php                 # 打卡
│   ├── Meeting.php                 # 会议
│   ├── Kf.php                      # 微信客服
│   └── MsgAudit.php                # 会话内容存档
│
├── callback/                       # ═══ 回调系统 ═══
│   ├── Server.php                  # 回调服务器（接收/验证/分发）
│   ├── EventDispatcher.php         # 事件分发器
│   ├── HandlerInterface.php        # 事件处理器接口
│   └── handlers/                   # 具体事件处理器
│       ├── ChangeContact.php       # 通讯录变更事件
│       ├── ChangeExternal.php      # 客户变更事件
│       ├── ApprovalEvent.php       # 审批事件
│       └── DefaultHandler.php      # 兜底处理器
│
├── admin/                          # ═══ 管理后台 ═══
│   ├── layout/                     # 公共布局
│   │   ├── header.php
│   │   ├── footer.php
│   │   └── sidebar.php
│   ├── modules/                    # 功能模块
│   │   ├── dashboard/              # 仪表盘
│   │   ├── contacts/               # 通讯录管理
│   │   ├── messages/               # 消息推送
│   │   ├── external/               # 客户联系
│   │   ├── approval/               # 审批管理
│   │   ├── callback/               # 回调日志
│   │   ├── media/                  # 素材管理
│   │   └── system/                 # 系统设置
│   ├── login.php                   # 登录页
│   └── logout.php                  # 退出
│
├── models/                         # ═══ 数据模型（对应 MySQL 表）═══
│   ├── BaseModel.php               # Model 基类
│   ├── DepartmentModel.php
│   ├── MemberModel.php
│   ├── TagModel.php
│   ├── ExternalContactModel.php
│   ├── MessageLogModel.php
│   ├── CallbackLogModel.php
│   └── AdminUserModel.php
│
├── sync/                           # ═══ 定时同步任务 ═══
│   ├── SyncContacts.php            # 同步通讯录
│   ├── SyncExternalContacts.php    # 同步客户
│   └── SyncTags.php                # 同步标签
│
├── config.php                      # 主配置文件
├── schema.sql                      # 数据库建表 SQL
├── composer.json                   # 仅用于 PSR-4 autoload
├── cron.example                    # 定时任务示例
└── README.md                       # 项目文档入口
```

---

## 1.4 系统架构图

```
┌─────────────────────────────────────────────────────────────────┐
│                        管理后台 (Web UI)                         │
│               admin/  ── 原生 PHP + HTML 页面                    │
│          会话管理(Session) ── 路由(Router) ── 模板渲染            │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                     API 模块层 (api/)                             │
│                                                                  │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐           │
│  │ 通讯录   │ │ 消息推送 │ │ 客户联系 │ │ OA 审批  │  ...      │
│  │AddressBook│ │Message   │ │ExtContact│ │Approval  │           │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘           │
│       └──────────────┴────────────┴────────────┘                 │
│                          │                                       │
│                    BaseApi (基类)                                │
│             注入: HttpClient + TokenManager                      │
└───────────────────────────┬─────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                     核心框架层 (core/)                            │
│                                                                  │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────┐          │
│  │HttpClient   │  │TokenManager  │  │ Crypt         │          │
│  │ (curl封装)  │  │(token中控)   │  │ (加解密)      │          │
│  └──────┬──────┘  └──────┬───────┘  └───────┬───────┘          │
│         │                │                   │                   │
│  ┌──────┴──────┐  ┌──────┴───────┐  ┌───────┴───────┐          │
│  │ Config      │  │ RedisClient  │  │ Database      │          │
│  │ (配置)      │  │ (Redis)      │  │ (MySQL PDO)   │          │
│  └─────────────┘  └──────────────┘  └───────────────┘          │
│                                                                  │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────┐          │
│  │ Logger      │  │ Router       │  │ Validator     │          │
│  │ (日志)      │  │ (路由)       │  │ (校验)        │          │
│  └─────────────┘  └──────────────┘  └───────────────┘          │
└───────────────────────────┬─────────────────────────────────────┘
                            │
          ┌─────────────────┼─────────────────┐
          ▼                 ▼                   ▼
    ┌──────────┐     ┌──────────┐       ┌──────────────┐
    │  MySQL   │     │  Redis   │       │ 企业微信云端  │
    │  数据库   │     │  缓存    │       │  API 服务器   │
    └──────────┘     └──────────┘       └──────────────┘
```

---

## 1.5 请求生命周期

### 5.1 管理后台页面请求

```
用户浏览器
  │
  ├─(1)──→ public/index.php?/contacts/departments
  │         │
  │         ├─(2)──→ Router 解析 URL → 匹配 admin/modules/contacts/departments.php
  │         │         │
  │         │         ├─(3)──→ 加载 Config → 实例化 Wxwork
  │         │         │         │
  │         │         │         ├─(4)──→ TokenManager::getToken()
  │         │         │         │          │
  │         │         │         │          ├─ 查 Redis
  │         │         │         │          ├─ 未命中 → 调 API 获取
  │         │         │         │          └─ 写入 Redis + MySQL
  │         │         │         │
  │         │         │         └─(5)──→ AddressBook::departmentList()
  │         │         │                    │
  │         │         │                    └─→ HttpClient::get('/department/list')
  │         │         │                          │
  │         │         │                          └─→ curl_exec → 企业微信 API
  │         │         │
  │         │         └─(6)──→ 渲染 HTML → 返回浏览器
  │
  └─(7)── 浏览器渲染页面
```

### 5.2 回调事件请求

```
企业微信服务器
  │
  └─(1)──→ POST public/callback.php?msg_signature=...&timestamp=...&nonce=...
            │
            ├─(2)──→ Crypt::decryptMsg(原始XML)  ← 解密消息体
            │         │
            │         └─→ 验证签名 → AES解密 → 得到明文XML
            │
            ├─(3)──→ 解析 XML → Event 对象
            │
            ├─(4)──→ EventDispatcher::dispatch(event)
            │         │
            │         ├─→ ChangeContact::handle()   ← 通讯录变更
            │         ├─→ ChangeExternal::handle()  ← 客户变更
            │         └─→ ApprovalEvent::handle()   ← 审批变更
            │
            ├─(5)──→ 记录日志到 MySQL (callback_log 表)
            │
            └─(6)──→ 返回 "success" (企业微信要求的确认字符串)
```

---

## 1.6 核心设计原则

### 原则 1：API 模块零依赖
每个 `api/` 下的模块类**只依赖 BaseApi**，可独立 require 使用：

```php
// 在其他项目中单独使用通讯录模块
require 'wxwork2026/core/Wxwork.php';
$wx = new Wxwork\Wxwork(['corpid'=>'xxx', 'secret'=>'xxx']);
$depts = $wx->addressbook->departmentList();
```

### 原则 2：Token 中控
所有 API 请求**不直接传 access_token**，由 TokenManager 自动管理：

```php
// 开发者无需关心 token
$users = $wx->addressbook->userList(1);  // TokenManager 自动注入
```

### 原则 3：错误统一处理
HttpClient 层统一处理：
- Token 过期 → 自动刷新 + 重试（1 次）
- 网络超时 → 重试（可配置次数）
- 企业微信错误码 → 统一抛出 WxworkException

### 原则 4：数据库最小化原则
- 能实时查 API 的不存库（如单个成员详情）
- 需要离线使用的才同步（通讯录列表、客户列表）
- 日志类数据入库（消息记录、回调记录）

### 原则 5：前后端分离准备
管理后台虽然是 PHP 直出，但每个功能模块的数据获取和渲染逻辑分离，**未来可轻松改成 API + Vue**：

```php
// admin/modules/contacts/departments.php
$data = $wx->addressbook->departmentList();  // 数据获取
render('contacts/departments', ['list' => $data]);  // 模板渲染
```

---

## 1.7 PHP 版本与扩展要求

| 要求 | 说明 |
|------|------|
| PHP >= 8.0 | 使用命名空间、类型声明、match 表达式等 |
| ext-curl | HTTP 请求 |
| ext-openssl | 加解密 |
| ext-pdo + pdo_mysql | 数据库 |
| ext-redis | Redis（推荐）或 predis/predis（Composer） |
| ext-mbstring | 多字节字符串处理 |
| ext-xml | XML 解析（回调消息） |
| ext-json | JSON 编解码 |

---

## 1.8 命名空间约定

```
Wxwork\                      # 根命名空间
Wxwork\Core\                 # 核心框架
Wxwork\Api\                  # API 模块
Wxwork\Callback\             # 回调系统
Wxwork\Callback\Handlers\    # 事件处理器
Wxwork\Models\               # 数据模型
Wxwork\Sync\                 # 同步任务
```

对应 PSR-4 自动加载：

```json
{
  "autoload": {
    "psr-4": {
      "Wxwork\\": ".",
      "Wxwork\\Core\\": "core/",
      "Wxwork\\Api\\": "api/",
      "Wxwork\\Callback\\": "callback/",
      "Wxwork\\Callback\\Handlers\\": "callback/handlers/",
      "Wxwork\\Models\\": "models/",
      "Wxwork\\Sync\\": "sync/"
    }
  }
}
```

---

> 下一章节：[02 - 数据库设计](./02-database-design.md)

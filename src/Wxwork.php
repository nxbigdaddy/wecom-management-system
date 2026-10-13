<?php
/**
 * 企业微信管理系统 - 主入口 / 门面类
 * 
 * 这是整个系统的入口，用户只需实例化此类即可访问所有功能。
 * 
 * 使用示例：
 *   $wx = new Wxwork\Wxwork();
 *   $depts = $wx->addressbook->departmentList();
 *   $wx->message->sendText(['touser' => 'zhangsan', 'content' => 'Hello']);
 * 
 * 支持的模块（通过 __get 懒加载）：
 *   @property \Wxwork\Api\AddressBook      $addressbook
 *   @property \Wxwork\Api\Message          $message
 *   @property \Wxwork\Api\Auth             $auth
 *   @property \Wxwork\Api\ExternalContact  $externalContact
 *   @property \Wxwork\Api\Media            $media
 *   @property \Wxwork\Api\AppManage        $appManage
 *   @property \Wxwork\Api\Approval         $approval
 *   @property \Wxwork\Api\Calendar         $calendar
 *   @property \Wxwork\Api\Checkin          $checkin
 *   @property \Wxwork\Api\Meeting          $meeting
 *   @property \Wxwork\Api\Kf               $kf
 *   @property \Wxwork\Api\MsgAudit         $msgAudit
 *   @property \Wxwork\Callback\Server      $callback
 */

namespace Wxwork;

use Wxwork\Core\Config;
use Wxwork\Core\Database;
use Wxwork\Core\RedisClient;
use Wxwork\Core\TokenManager;
use Wxwork\Core\HttpClient;
use Wxwork\Core\Crypt;
use Wxwork\Core\Logger;

class Wxwork
{
    /** @var Config 配置实例 */
    private Config $config;

    /** @var Database|null 数据库实例 */
    private ?Database $db = null;

    /** @var RedisClient|null Redis 实例 */
    private ?RedisClient $redis = null;

    /** @var TokenManager|null Token 管理器 */
    private ?TokenManager $tokenManager = null;

    /** @var HttpClient|null HTTP 客户端 */
    private ?HttpClient $httpClient = null;

    /** @var Crypt|null 加解密实例 */
    private ?Crypt $crypt = null;

    /** @var Logger|null 日志实例 */
    private ?Logger $logger = null;

    /** @var array API 模块实例缓存 */
    private array $modules = [];

    /** @var array 模块名 → 类名映射 */
    private static array $moduleMap = [
        'addressbook'      => 'Wxwork\\Api\\AddressBook',
        'message'          => 'Wxwork\\Api\\Message',
        'auth'             => 'Wxwork\\Api\\Auth',
        'externalContact'  => 'Wxwork\\Api\\ExternalContact',
        'externalcontact'  => 'Wxwork\\Api\\ExternalContact',
        'media'            => 'Wxwork\\Api\\Media',
        'appManage'        => 'Wxwork\\Api\\AppManage',
        'appmanage'        => 'Wxwork\\Api\\AppManage',
        'approval'         => 'Wxwork\\Api\\Approval',
        'calendar'         => 'Wxwork\\Api\\Calendar',
        'checkin'          => 'Wxwork\\Api\\Checkin',
        'meeting'          => 'Wxwork\\Api\\Meeting',
        'kf'               => 'Wxwork\\Api\\Kf',
        'msgAudit'         => 'Wxwork\\Api\\MsgAudit',
        'msgaudit'         => 'Wxwork\\Api\\MsgAudit',
        'smartsheet'       => 'Wxwork\\Api\\Smartsheet',
        'wedoc'            => 'Wxwork\\Api\\Wedoc',
        'wedrive'          => 'Wxwork\\Api\\Wedrive',
        'callback'         => 'Wxwork\\Callback\\Server',
    ];

    /**
     * 构造函数
     * 
     * @param array|null $config 配置数组（可选，不传则自动加载 config.php）
     * 
     * 示例：
     *   // 方式1：自动加载配置文件
     *   $wx = new Wxwork();
     * 
     *   // 方式2：传入完整配置
     *   $wx = new Wxwork([
     *       'corpid'     => 'ww1234567890abcdef',
     *       'corpsecret' => 'your-secret',
     *       'agent_id'   => 1000002,
     *   ]);
     * 
     *   // 方式3：传入配置 + 自动加载默认值
     *   $wx = new Wxwork(['corpid' => 'wwxxx', 'corpsecret' => 'xxx']);
     */
    public function __construct(?array $config = null)
    {
        // 1. 加载基础配置
        $baseConfig = [];
        $configFile = __DIR__ . '/../config.php';
        if (file_exists($configFile)) {
            $baseConfig = require $configFile;
        }

        // 2. 合并用户配置
        if ($config !== null) {
            $baseConfig = array_merge($baseConfig, $config);
        }

        // 3. 创建 Config 实例
        $this->config = new Config($baseConfig);
    }

    /**
     * 初始化全部组件（数据库、Redis、Logger 等）
     * 通常不需要手动调用，各组件按需懒加载。
     * 如果需要在请求开始时预热，可手动调用。
     */
    public function boot(): self
    {
        $this->getDatabase();
        $this->getRedis();
        $this->getLogger();
        $this->config->setDatabase($this->getDatabase())->loadFromDb();
        return $this;
    }

    // ===================== 魔术方法：懒加载模块 =====================

    /**
     * 魔术方法，懒加载 API 模块
     * $wx->addressbook  →  new Api\AddressBook($this)
     * $wx->message      →  new Api\Message($this)
     */
    public function __get(string $name): object
    {
        $lowerName = strtolower($name);

        // 检查缓存
        if (isset($this->modules[$lowerName])) {
            return $this->modules[$lowerName];
        }

        // 查找映射
        $className = self::$moduleMap[$lowerName] ?? null;
        if ($className === null) {
            throw new \RuntimeException("未知模块: {$name}");
        }

        if (!class_exists($className)) {
            throw new \RuntimeException("模块类不存在: {$className}. 请确保文件已创建。");
        }

        $this->modules[$lowerName] = new $className($this);
        return $this->modules[$lowerName];
    }

    // ===================== Getter 方法 =====================

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getDatabase(): Database
    {
        if ($this->db === null) {
            $this->db = Database::initWithConfig($this->config);
        }
        return $this->db;
    }

    public function getRedis(): RedisClient
    {
        if ($this->redis === null) {
            $this->redis = RedisClient::initWithConfig($this->config);
        }
        return $this->redis;
    }

    public function getTokenManager(): TokenManager
    {
        if ($this->tokenManager === null) {
            $this->tokenManager = new TokenManager(
                $this->config,
                $this->getRedis(),
                $this->getDatabase()
            );
        }
        return $this->tokenManager;
    }

    public function getHttpClient(): HttpClient
    {
        if ($this->httpClient === null) {
            $this->httpClient = new HttpClient($this->config, $this->getTokenManager());
            $this->httpClient->setLogger($this->getLogger());
            $this->httpClient->setDatabase($this->getDatabase());
        }
        return $this->httpClient;
    }

    public function getCrypt(): Crypt
    {
        if ($this->crypt === null) {
            $this->crypt = new Crypt(
                $this->config->getToken(),
                $this->config->getEncodingAesKey(),
                $this->config->getCorpId()
            );
        }
        return $this->crypt;
    }

    public function getLogger(): Logger
    {
        if ($this->logger === null) {
            $this->logger = new Logger(
                $this->config->getLogDir(),
                $this->config->getLogLevel(),
                (int)$this->config->get('log_max_days', 30)
            );
        }
        return $this->logger;
    }

    // ===================== 快捷 HTTP 方法 =====================

    /**
     * 快捷 GET 请求
     */
    public function get(string $path, array $query = []): array
    {
        return $this->getHttpClient()->get($path, $query);
    }

    /**
     * 快捷 POST 请求
     */
    public function post(string $path, array $data = []): array
    {
        return $this->getHttpClient()->post($path, $data);
    }

    /**
     * 快捷文件上传
     */
    public function upload(string $path, string $filePath, string $type): array
    {
        return $this->getHttpClient()->upload($path, $filePath, $type);
    }

    // ===================== 静态快捷入口 =====================

    /**
     * 快速创建实例（推荐）
     * 
     * @param array|null $config
     * @return self
     */
    public static function create(?array $config = null): self
    {
        $instance = new self($config);
        $instance->boot();
        return $instance;
    }
}

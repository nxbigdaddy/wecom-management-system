<?php
/**
 * 配置管理类
 * 
 * 支持从 PHP 文件加载配置，同时支持从数据库 wxwork_config 表覆盖配置。
 * 支持点号分隔的多级 key 访问（如 'db.host'）。
 */

namespace Wxwork\Core;

class Config
{
    /** @var array 配置数据 */
    private array $items = [];

    /** @var Database|null 数据库实例（用于从 DB 加载配置） */
    private ?Database $db = null;

    /**
     * 构造函数
     * @param array $config 初始配置数组（通常来自 config.php）
     */
    public function __construct(array $config = [])
    {
        $this->items = $config;
    }

    /**
     * 注入数据库实例，用于从数据库加载配置覆盖
     */
    public function setDatabase(Database $db): self
    {
        $this->db = $db;
        return $this;
    }

    /**
     * 从数据库 wxwork_config 表加载配置并覆盖
     * 仅覆盖存在的键，不会新增
     */
    public function loadFromDb(): self
    {
        if ($this->db === null) {
            return $this;
        }
        try {
            $rows = $this->db->select('SELECT cfg_key, cfg_value FROM wxwork_config');
            foreach ($rows as $row) {
                $this->items[$row['cfg_key']] = $row['cfg_value'];
            }
        } catch (\Throwable $e) {
            // 表可能还未创建，静默处理
        }
        return $this;
    }

    /**
     * 获取配置值
     * @param string $key     配置键，支持点号分隔（如 'db.host'）
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->items;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * 设置配置值（运行时覆盖）
     * @param string $key   配置键
     * @param mixed  $value 配置值
     */
    public function set(string $key, mixed $value): self
    {
        $keys = explode('.', $key);
        $ref = &$this->items;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $ref[$segment] = $value;
                break;
            }
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        return $this;
    }

    /**
     * 获取所有配置
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * 检查配置键是否存在
     */
    public function has(string $key): bool
    {
        return $this->get($key, '__WXWORK_NULL__') !== '__WXWORK_NULL__';
    }

    // ===================== 快捷方法 =====================

    public function getCorpId(): string
    {
        return (string)$this->get('corpid', '');
    }

    public function getCorpSecret(): string
    {
        return (string)$this->get('corpsecret', '');
    }

    public function getAgentId(): int
    {
        return (int)$this->get('agent_id', 0);
    }

    public function getApiDomain(): string
    {
        return rtrim((string)$this->get('api_domain', 'https://qyapi.weixin.qq.com'), '/');
    }

    public function getToken(): string
    {
        return (string)$this->get('token', '');
    }

    public function getEncodingAesKey(): string
    {
        return (string)$this->get('encoding_aes_key', '');
    }

    public function getHttpTimeout(): int
    {
        return (int)$this->get('http_timeout', 10);
    }

    public function getHttpRetry(): int
    {
        return (int)$this->get('http_retry', 1);
    }

    public function getTokenAheadRefresh(): int
    {
        return (int)$this->get('token_ahead_refresh', 300);
    }

    public function getTokenLockTimeout(): int
    {
        return (int)$this->get('token_lock_timeout', 10);
    }

    public function getLogDir(): string
    {
        return (string)$this->get('log_dir', __DIR__ . '/../logs');
    }

    public function getLogLevel(): string
    {
        return (string)$this->get('log_level', 'info');
    }

    public function getRedisPrefix(): string
    {
        return (string)$this->get('redis_prefix', 'wxwork:');
    }

    public function getDbPrefix(): string
    {
        return (string)$this->get('db_prefix', 'wxwork_');
    }
}

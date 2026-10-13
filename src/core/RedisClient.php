<?php
/**
 * Redis 客户端封装
 * 
 * 可降级模式：Redis 不可用时不影响系统运行
 * Token 管理等会 fallback 到 MySQL
 */

namespace Wxwork\Core;

class RedisClient
{
    /** @var self|null 单例 */
    private static ?self $instance = null;

    /** @var \Redis|null Redis 连接实例 */
    private ?\Redis $redis = null;

    /** @var bool Redis 是否可用 */
    private bool $available = false;

    /** @var string Key 前缀 */
    private string $prefix;

    /**
     * 私有构造函数
     */
    private function __construct(array $config)
    {
        $this->prefix = $config['redis_prefix'] ?? 'wxwork:';

        if (!extension_loaded('redis') && !class_exists('Redis')) {
            $this->available = false;
            return;
        }

        try {
            $host = $config['redis_host'] ?? '127.0.0.1';
            $port = (int)($config['redis_port'] ?? 6379);
            $pass = $config['redis_password'] ?? '';
            $db   = (int)($config['redis_db'] ?? 0);
            $timeout = 3.0; // 连接超时 3 秒

            $this->redis = new \Redis();

            // 使用 persistent 连接
            $connected = $this->redis->pconnect($host, $port, $timeout);

            if (!$connected) {
                $this->available = false;
                return;
            }

            if (!empty($pass)) {
                $this->redis->auth($pass);
            }

            $this->redis->select($db);
            $this->available = true;
        } catch (\Throwable $e) {
            $this->redis = null;
            $this->available = false;
        }
    }

    /**
     * 获取单例
     */
    public static function getInstance(array $config = []): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * 使用 Config 对象初始化
     */
    public static function initWithConfig(Config $config): self
    {
        if (self::$instance === null) {
            self::$instance = new self([
                'redis_host'     => $config->get('redis_host', '127.0.0.1'),
                'redis_port'     => $config->get('redis_port', 6379),
                'redis_password' => $config->get('redis_password', ''),
                'redis_db'       => $config->get('redis_db', 0),
                'redis_prefix'   => $config->getRedisPrefix(),
            ]);
        }
        return self::$instance;
    }

    /**
     * Redis 是否可用
     */
    public function isAvailable(): bool
    {
        return $this->available && $this->redis !== null;
    }

    /**
     * 获取带前缀的完整 Key
     */
    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * 获取字符串值
     */
    public function get(string $key): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }
        try {
            $value = $this->redis->get($this->key($key));
            return $value !== false ? $value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 设置字符串值（带 TTL）
     * @param int $ttl 过期秒数，0 表示永不过期
     */
    public function set(string $key, string $value, int $ttl = 0): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        try {
            $fullKey = $this->key($key);
            if ($ttl > 0) {
                return (bool)$this->redis->setex($fullKey, $ttl, $value);
            }
            return (bool)$this->redis->set($fullKey, $value);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * SET NX EX 原子操作（用作分布式锁）
     * @return bool true=获取锁成功，false=锁已被占用
     */
    public function setnx(string $key, string $value, int $ttl): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        try {
            $fullKey = $this->key($key);
            // Redis::set 的第四个参数支持 nx + ex
            $result = $this->redis->set($fullKey, $value, ['nx', 'ex' => $ttl]);
            return $result === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 删除 key
     */
    public function del(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        try {
            return (bool)$this->redis->del($this->key($key));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 检查 key 是否存在
     */
    public function exists(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        try {
            return (bool)$this->redis->exists($this->key($key));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 获取 TTL
     * @return int -1=永不过期，-2=不存在，>0=剩余秒数
     */
    public function ttl(string $key): int
    {
        if (!$this->isAvailable()) {
            return -2;
        }
        try {
            return $this->redis->ttl($this->key($key));
        } catch (\Throwable $e) {
            return -2;
        }
    }

    /**
     * 设置过期时间
     */
    public function expire(string $key, int $ttl): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        try {
            return $this->redis->expire($this->key($key), $ttl);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * INCR 自增
     */
    public function incr(string $key): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }
        try {
            return $this->redis->incr($this->key($key));
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

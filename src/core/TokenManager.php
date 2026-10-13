<?php
/**
 * access_token 中控管理器
 * 
 * 核心功能：
 * - Redis 主存储 + MySQL 兜底
 * - 分布式锁防止并发刷新
 * - 提前刷新（过期前 N 秒自动更新）
 * 
 * 企业微信 access_token 特性：
 * - 有效期 7200 秒（2 小时）
 * - 同一 secret 全局唯一，新 token 生成后旧 token 立即失效
 * - 获取频率限制 2000 次/天/secret
 */

namespace Wxwork\Core;

class TokenManager
{
    /** @var Config */
    private Config $config;

    /** @var RedisClient|null */
    private ?RedisClient $redis;

    /** @var Database|null */
    private ?Database $db;

    // Redis Key
    const REDIS_KEY_TOKEN = 'YOUR_SECRET';
    const REDIS_KEY_LOCK  = 'token_refresh_lock';

    /**
     * @param Config         $config
     * @param RedisClient|null $redis
     * @param Database|null  $db
     */
    public function __construct(Config $config, ?RedisClient $redis = null, ?Database $db = null)
    {
        $this->config = $config;
        $this->redis = $redis;
        $this->db = $db;
    }

    /**
     * 获取有效的 access_token
     * 
     * 流程：
     * 1. 查 Redis → 命中直接返回
     * 2. 查 MySQL → 命中直接返回
     * 3. 获取分布式锁 → 调 API 获取新 token → 写 Redis + DB → 释放锁
     * 
     * @return string access_token
     * @throws TokenException
     */
    public function getToken(): string
    {
        $token = YOUR_SECRET);
        if ($token !== null) {
            return $token;
        }

        // 尝试获取分布式锁
        $lockAcquired = $this->acquireLock();

        if ($lockAcquired) {
            try {
                // 获取锁后再次检查缓存（双重检查，防止并发）
                $token = YOUR_SECRET);
                if ($token !== null) {
                    return $token;
                }
                // 从 API 获取新 token
                return $this->fetchAndStore();
            } finally {
                $this->releaseLock();
            }
        }

        // 未获取到锁，等待后重试
        $maxRetries = 5;
        for ($i = 0; $i < $maxRetries; $i++) {
            usleep(200000); // 等待 200ms
            $token = YOUR_SECRET);
            if ($token !== null) {
                return $token;
            }
        }

        // 最后兜底：直接调 API
        return $this->fetchAndStore();
    }

    /**
     * 从缓存层获取 token（Redis → DB）
     * @return string|null
     */
    private function getFromCache(): ?string
    {
        // 1. Redis
        if ($this->redis && $this->redis->isAvailable()) {
            $token = YOUR_SECRET);
            if ($token !== null && $token !== '') {
                return $token;
            }
        }

        // 2. MySQL 兜底
        if ($this->db) {
            try {
                $row = $this->db->selectOne(
                    'SELECT token FROM `wxwork_token` WHERE token_type = ? AND status = 1 AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
                    ['access_token']
                );
                if ($row && !empty($row['token'])) {
                    // 回写 Redis
                    $this->cacheToRedis($row['token']);
                    return $row['token'];
                }
            } catch (\Throwable $e) {
                // 表可能不存在或其他错误，继续往下
            }
        }

        return null;
    }

    /**
     * 从企业微信 API 获取新 token 并存储
     * @return string
     * @throws TokenException
     */
    private function fetchAndStore(): string
    {
        $corpid = $this->config->getCorpId();
        $corpsecret = $this->config->getCorpSecret();

        if (empty($corpid) || empty($corpsecret)) {
            throw new TokenException('corpid 或 corpsecret 未配置');
        }

        $url = sprintf(
            '%s/cgi-bin/gettoken?corpid=%s&corpsecret=%s',
            $this->config->getApiDomain(),
            urlencode($corpid),
            urlencode($corpsecret)
        );

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->config->getHttpTimeout(),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['User-Agent: ' . $this->config->get('http_user_agent', 'WxworkManager/1.0')],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new TokenException('获取 token 网络错误: ' . $curlError);
        }

        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data['access_token'])) {
            $errmsg = $data['errmsg'] ?? '未知错误';
            throw new TokenException('获取 token 失败: ' . $errmsg, $data['errcode'] ?? -1);
        }

        $token = YOUR_SECRET'access_token'];
        $expiresIn = (int)($data['expires_in'] ?? 7200);

        // 存储到缓存
        $this->cacheToRedis($token, $expiresIn);
        $this->cacheToDb($token, $expiresIn);

        return $token;
    }

    /**
     * 缓存到 Redis
     */
    private function cacheToRedis(string $token, int $expiresIn = 7200): void
    {
        if (!$this->redis || !$this->redis->isAvailable()) {
            return;
        }
        // TTL = 实际过期时间 - 提前刷新时间
        $ttl = max(60, $expiresIn - $this->config->getTokenAheadRefresh());
        $this->redis->set(self::REDIS_KEY_TOKEN, $token, $ttl);
    }

    /**
     * 缓存到 MySQL
     */
    private function cacheToDb(string $token, int $expiresIn = 7200): void
    {
        if (!$this->db) {
            return;
        }
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

            // 标记旧 token 为无效
            $this->db->execute(
                'UPDATE `wxwork_token` SET status = 0 WHERE token_type = ?',
                ['access_token']
            );

            // 插入新记录
            $this->db->insertRow('wxwork_token', [
                'token'      => $token,
                'expires_at' => $expiresAt,
                'token_type' => 'access_token',
                'status'     => 1,
            ]);
        } catch (\Throwable $e) {
            // 表可能还未创建，静默处理
        }
    }

    /**
     * 强制刷新 token
     */
    public function refreshToken(): string
    {
        try {
            $lockAcquired = $this->acquireLock();
            try {
                $result = $this->fetchAndStore();
                // 短暂延迟确保新 token 在企业微信侧生效
                usleep(100000);
                return $result;
            } finally {
                if ($lockAcquired) {
                    $this->releaseLock();
                }
            }
        } catch (\Throwable $e) {
            throw new TokenException('刷新 token 失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 清除所有缓存
     */
    public function clearCache(): void
    {
        if ($this->redis && $this->redis->isAvailable()) {
            $this->redis->del(self::REDIS_KEY_TOKEN);
            $this->redis->del(self::REDIS_KEY_LOCK);
        }
        if ($this->db) {
            try {
                $this->db->execute('UPDATE `wxwork_token` SET status = 0');
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    /**
     * 获取分布式刷新锁（Redis SET NX EX）
     * @return bool 是否获取成功
     */
    private function acquireLock(): bool
    {
        if (!$this->redis || !$this->redis->isAvailable()) {
            return true; // 无 Redis 时直接放行
        }
        return $this->redis->setnx(
            self::REDIS_KEY_LOCK,
            (string)time(),
            $this->config->getTokenLockTimeout()
        );
    }

    /**
     * 释放分布式刷新锁
     */
    private function releaseLock(): void
    {
        if ($this->redis && $this->redis->isAvailable()) {
            $this->redis->del(self::REDIS_KEY_LOCK);
        }
    }
}

<?php
/**
 * 会话管理
 * 
 * 管理后台的登录会话。
 * 如果 Redis 可用，使用 Redis 存储 Session，否则使用 PHP 原生 Session。
 */

namespace Wxwork\Core;

class RedisSessionHandler implements \SessionHandlerInterface
{
    private ?RedisClient $redis;
    private int $lifetime;

    public function __construct(?RedisClient $redis, int $lifetime)
    {
        $this->redis = $redis;
        $this->lifetime = $lifetime;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        if (!$this->redis || !$this->redis->isAvailable()) {
            return false;
        }
        try {
            $data = $this->redis->get('session:' . $id);
            return $data !== null ? $data : false;
        } catch (\Throwable $e) {
            error_log('[RedisSession] read error: ' . $e->getMessage());
            return false;
        }
    }

    public function write(string $id, string $data): bool
    {
        if (!$this->redis || !$this->redis->isAvailable()) {
            return false;
        }
        try {
            return $this->redis->set('session:' . $id, $data, $this->lifetime);
        } catch (\Throwable $e) {
            error_log('[RedisSession] write error: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        if (!$this->redis || !$this->redis->isAvailable()) {
            return false;
        }
        try {
            return $this->redis->del('session:' . $id);
        } catch (\Throwable $e) {
            error_log('[RedisSession] destroy error: ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        // Redis 的 TTL 自动过期，无需手动 GC
        return 0;
    }
}

class Session
{
    /** @var RedisClient|null */
    private static ?RedisClient $redis = null;

    /** @var Config|null */
    private static ?Config $config = null;

    /** @var string Session 名称 */
    private static string $sessionName = 'WXWORK_SESS';

    /** @var int 会话有效期（秒） */
    private static int $lifetime = 7200;

    /** @var bool 是否已初始化 */
    private static bool $initialized = false;

    /**
     * 初始化 Session（应在请求开始时调用）
     */
    public static function init(Config $config, ?RedisClient $redis = null): void
    {
        if (self::$initialized) return;

        self::$config = $config;
        self::$redis = $redis;
        self::$sessionName = $config->get('session_name', 'WXWORK_SESS');
        self::$lifetime = (int)$config->get('session_lifetime', 7200);

        // Session 安全设置
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string)self::$lifetime);
        ini_set('session.cookie_lifetime', (string)self::$lifetime);

        // 如果 Redis 可用，使用 Redis Session Handler
        // 必须在 session_start() 之前注册
        if ($redis && $redis->isAvailable()) {
            try {
                $handler = new RedisSessionHandler($redis, self::$lifetime);
                session_set_save_handler($handler, true);
            } catch (\Throwable $e) {
                // Redis session handler 注册失败，回退到文件 session
                error_log('[Session] Redis handler 注册失败，使用文件 Session: ' . $e->getMessage());
            }
        }

        session_name(self::$sessionName);
        session_start();

        self::$initialized = true;
    }

    /**
     * 设置用户登录状态
     * @param int   $userId   管理员 ID
     * @param array $userData 用户数据
     */
    public static function login(int $userId, array $userData): void
    {
        self::ensureInit();

        $_SESSION['user'] = [
            'id'          => $userId,
            'username'    => $userData['username'] ?? '',
            'realname'    => $userData['realname'] ?? '',
            'role'        => $userData['role'] ?? 'admin',
            'login_at'    => time(),
            'login_ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        // 更新数据库 last_login_at, last_login_ip
        try {
            $db = Database::getInstance();
            $db->updateRow(
                'wxwork_admin_user',
                [
                    'last_login_at' => date('Y-m-d H:i:s'),
                    'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ],
                'id = ?',
                [$userId]
            );
        } catch (\Throwable $e) {
            // 非关键操作
        }

        // 重新生成 session ID 防固定
        session_regenerate_id(true);
    }

    /**
     * 检查是否已登录
     */
    public static function isLoggedIn(): bool
    {
        self::ensureInit();
        return !empty($_SESSION['user']['id']);
    }

    /**
     * 获取当前登录用户
     */
    public static function getUser(): ?array
    {
        self::ensureInit();
        return $_SESSION['user'] ?? null;
    }

    /**
     * 获取当前用户 ID
     */
    public static function getUserId(): ?int
    {
        return self::getUser()['id'] ?? null;
    }

    /**
     * 检查是否超级管理员
     */
    public static function isSuperAdmin(): bool
    {
        $user = self::getUser();
        return $user && ($user['role'] ?? '') === 'superadmin';
    }

    /**
     * 退出登录
     */
    public static function logout(): void
    {
        self::ensureInit();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * 生成 CSRF Token
     */
    public static function csrfToken(): string
    {
        self::ensureInit();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * 验证 CSRF Token
     */
    public static function validateCsrf(?string $token): bool
    {
        if (empty($token)) {
            $token = YOUR_SECRET'_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        return hash_equals(self::csrfToken(), $token);
    }

    /**
     * 确保已初始化
     */
    private static function ensureInit(): void
    {
        if (!self::$initialized) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            self::$initialized = true;
        }
    }
}

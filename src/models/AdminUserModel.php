<?php
namespace Wxwork\Models;

/**
 * 管理员用户 Model
 * 
 * 对应表：wxwork_admin_user
 */
class AdminUserModel extends BaseModel
{
    protected static string $table = 'wxwork_admin_user';
    protected static string $primaryKey = 'id';

    /** 根据用户名查找 */
    public static function findByUsername(string $username): ?array
    {
        return static::db()->selectOne(
            'SELECT * FROM wxwork_admin_user WHERE username = ? AND is_enabled = 1 LIMIT 1',
            [$username]
        );
    }

    /** 验证密码 */
    public static function verifyPassword(string $username, string $password): ?array
    {
        $user = self::findByUsername($username);
        if (!$user) {
            return null;
        }
        if (!password_verify($password, $user['password'])) {
            return null;
        }
        return $user;
    }

    /** 创建管理员 */
    public static function create(array $data): int
    {
        return (int)static::db()->insertRow('wxwork_admin_user', [
            'username'      => $data['username'],
            'password'      => password_hash($data['password'], PASSWORD_BCRYPT),
            'realname'      => $data['realname'] ?? '',
            'email'         => $data['email'] ?? '',
            'role'          => $data['role'] ?? 'admin',
            'is_enabled'    => 1,
        ]);
    }

    /** 更新管理员 */
    public static function updateAdmin(int $id, array $data): void
    {
        $update = [];
        if (isset($data['realname'])) $update['realname'] = $data['realname'];
        if (isset($data['email'])) $update['email'] = $data['email'];
        if (isset($data['role'])) $update['role'] = $data['role'];
        if (isset($data['is_enabled'])) $update['is_enabled'] = $data['is_enabled'];
        if (isset($data['password'])) $update['password'] = password_hash($data['password'], PASSWORD_BCRYPT);

        if (!empty($update)) {
            static::db()->updateRow('wxwork_admin_user', $update, 'id = ?', [$id]);
        }
    }

    /** 更新登录信息 */
    public static function updateLoginInfo(int $id, string $ip): void
    {
        static::db()->execute(
            'UPDATE wxwork_admin_user SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?',
            [$ip, $id]
        );
    }

    /** 禁用管理员 */
    public static function disable(int $id): void
    {
        static::db()->execute(
            'UPDATE wxwork_admin_user SET is_enabled = 0 WHERE id = ?',
            [$id]
        );
    }

    /** 获取管理员列表 */
    public static function getList(int $page = 1, int $size = 20): array
    {
        return static::paginate($page, $size, '1=1', []);
    }

    /** 检查是否超级管理员 */
    public static function isSuperAdmin(int $id): bool
    {
        $user = static::find($id);
        return $user && ($user['role'] ?? '') === 'superadmin';
    }

    /** 登录日志校验：检查是否被锁定（基于错误次数） */
    public static function recordLoginAttempt(string $username, bool $success): void
    {
        // 可在此扩展：Redis 记录失败次数，超过阈值锁定
        // 当前版本先不做锁，后续可以配合 Redis 实现
        if (!$success) {
            // 记录失败日志（可选：写入专门的登录日志表）
            error_log("[AdminUser] Login failed for user: {$username}, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    }
}

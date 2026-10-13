<?php
namespace Wxwork\Models;

/**
 * 成员 Model
 */
class MemberModel extends BaseModel
{
    protected static string $table = 'wxwork_member';
    protected static string $primaryKey = 'userid';

    public static function upsertFromApi(array $data): void
    {
        static::db()->execute(
            'INSERT INTO wxwork_member (userid, name, mobile, email, position, avatar, status, main_department, is_deleted, sync_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
             ON DUPLICATE KEY UPDATE name=VALUES(name), mobile=VALUES(mobile), email=VALUES(email),
             position=VALUES(position), avatar=VALUES(avatar), status=VALUES(status),
             main_department=VALUES(main_department), updated_at=NOW()',
            [$data['userid']??'', $data['name']??'', $data['mobile']??'', $data['email']??'',
             $data['position']??'', $data['avatar']??'', $data['status']??1, $data['main_department']??0]
        );
    }

    public static function softDelete(string $userid): void
    {
        static::db()->execute(
            'UPDATE wxwork_member SET is_deleted=1, updated_at=NOW() WHERE userid=?',
            [$userid]
        );
    }

    /** 按部门搜索 */
    public static function byDept(int $deptId, bool $fetchChild = false): array
    {
        // 从数据库查询（同步后的数据）
        $where = 'is_deleted = 0';
        $params = [];
        if ($deptId > 0) {
            $where .= ' AND main_department = ?';
            $params[] = $deptId;
        }
        return static::db()->select(
            'SELECT * FROM ' . static::$table . ' WHERE ' . $where . ' ORDER BY name',
            $params
        );
    }
}

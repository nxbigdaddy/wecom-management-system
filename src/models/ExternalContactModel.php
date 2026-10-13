<?php
namespace Wxwork\Models;

/**
 * 客户联系 Model
 * 
 * 对应表：wxwork_external_contact, wxwork_external_follow, wxwork_external_group
 */
class ExternalContactModel extends BaseModel
{
    protected static string $table = 'wxwork_external_contact';
    protected static string $primaryKey = 'external_userid';

    // ==================== 客户基本操作 ====================

    /** 从 API 数据同步客户 */
    public static function upsertFromApi(array $data): void
    {
        static::db()->execute(
            'INSERT INTO wxwork_external_contact 
             (external_userid, name, avatar, gender, type, unionid, corp_name, corp_full_name, is_deleted, sync_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
             ON DUPLICATE KEY UPDATE name=VALUES(name), avatar=VALUES(avatar), gender=VALUES(gender),
             type=VALUES(type), unionid=VALUES(unionid), corp_name=VALUES(corp_name),
             corp_full_name=VALUES(corp_full_name), updated_at=NOW(), sync_version=sync_version+1',
            [
                $data['external_userid'] ?? '',
                $data['name'] ?? '',
                $data['avatar'] ?? '',
                (int)($data['gender'] ?? 0),
                (int)($data['type'] ?? 1),
                $data['unionid'] ?? '',
                $data['corp_name'] ?? '',
                $data['corp_full_name'] ?? '',
            ]
        );
    }

    /** 软删除 */
    public static function softDelete(string $externalUserid): void
    {
        static::db()->execute(
            'UPDATE wxwork_external_contact SET is_deleted=1, updated_at=NOW() WHERE external_userid=?',
            [$externalUserid]
        );
    }

    /** 按跟进人查找客户 */
    public static function byFollowUser(string $userid, int $page = 1, int $size = 20): array
    {
        $offset = ($page - 1) * $size;
        $total = (int)(static::db()->selectValue(
            'SELECT COUNT(*) FROM wxwork_external_contact ec
             INNER JOIN wxwork_external_follow ef ON ec.external_userid = ef.external_userid
             WHERE ef.userid = ? AND ec.is_deleted = 0 AND ef.is_deleted = 0',
            [$userid]
        ) ?? 0);
        $rows = static::db()->select(
            'SELECT ec.*, ef.userid as follow_userid, ef.remark, ef.add_time, ef.add_way
             FROM wxwork_external_contact ec
             INNER JOIN wxwork_external_follow ef ON ec.external_userid = ef.external_userid
             WHERE ef.userid = ? AND ec.is_deleted = 0 AND ef.is_deleted = 0
             ORDER BY ef.add_time DESC LIMIT ?, ?',
            [$userid, $offset, $size]
        );
        return ['total' => $total, 'page' => $page, 'size' => $size, 'rows' => $rows];
    }

    // ==================== 跟进关系 ====================

    /** 同步跟进关系 */
    public static function upsertFollow(string $externalUserid, string $userid, array $data): void
    {
        static::db()->execute(
            'INSERT INTO wxwork_external_follow 
             (external_userid, userid, remark, description, remark_corp_name, remark_mobiles, add_way, state, add_time, tags, is_deleted, sync_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
             ON DUPLICATE KEY UPDATE remark=VALUES(remark), description=VALUES(description),
             remark_corp_name=VALUES(remark_corp_name), remark_mobiles=VALUES(remark_mobiles),
             add_way=VALUES(add_way), state=VALUES(state), tags=VALUES(tags),
             updated_at=NOW(), sync_version=sync_version+1',
            [
                $externalUserid,
                $userid,
                $data['remark'] ?? '',
                $data['description'] ?? '',
                $data['remark_corp_name'] ?? '',
                $data['remark_mobiles'] ?? '',
                (int)($data['add_way'] ?? 0),
                $data['state'] ?? '',
                $data['add_time'] ? date('Y-m-d H:i:s', (int)$data['add_time']) : null,
                isset($data['tags']) ? json_encode($data['tags'], JSON_UNESCAPED_UNICODE) : null,
            ]
        );
    }

    /** 软删除跟进关系 */
    public static function softDeleteFollow(string $externalUserid, string $userid): void
    {
        static::db()->execute(
            'UPDATE wxwork_external_follow SET is_deleted=1, updated_at=NOW() WHERE external_userid=? AND userid=?',
            [$externalUserid, $userid]
        );
    }

    /** 获取未跟进客户（离职继承用） */
    public static function getUnassigned(int $pageId = 0, int $pageSize = 100): array
    {
        $offset = $pageId * $pageSize;
        return static::db()->select(
            'SELECT ef.external_userid, ef.userid as handover_userid, ec.name
             FROM wxwork_external_follow ef
             LEFT JOIN wxwork_external_contact ec ON ef.external_userid = ec.external_userid
             WHERE ef.is_deleted = 1 AND ec.is_deleted = 0
             LIMIT ?, ?',
            [$offset, $pageSize]
        );
    }

    // ==================== 客户群 ====================

    /** 同步客户群 */
    public static function upsertGroup(array $data): void
    {
        static::db()->execute(
            'INSERT INTO wxwork_external_group 
             (chat_id, name, owner, member_count, status, notice, create_time, is_deleted, sync_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1)
             ON DUPLICATE KEY UPDATE name=VALUES(name), owner=VALUES(owner), member_count=VALUES(member_count),
             status=VALUES(status), notice=VALUES(notice), updated_at=NOW(), sync_version=sync_version+1',
            [
                $data['chat_id'] ?? '',
                $data['name'] ?? '',
                $data['owner'] ?? '',
                (int)($data['member_count'] ?? 0),
                (int)($data['status'] ?? 0),
                $data['notice'] ?? '',
                isset($data['create_time']) ? date('Y-m-d H:i:s', (int)$data['create_time']) : null,
            ]
        );
    }

    /** 软删除客户群 */
    public static function softDeleteGroup(string $chatId): void
    {
        static::db()->execute(
            'UPDATE wxwork_external_group SET is_deleted=1, updated_at=NOW() WHERE chat_id=?',
            [$chatId]
        );
    }

    /** 获取客户群列表 */
    public static function getGroups(int $page = 1, int $size = 20): array
    {
        return static::paginate($page, $size, 'is_deleted = 0', []);
    }
}

<?php
namespace Wxwork\Models;

/**
 * 标签 Model
 * 
 * 对应表：wxwork_tag, wxwork_tag_member
 */
class TagModel extends BaseModel
{
    protected static string $table = 'wxwork_tag';
    protected static string $primaryKey = 'tag_id';

    /** 从 API 数据同步标签 */
    public static function upsertFromApi(array $data): void
    {
        static::db()->execute(
            'INSERT INTO wxwork_tag (tag_id, tag_name, is_deleted, sync_version)
             VALUES (?, ?, 0, 1)
             ON DUPLICATE KEY UPDATE tag_name=VALUES(tag_name), updated_at=NOW(), sync_version=sync_version+1',
            [(int)$data['tagid'], $data['tagname']]
        );
    }

    /** 软删除 */
    public static function softDelete(int $tagId): void
    {
        static::db()->execute(
            'UPDATE wxwork_tag SET is_deleted=1, updated_at=NOW() WHERE tag_id=?',
            [$tagId]
        );
    }

    /** 获取标签成员 */
    public static function getTagMembers(int $tagId): array
    {
        return static::db()->select(
            'SELECT tm.*, m.name, m.avatar FROM wxwork_tag_member tm
             LEFT JOIN wxwork_member m ON tm.userid = m.userid AND m.is_deleted = 0
             WHERE tm.tag_id = ?',
            [$tagId]
        );
    }

    /** 给标签添加成员 */
    public static function addMembers(int $tagId, array $userids): void
    {
        foreach ($userids as $userid) {
            static::db()->execute(
                'INSERT IGNORE INTO wxwork_tag_member (tag_id, userid) VALUES (?, ?)',
                [$tagId, $userid]
            );
        }
    }

    /** 从标签移除成员 */
    public static function removeMembers(int $tagId, array $userids): void
    {
        if (empty($userids)) return;
        $placeholders = implode(',', array_fill(0, count($userids), '?'));
        static::db()->execute(
            "DELETE FROM wxwork_tag_member WHERE tag_id = ? AND userid IN ({$placeholders})",
            array_merge([$tagId], $userids)
        );
    }

    /** 清除标签所有成员关联 */
    public static function clearMembers(int $tagId): void
    {
        static::db()->execute('DELETE FROM wxwork_tag_member WHERE tag_id = ?', [$tagId]);
    }

    /** 获取有效标签列表 */
    public static function getActive(): array
    {
        return static::where('is_deleted', 0, 'tag_id ASC');
    }
}

<?php
namespace Wxwork\Models;

/**
 * 部门 Model
 */
class DepartmentModel extends BaseModel
{
    protected static string $table = 'wxwork_department';
    protected static string $primaryKey = 'dept_id';

    /** 同步单个部门（回调或全量同步用） */
    public static function upsertFromApi(array $data): void
    {
        static::db()->execute(
            'INSERT INTO wxwork_department (dept_id, name, parent_id, `order`, is_deleted, sync_version)
             VALUES (?, ?, ?, ?, 0, 1)
             ON DUPLICATE KEY UPDATE name=VALUES(name), parent_id=VALUES(parent_id),
             `order`=VALUES(`order`), updated_at=NOW()',
            [$data['id'], $data['name'], $data['parentid'] ?? 0, $data['order'] ?? 1]
        );
    }

    /** 软删除 */
    public static function softDelete(int $deptId): void
    {
        static::db()->execute(
            'UPDATE wxwork_department SET is_deleted=1, updated_at=NOW() WHERE dept_id=?',
            [$deptId]
        );
    }

    /** 获取树形结构 */
    public static function getTree(int $parentId = 0): array
    {
        $all = static::where('is_deleted', 0, '`order` ASC');
        $tree = [];
        foreach ($all as $d) {
            if ((int)$d['parent_id'] === $parentId) {
                $d['children'] = static::getTreeRecursive($all, (int)$d['dept_id']);
                $tree[] = $d;
            }
        }
        return $tree;
    }

    private static function getTreeRecursive(array $all, int $parentId): array
    {
        $branch = [];
        foreach ($all as $d) {
            if ((int)$d['parent_id'] === $parentId) {
                $d['children'] = static::getTreeRecursive($all, (int)$d['dept_id']);
                $branch[] = $d;
            }
        }
        return $branch;
    }
}

<?php
/**
 * 通讯录变更事件处理器
 * 
 * 接收企业微信通讯录变更回调，实时同步到本地数据库。
 */
namespace Wxwork\Callback\Handlers;

use Wxwork\Callback\HandlerInterface;
use Wxwork\Core\Database;

class ChangeContact implements HandlerInterface
{
    public function handle(array $event): string
    {
        $changeType = $event['ChangeType'] ?? '';
        try {
            $db = Database::getInstance();
            
            switch ($changeType) {
                // ===== 成员变更 =====
                case 'create_user':
                case 'update_user':
                    $db->execute(
                        'INSERT INTO wxwork_member 
                         (userid, name, mobile, position, avatar, status, main_department, is_deleted, sync_version)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1)
                         ON DUPLICATE KEY UPDATE 
                         name=VALUES(name), mobile=VALUES(mobile), position=VALUES(position),
                         avatar=VALUES(avatar), status=VALUES(status), main_department=VALUES(main_department),
                         updated_at=NOW(), sync_version=sync_version+1',
                        [
                            $event['UserID'] ?? '',
                            $event['Name'] ?? '',
                            $event['Mobile'] ?? '',
                            $event['Position'] ?? '',
                            $event['Avatar'] ?? '',
                            $event['Status'] ?? 1,
                            (int)($event['MainDepartment'] ?? 0),
                        ]
                    );
                    break;

                case 'delete_user':
                    $db->execute(
                        'UPDATE wxwork_member SET is_deleted=1, updated_at=NOW() WHERE userid=?',
                        [$event['UserID'] ?? '']
                    );
                    break;

                // ===== 部门变更 =====
                case 'create_party':
                case 'update_party':
                    $db->execute(
                        'INSERT INTO wxwork_department 
                         (dept_id, name, parent_id, `order`, is_deleted, sync_version)
                         VALUES (?, ?, ?, ?, 0, 1)
                         ON DUPLICATE KEY UPDATE 
                         name=VALUES(name), parent_id=VALUES(parent_id), `order`=VALUES(`order`),
                         updated_at=NOW(), sync_version=sync_version+1',
                        [
                            (int)($event['Id'] ?? 0),
                            $event['Name'] ?? '',
                            (int)($event['ParentId'] ?? 0),
                            (int)($event['Order'] ?? 0),
                        ]
                    );
                    break;

                case 'delete_party':
                    $db->execute(
                        'UPDATE wxwork_department SET is_deleted=1, updated_at=NOW() WHERE dept_id=?',
                        [(int)($event['Id'] ?? 0)]
                    );
                    break;

                // ===== 标签变更 =====
                case 'update_tag':
                    $tagId = (int)($event['TagId'] ?? 0);
                    // 删除旧关联
                    $db->execute('DELETE FROM wxwork_tag_member WHERE tag_id=?', [$tagId]);
                    // 插入新关联
                    $addItems = $event['AddUserItems'] ?? [];
                    if (is_string($addItems)) $addItems = json_decode($addItems, true);
                    if (is_array($addItems)) {
                        foreach ($addItems as $uid) {
                            $db->execute(
                                'INSERT IGNORE INTO wxwork_tag_member (tag_id, userid) VALUES (?, ?)',
                                [$tagId, is_string($uid) ? $uid : ($uid['UserID'] ?? '')]
                            );
                        }
                    }
                    break;
            }
        } catch (\Throwable $e) {
            error_log('[ChangeContact] Sync error: ' . $e->getMessage());
        }

        return 'success';
    }
}

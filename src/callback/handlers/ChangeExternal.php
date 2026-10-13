<?php
/**
 * 客户联系变更事件处理器
 * 
 * 接收企业微信客户相关变更回调，实时同步到本地数据库。
 */
namespace Wxwork\Callback\Handlers;

use Wxwork\Callback\HandlerInterface;
use Wxwork\Core\Database;

class ChangeExternal implements HandlerInterface
{
    public function handle(array $event): string
    {
        $changeType = $event['ChangeType'] ?? '';
        try {
            $db = Database::getInstance();
            
            switch ($changeType) {
                case 'add_external_contact':
                    // 新客户添加：记录跟进关系
                    $db->execute(
                        'INSERT INTO wxwork_external_follow 
                         (external_userid, userid, add_time, add_way, state, sync_version)
                         VALUES (?, ?, FROM_UNIXTIME(?), ?, ?, 1)
                         ON DUPLICATE KEY UPDATE 
                         add_time=VALUES(add_time), sync_version=sync_version+1, is_deleted=0, updated_at=NOW()',
                        [
                            $event['ExternalUserID'] ?? '',
                            $event['UserID'] ?? '',
                            (int)($event['CreateTime'] ?? time()),
                            (int)($event['AddWay'] ?? 0),
                            $event['State'] ?? '',
                        ]
                    );
                    break;

                case 'del_external_contact':
                case 'del_follow_user':
                    // 删除客户/被客户删除：标记跟进关系删除
                    $db->execute(
                        'UPDATE wxwork_external_follow SET is_deleted=1, updated_at=NOW() 
                         WHERE external_userid=? AND userid=?',
                        [
                            $event['ExternalUserID'] ?? '',
                            $event['UserID'] ?? '',
                        ]
                    );
                    break;

                case 'change_external_chat':
                case 'create_chat':
                case 'update_chat':
                    // 客户群变更
                    $db->execute(
                        'INSERT INTO wxwork_external_group 
                         (chat_id, name, owner, status, sync_version, create_time)
                         VALUES (?, ?, ?, ?, 1, NOW())
                         ON DUPLICATE KEY UPDATE 
                         name=VALUES(name), owner=VALUES(owner), status=VALUES(status),
                         updated_at=NOW(), sync_version=sync_version+1',
                        [
                            $event['ChatId'] ?? '',
                            $event['Name'] ?? '',
                            $event['Owner'] ?? '',
                            (int)($event['Status'] ?? 0),
                        ]
                    );
                    break;

                case 'dismiss_chat':
                    // 解散客户群
                    $db->execute(
                        'UPDATE wxwork_external_group SET is_deleted=1, updated_at=NOW() WHERE chat_id=?',
                        [$event['ChatId'] ?? '']
                    );
                    break;
            }
        } catch (\Throwable $e) {
            error_log('[ChangeExternal] Sync error: ' . $e->getMessage());
        }

        return 'success';
    }
}

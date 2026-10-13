<?php
/**
 * 数据同步脚本 - 通讯录全量同步
 * 
 * 使用方式：
 *   php sync/SyncContacts.php
 *   或通过 Web 触发：http://domain.com/sync/contacts?key=xxx
 */
namespace Wxwork\Sync;

require __DIR__ . '/../../vendor/autoload.php';

use Wxwork\Wxwork;
use Wxwork\Core\Database;

$wx = Wxwork::create();
$db = $wx->getDatabase();

echo "=== 通讯录数据同步 ===\n";
echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

// 1. 同步部门
echo "[部门] 开始同步...\n";
try {
    $result = $wx->get('/cgi-bin/department/list');
    if (($result['errcode'] ?? -1) === 0) {
        $depts = $result['department'] ?? [];
        $count = 0;
        foreach ($depts as $d) {
            $db->execute(
                'INSERT INTO wxwork_department (dept_id, name, name_en, parent_id, `order`, is_deleted, sync_version)
                 VALUES (?, ?, ?, ?, ?, 0, 1)
                 ON DUPLICATE KEY UPDATE name=VALUES(name), parent_id=VALUES(parent_id), 
                 `order`=VALUES(`order`), updated_at=NOW(), sync_version=sync_version+1',
                [$d['id'], $d['name'], $d['name_en'] ?? '', $d['parentid'] ?? 0, $d['order'] ?? 1]
            );
            $count++;
        }
        echo "  同步完成: {$count} 个部门\n";
    }
} catch (\Throwable $e) {
    echo "  错误: " . $e->getMessage() . "\n";
}

// 2. 同步成员（先获取根部门成员列表）
echo "[成员] 开始同步...\n";
try {
    $result = $wx->get('/cgi-bin/user/list', ['department_id' => 1, 'fetch_child' => 1]);
    if (($result['errcode'] ?? -1) === 0) {
        $members = $result['userlist'] ?? [];
        $count = 0;
        foreach ($members as $m) {
            $db->execute(
                'INSERT INTO wxwork_member (userid, name, mobile, email, position, avatar, status, main_department, is_deleted, sync_version)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
                 ON DUPLICATE KEY UPDATE name=VALUES(name), mobile=VALUES(mobile), email=VALUES(email),
                 position=VALUES(position), avatar=VALUES(avatar), status=VALUES(status),
                 main_department=VALUES(main_department), updated_at=NOW(), sync_version=sync_version+1',
                [
                    $m['userid'], $m['name'], $m['mobile'] ?? '', $m['email'] ?? '',
                    $m['position'] ?? '', $m['avatar'] ?? '', $m['status'] ?? 1,
                    $m['main_department'] ?? 0,
                ]
            );
            $count++;
        }
        echo "  同步完成: {$count} 个成员\n";
    }
} catch (\Throwable $e) {
    echo "  错误: " . $e->getMessage() . "\n";
}

// 3. 同步标签
echo "[标签] 开始同步...\n";
try {
    $result = $wx->get('/cgi-bin/tag/list');
    if (($result['errcode'] ?? -1) === 0) {
        $tags = $result['taglist'] ?? [];
        $count = 0;
        foreach ($tags as $t) {
            $db->execute(
                'INSERT INTO wxwork_tag (tag_id, tag_name, is_deleted, sync_version)
                 VALUES (?, ?, 0, 1)
                 ON DUPLICATE KEY UPDATE tag_name=VALUES(tag_name), updated_at=NOW(), sync_version=sync_version+1',
                [$t['tagid'], $t['tagname']]
            );
            // 同步标签成员
            try {
                $tagResult = $wx->get('/cgi-bin/tag/get', ['tagid' => $t['tagid']]);
                if (($tagResult['errcode'] ?? -1) === 0) {
                    $userlist = $tagResult['userlist'] ?? [];
                    $db->execute('DELETE FROM wxwork_tag_member WHERE tag_id=?', [$t['tagid']]);
                    foreach ($userlist as $u) {
                        $db->execute(
                            'INSERT IGNORE INTO wxwork_tag_member (tag_id, userid) VALUES (?, ?)',
                            [$t['tagid'], $u['userid']]
                        );
                    }
                }
            } catch (\Throwable $e) {}
            $count++;
        }
        echo "  同步完成: {$count} 个标签\n";
    }
} catch (\Throwable $e) {
    echo "  错误: " . $e->getMessage() . "\n";
}

echo "\n结束时间: " . date('Y-m-d H:i:s') . "\n";
echo "=== 同步完成 ===\n";

<?php
/**
 * 数据同步脚本 - 外部联系人全量同步
 * 
 * 使用方式：php sync/SyncExternalContacts.php [userid]
 */
namespace Wxwork\Sync;

require __DIR__ . '/../../vendor/autoload.php';

use Wxwork\Wxwork;
use Wxwork\Core\Database;

$wx = Wxwork::create();
$db = $wx->getDatabase();

echo "=== 外部联系人数据同步 ===\n";
echo "开始时间: " . date('Y-m-d H:i:s') . "\n\n";

$targetUserid = $argv[1] ?? null;
$syncCount = ['customers' => 0, 'groups' => 0];

// 1. 获取所有成员列表
echo "[准备] 获取成员列表...\n";
try {
    $result = $wx->get('/cgi-bin/user/simplelist', ['department_id' => 1, 'fetch_child' => 1]);
    $members = ($result['errcode'] ?? -1) === 0 ? ($result['userlist'] ?? []) : [];
    echo "  获取到 " . count($members) . " 个成员\n\n";
} catch (\Throwable $e) {
    echo "  错误: " . $e->getMessage() . "\n";
    exit(1);
}

// 过滤目标成员
if ($targetUserid) {
    $members = array_filter($members, fn($m) => $m['userid'] === $targetUserid);
}

foreach ($members as $member) {
    $userid = $member['userid'];
    echo "[{$userid}] 同步客户...\n";
    
    // 2. 获取该成员的客户列表
    try {
        $result = $wx->get('/cgi-bin/externalcontact/list', ['userid' => $userid]);
        if (($result['errcode'] ?? -1) !== 0) {
            echo "  跳过 (errcode: {$result['errcode']})\n";
            continue;
        }
        
        $extUserids = $result['external_userid'] ?? [];
        
        foreach ($extUserids as $euid) {
            try {
                // 获取客户详情
                $detail = $wx->get('/cgi-bin/externalcontact/get', ['external_userid' => $euid]);
                if (($detail['errcode'] ?? -1) !== 0) continue;
                
                $c = $detail['external_contact'] ?? [];
                $f = $detail['follow_user'] ?? [];
                
                // 入库客户
                $db->execute(
                    'INSERT INTO wxwork_external_contact (external_userid, name, avatar, gender, type, unionid, corp_name, sync_version)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE name=VALUES(name), avatar=VALUES(avatar), type=VALUES(type),
                     corp_name=VALUES(corp_name), updated_at=NOW(), sync_version=sync_version+1',
                    [$euid, $c['name']??'', $c['avatar']??'', $c['gender']??0, $c['type']??1, $c['unionid']??'', $c['corp_name']??'']
                );
                
                // 入库跟进关系
                $addTime = $f['createtime'] ? date('Y-m-d H:i:s', $f['createtime']) : null;
                $db->execute(
                    'INSERT INTO wxwork_external_follow (external_userid, userid, remark, description, add_way, add_time, tags, sync_version)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE remark=VALUES(remark), description=VALUES(description),
                     add_way=VALUES(add_way), tags=VALUES(tags), updated_at=NOW(), sync_version=sync_version+1',
                    [$euid, $f['userid']??$userid, $f['remark']??'', $f['description']??'', $f['add_way']??0, $addTime, json_encode($f['tags']??[])]
                );
                
                $syncCount['customers']++;
            } catch (\Throwable $e) {
                echo "  {$euid} 错误: {$e->getMessage()}\n";
            }
        }
        
        echo "  同步 {$syncCount['customers']} 个客户\n";
        
    } catch (\Throwable $e) {
        echo "  错误: {$e->getMessage()}\n";
    }
}

// 3. 同步客户群
echo "\n[客户群] 同步...\n";
try {
    $result = $wx->post('/cgi-bin/externalcontact/groupchat/list', ['limit' => 100]);
    if (($result['errcode'] ?? -1) === 0) {
        $groups = $result['group_chat_list'] ?? [];
        foreach ($groups as $g) {
            $db->execute(
                'INSERT INTO wxwork_external_group (chat_id, name, owner, status, sync_version, create_time)
                 VALUES (?, ?, ?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE name=VALUES(name), owner=VALUES(owner), status=VALUES(status),
                 updated_at=NOW(), sync_version=sync_version+1',
                [$g['chat_id']??'', $g['name']??'', $g['owner']??'', $g['status']??0]
            );
            $syncCount['groups']++;
        }
    }
    echo "  同步 {$syncCount['groups']} 个客户群\n";
} catch (\Throwable $e) {
    echo "  错误: {$e->getMessage()}\n";
}

echo "\n结束时间: " . date('Y-m-d H:i:s') . "\n";
echo "=== 同步完成 ===\n";
echo "客户: {$syncCount['customers']}, 客户群: {$syncCount['groups']}\n";

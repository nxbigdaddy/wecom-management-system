<?php
/**
 * 标签同步脚本
 * 
 * 从企业微信 API 同步标签及标签成员数据到本地 MySQL。
 * 
 * 使用方式：
 *   php src/sync/SyncTags.php
 * 
 * 定时任务（cron）：
 *   # 每天凌晨 3 点同步标签
 *   0 3 * * * php /www/sites/qywx.example.com/index/src/sync/SyncTags.php >> /tmp/sync_tags.log 2>&1
 */
namespace Wxwork\Sync;

require __DIR__ . '/../../vendor/autoload.php';

use Wxwork\Wxwork;
use Wxwork\Models\TagModel;

// ===================== 初始化 =====================
try {
    $wx = Wxwork::create();
    $db = $wx->getDatabase();
    echo "[SyncTags] === 标签同步开始 === " . date('Y-m-d H:i:s') . "\n";

    // ===================== 1. 获取标签列表 =====================
    echo "[SyncTags] 获取标签列表...\n";
    $tagResult = $wx->get('/cgi-bin/tag/list');

    if (($tagResult['errcode'] ?? -1) !== 0) {
        throw new \RuntimeException("获取标签列表失败: " . ($tagResult['errmsg'] ?? 'unknown'));
    }

    $tags = $tagResult['taglist'] ?? [];
    echo "[SyncTags] 获取到 " . count($tags) . " 个标签\n";

    // ===================== 2. 同步标签基本信息 =====================
    $syncedTagIds = [];
    foreach ($tags as $tag) {
        try {
            TagModel::upsertFromApi($tag);
            $syncedTagIds[] = $tag['tagid'];
        } catch (\Throwable $e) {
            echo "[SyncTags] 标签 {$tag['tagid']} 同步失败: {$e->getMessage()}\n";
        }
    }

    // ===================== 3. 标记已删除的标签 =====================
    if (!empty($syncedTagIds)) {
        $placeholders = implode(',', array_fill(0, count($syncedTagIds), '?'));
        $db->execute(
            "UPDATE wxwork_tag SET is_deleted = 1, updated_at = NOW() 
             WHERE tag_id NOT IN ({$placeholders}) AND is_deleted = 0",
            $syncedTagIds
        );
    }

    // ===================== 4. 同步标签成员 =====================
    echo "[SyncTags] 同步标签成员...\n";
    foreach ($syncedTagIds as $tagId) {
        try {
            $tagDetail = $wx->get('/cgi-bin/tag/get', ['tagid' => $tagId]);

            if (($tagDetail['errcode'] ?? -1) !== 0) {
                echo "[SyncTags] 获取标签 {$tagId} 成员失败: " . ($tagDetail['errmsg'] ?? '') . "\n";
                continue;
            }

            $userlist = $tagDetail['userlist'] ?? [];
            $partylist = $tagDetail['partylist'] ?? [];

            // 清空旧关联
            TagModel::clearMembers($tagId);

            // 插入成员关联
            if (!empty($userlist)) {
                $userids = array_map(function ($u) {
                    return is_string($u) ? $u : ($u['userid'] ?? '');
                }, $userlist);
                TagModel::addMembers($tagId, array_filter($userids));
            }

            echo "[SyncTags] 标签 {$tagId} 同步完成: " . count($userlist) . " 个成员\n";

        } catch (\Throwable $e) {
            echo "[SyncTags] 标签 {$tagId} 成员同步失败: {$e->getMessage()}\n";
        }
    }

    echo "[SyncTags] === 标签同步完成 === " . date('Y-m-d H:i:s') . "\n";

} catch (\Throwable $e) {
    echo "[SyncTags] 同步失败: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

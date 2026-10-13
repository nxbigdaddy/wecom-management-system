<?php
/**
 * 日志清理脚本
 * 
 * 清理过期的回调日志、API 请求日志和消息发送记录。
 * 
 * 使用方式：
 *   php src/sync/CleanLogs.php
 * 
 * 或指定保留天数：
 *   php src/sync/CleanLogs.php 60
 */
namespace Wxwork\Sync;

require __DIR__ . '/../../vendor/autoload.php';

use Wxwork\Wxwork;

// ===================== 配置 =====================
$retainDays = (int)($argv[1] ?? 90);  // 默认保留 90 天
$minDays = 30;
if ($retainDays < $minDays) {
    echo "[CleanLogs] 保留天数不能少于 {$minDays} 天，已调整为 {$minDays}\n";
    $retainDays = $minDays;
}

// ===================== 初始化 =====================
try {
    $wx = Wxwork::create();
    $db = $wx->getDatabase();

    echo "[CleanLogs] === 日志清理开始 === " . date('Y-m-d H:i:s') . "\n";
    echo "[CleanLogs] 保留最近 {$retainDays} 天的记录\n";

    $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retainDays} days"));

    // ===================== 清理回调日志 =====================
    $callbackCount = $db->execute(
        'DELETE FROM wxwork_callback_log WHERE created_at < ?',
        [$cutoffDate]
    );
    echo "[CleanLogs] 清理回调日志: {$callbackCount} 条\n";

    // ===================== 清理 API 请求日志 =====================
    $apiCount = $db->execute(
        'DELETE FROM wxwork_api_log WHERE created_at < ?',
        [$cutoffDate]
    );
    echo "[CleanLogs] 清理 API 日志: {$apiCount} 条\n";

    // ===================== 清理消息发送记录 =====================
    $msgCount = $db->execute(
        'DELETE FROM wxwork_message_log WHERE created_at < ?',
        [$cutoffDate]
    );
    echo "[CleanLogs] 清理消息记录: {$msgCount} 条\n";

    // ===================== 清理过期的临时素材 =====================
    $mediaCount = $db->execute(
        "UPDATE wxwork_media SET is_permanent = 0 WHERE is_permanent = 0 AND expires_at IS NOT NULL AND expires_at < NOW()"
    );
    echo "[CleanLogs] 标记过期素材: {$mediaCount} 条\n";

    // ===================== 优化表 =====================
    $tables = ['wxwork_callback_log', 'wxwork_api_log', 'wxwork_message_log'];
    foreach ($tables as $table) {
        try {
            $db->execute("OPTIMIZE TABLE {$table}");
            echo "[CleanLogs] 优化表: {$table}\n";
        } catch (\Throwable $e) {
            echo "[CleanLogs] 优化表 {$table} 跳过: {$e->getMessage()}\n";
        }
    }

    echo "[CleanLogs] === 日志清理完成 === " . date('Y-m-d H:i:s') . "\n";

} catch (\Throwable $e) {
    echo "[CleanLogs] 清理失败: {$e->getMessage()}\n";
    exit(1);
}

<?php
/**
 * 默认回调处理器（兜底）
 * 
 * 所有未注册精确处理器的事件都会经过此处理器。
 * 仅做日志记录，不做业务处理。
 */
namespace Wxwork\Callback\Handlers;

use Wxwork\Callback\HandlerInterface;
use Wxwork\Core\Database;

class DefaultHandler implements HandlerInterface
{
    public function handle(array $event): string
    {
        $eventType = $event['Event'] ?? $event['MsgType'] ?? 'unknown';
        $changeType = $event['ChangeType'] ?? '';

        // 仅记录日志，不修改数据库
        $logMsg = sprintf(
            '[DefaultHandler] 未分类事件: type=%s, change=%s, keys=%s',
            $eventType,
            $changeType,
            implode(',', array_keys($event))
        );
        error_log($logMsg);

        // 可选：将未匹配事件写入数据库（方便后续分析）
        try {
            $db = Database::getInstance();
            $db->execute(
                'INSERT INTO wxwork_callback_log 
                 (event_type, change_type, parsed_data, handler, handle_result, handle_time_ms)
                 VALUES (?, ?, ?, ?, ?, 0)',
                [
                    mb_substr($eventType, 0, 50),
                    mb_substr($changeType, 0, 50),
                    json_encode($event, JSON_UNESCAPED_UNICODE),
                    'DefaultHandler',
                    'success',
                ]
            );
        } catch (\Throwable $e) {
            error_log('[DefaultHandler] DB log failed: ' . $e->getMessage());
        }

        return 'success';
    }
}

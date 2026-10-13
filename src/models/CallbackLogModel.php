<?php
namespace Wxwork\Models;

/**
 * 回调日志 Model
 * 
 * 对应表：wxwork_callback_log
 */
class CallbackLogModel extends BaseModel
{
    protected static string $table = 'wxwork_callback_log';
    protected static string $primaryKey = 'id';

    /** 记录回调日志 */
    public static function log(array $data): int
    {
        // 截断过长的文本字段
        $rawXml = $data['raw_xml'] ?? '';
        if (mb_strlen($rawXml) > 16384) {
            $rawXml = mb_substr($rawXml, 0, 16384) . '...[truncated]';
        }
        $decryptedXml = $data['decrypted_xml'] ?? '';
        if (mb_strlen($decryptedXml) > 16384) {
            $decryptedXml = mb_substr($decryptedXml, 0, 16384) . '...[truncated]';
        }

        return (int)static::db()->insertRow('wxwork_callback_log', [
            'event_type'     => mb_substr($data['event_type'] ?? '', 0, 50),
            'change_type'    => mb_substr($data['change_type'] ?? '', 0, 50),
            'raw_xml'        => $rawXml,
            'decrypted_xml'  => $decryptedXml,
            'parsed_data'    => isset($data['parsed_data']) ? json_encode($data['parsed_data'], JSON_UNESCAPED_UNICODE) : '{}',
            'handler'        => mb_substr($data['handler'] ?? '', 0, 200),
            'handle_result'  => $data['handle_result'] ?? 'success',
            'handle_time_ms' => (int)($data['handle_time_ms'] ?? 0),
            'error_msg'      => mb_substr($data['error_msg'] ?? '', 0, 2000),
        ]);
    }

    /** 记录错误日志 */
    public static function logError(string $eventType, string $errorMsg, int $elapsedMs = 0, string $rawData = ''): int
    {
        return self::log([
            'event_type'     => $eventType,
            'change_type'    => '',
            'raw_xml'        => $rawData,
            'decrypted_xml'  => '',
            'parsed_data'    => [],
            'handler'        => '',
            'handle_result'  => 'error',
            'handle_time_ms' => $elapsedMs,
            'error_msg'      => $errorMsg,
        ]);
    }

    /** 获取回调历史 */
    public static function getHistory(int $page = 1, int $size = 20, string $eventType = ''): array
    {
        $where = '1=1';
        $params = [];
        if ($eventType) {
            $where .= ' AND event_type = ?';
            $params[] = $eventType;
        }
        $total = (int)(static::db()->selectValue(
            "SELECT COUNT(*) FROM wxwork_callback_log WHERE {$where}",
            $params
        ) ?? 0);

        $rows = static::db()->select(
            "SELECT * FROM wxwork_callback_log WHERE {$where} ORDER BY id DESC LIMIT ?, ?",
            array_merge($params, [($page - 1) * $size, $size])
        );

        return ['total' => $total, 'page' => $page, 'size' => $size, 'rows' => $rows];
    }

    /** 获取事件类型统计 */
    public static function getEventStats(int $days = 7): array
    {
        return static::db()->select(
            'SELECT event_type, COUNT(*) as cnt 
             FROM wxwork_callback_log 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY event_type ORDER BY cnt DESC',
            [$days]
        );
    }

    /** 清理旧日志 */
    public static function cleanOld(int $days = 90): int
    {
        return static::db()->execute(
            'DELETE FROM wxwork_callback_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$days]
        );
    }
}

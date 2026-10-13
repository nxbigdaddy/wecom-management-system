<?php
namespace Wxwork\Models;

/**
 * 消息发送记录 Model
 * 
 * 对应表：wxwork_message_log, wxwork_message_template
 */
class MessageLogModel extends BaseModel
{
    protected static string $table = 'wxwork_message_log';
    protected static string $primaryKey = 'id';

    /** 记录发送日志 */
    public static function log(array $data): int
    {
        return (int)static::db()->insertRow('wxwork_message_log', [
            'msg_type'               => $data['msg_type'] ?? 'text',
            'to_users'               => $data['to_users'] ?? '',
            'to_party'               => $data['to_party'] ?? '',
            'to_tag'                 => $data['to_tag'] ?? '',
            'content'                => isset($data['content']) ? json_encode($data['content'], JSON_UNESCAPED_UNICODE) : '{}',
            'safe'                   => (int)($data['safe'] ?? 0),
            'enable_id_trans'        => (int)($data['enable_id_trans'] ?? 0),
            'enable_duplicate_check' => (int)($data['enable_duplicate_check'] ?? 0),
            'msg_id'                 => $data['msg_id'] ?? '',
            'errcode'                => (int)($data['errcode'] ?? 0),
            'errmsg'                 => $data['errmsg'] ?? '',
            'status'                 => (int)($data['status'] ?? 0),
            'sent_by'                => $data['sent_by'] ?? '',
        ]);
    }

    /** 获取发送历史 */
    public static function getHistory(int $page = 1, int $size = 20, string $keyword = ''): array
    {
        $where = '1=1';
        $params = [];
        if ($keyword) {
            $where .= ' AND (to_users LIKE ? OR errmsg LIKE ?)';
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }
        return static::db()->select(
            "SELECT * FROM wxwork_message_log WHERE {$where} ORDER BY id DESC LIMIT ?, ?",
            array_merge($params, [($page - 1) * $size, $size])
        );
    }

    /** 获取今日发送数 */
    public static function getTodayCount(): int
    {
        return (int)(static::db()->selectValue(
            "SELECT COUNT(*) FROM wxwork_message_log WHERE DATE(created_at) = CURDATE()"
        ) ?? 0);
    }

    // ==================== 消息模板 ====================

    /** 获取所有模板 */
    public static function getTemplates(): array
    {
        return static::db()->select(
            'SELECT * FROM wxwork_message_template WHERE is_enabled = 1 ORDER BY id DESC'
        );
    }

    /** 保存模板 */
    public static function saveTemplate(array $data): int
    {
        // 检查是否重名
        $exists = static::db()->selectValue(
            'SELECT id FROM wxwork_message_template WHERE tpl_name = ? AND is_enabled = 1',
            [$data['tpl_name']]
        );

        if ($exists) {
            return (int)static::db()->updateRow(
                'wxwork_message_template',
                [
                    'tpl_desc'  => $data['tpl_desc'] ?? '',
                    'msg_type'  => $data['msg_type'] ?? 'text',
                    'content'   => isset($data['content']) ? json_encode($data['content'], JSON_UNESCAPED_UNICODE) : '{}',
                    'variables' => isset($data['variables']) ? json_encode($data['variables'], JSON_UNESCAPED_UNICODE) : '[]',
                ],
                'id = ?',
                [(int)$exists]
            );
        }

        return (int)static::db()->insertRow('wxwork_message_template', [
            'tpl_name'  => $data['tpl_name'],
            'tpl_desc'  => $data['tpl_desc'] ?? '',
            'msg_type'  => $data['msg_type'] ?? 'text',
            'content'   => isset($data['content']) ? json_encode($data['content'], JSON_UNESCAPED_UNICODE) : '{}',
            'variables' => isset($data['variables']) ? json_encode($data['variables'], JSON_UNESCAPED_UNICODE) : '[]',
            'is_enabled' => 1,
        ]);
    }

    /** 获取单个模板 */
    public static function getTemplate(int $id): ?array
    {
        return static::db()->selectOne(
            'SELECT * FROM wxwork_message_template WHERE id = ? AND is_enabled = 1',
            [$id]
        );
    }

    /** 删除模板（软删除） */
    public static function deleteTemplate(int $id): void
    {
        static::db()->execute(
            'UPDATE wxwork_message_template SET is_enabled = 0 WHERE id = ?',
            [$id]
        );
    }
}

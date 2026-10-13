<?php
/**
 * 审批状态变更事件处理器
 * 
 * 接收企业微信审批状态变更回调，记录审批单号变化。
 * 适用于：审批申请提交、审批通过、审批驳回等场景。
 */
namespace Wxwork\Callback\Handlers;

use Wxwork\Callback\HandlerInterface;
use Wxwork\Core\Database;

class ApprovalEvent implements HandlerInterface
{
    public function handle(array $event): string
    {
        $changeType = $event['ChangeType'] ?? '';
        $spNo = $event['SpNo'] ?? '';          // 审批单号
        $templateId = $event['TemplateId'] ?? ''; // 模板ID
        $status = (int)($event['ApprovalStatus'] ?? 0); // 1审批中 2已通过 3已驳回 4已撤销

        $statusMap = [
            1 => '审批中',
            2 => '已通过',
            3 => '已驳回',
            4 => '已撤销',
        ];

        try {
            $db = Database::getInstance();

            // 记录审批变更到回调日志
            $db->execute(
                'INSERT INTO wxwork_callback_log 
                 (event_type, change_type, parsed_data, handler, handle_result, handle_time_ms)
                 VALUES (?, ?, ?, ?, ?, 0)',
                [
                    $event['Event'] ?? 'sys_approval_change',
                    $changeType,
                    json_encode($event, JSON_UNESCAPED_UNICODE),
                    'ApprovalEvent',
                    'success',
                ]
            );

            // 可根据业务需要同步审批模板或审批单信息
            // 例如：更新 wxwork_approval_template 表中的缓存
            if ($changeType === 'add_template' || $changeType === 'modify_template') {
                // 审批模板变更，后续可通过 API 拉取最新模板
                error_log("[ApprovalEvent] Template changed: {$templateId}, type={$changeType}");
            }

            // 审批单状态变更
            if (in_array($changeType, ['status_change', 'apply', 'sp_pass', 'sp_reject', 'sp_recall'])) {
                $statusText = $statusMap[$status] ?? "状态{$status}";
                error_log("[ApprovalEvent] 审批单 {$spNo} {$statusText}, 模板={$templateId}");
            }

        } catch (\Throwable $e) {
            error_log('[ApprovalEvent] Error: ' . $e->getMessage());
        }

        return 'success';
    }
}

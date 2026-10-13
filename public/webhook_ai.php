<?php
/**
 * AI Webhook 回调端点
 * 
 * 接收外部 AI 服务（如 Claude Code、OpenAI）的处理结果，
 * 自动写入对应的智能表格。
 * 
 * POST /webhook/ai
 * Content-Type: application/json
 * 
 * 请求体：
 * {
 *   "token": "your_webhook_token",
 *   "docid": "目标文档ID",
 *   "sheet_id": "目标子表ID",
 *   "records": [
 *     {"values": {"字段名": [{"type":"text","text":"值"}]}}
 *   ]
 * }
 */

// 自动加载
require __DIR__ . '/../../vendor/autoload.php';

use Wxwork\Wxwork;

header('Content-Type: application/json; charset=utf-8');

// ===== 验证请求 =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['code' => 405, 'error' => '仅支持 POST 请求']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'error' => '请求体必须是有效的 JSON']);
    exit;
}

// ===== 验证 Token =====
$configFile = __DIR__ . '/../../config.php';
$baseConfig = file_exists($configFile) ? require $configFile : [];
$config = new \Wxwork\Core\Config($baseConfig);
$webhookToken = $config->get('webhook_token', '');

$requestToken = $input['token'] ?? '';
if ($webhookToken && $requestToken !== $webhookToken) {
    http_response_code(403);
    echo json_encode(['code' => 403, 'error' => 'Token 验证失败']);
    exit;
}

// ===== 处理请求 =====
try {
    $wx = Wxwork::create();
    $docid = $input['docid'] ?? '';
    $sheetId = $input['sheet_id'] ?? '';
    $records = $input['records'] ?? [];

    if (empty($docid) || empty($sheetId) || empty($records)) {
        throw new \RuntimeException('缺少必填字段：docid, sheet_id, records');
    }

    // 批量写入记录（最多一次100条）
    $totalRecords = count($records);
    $written = 0;
    $errors = [];

    foreach (array_chunk($records, 100) as $batch) {
        try {
            $result = $wx->smartsheet->addRecords($docid, $sheetId, $batch);
            $written += count($batch);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    // 记录到日志
    try {
        $db = $wx->getDatabase();
        $db->insertRow('wxwork_callback_log', [
            'event_type' => 'ai_webhook',
            'change_type' => 'post_records',
            'parsed_data' => json_encode(['docid'=>$docid,'sheet_id'=>$sheetId,'total'=>$totalRecords,'written'=>$written], JSON_UNESCAPED_UNICODE),
            'handler' => 'AI Webhook',
            'handle_result' => empty($errors) ? 'success' : 'partial',
            'handle_time_ms' => 0,
            'error_msg' => implode('; ', $errors),
        ]);
    } catch (\Throwable $e) {}

    echo json_encode([
        'code' => 0,
        'message' => "成功写入 {$written}/{$totalRecords} 条记录",
        'data' => ['docid' => $docid, 'written' => $written, 'total' => $totalRecords],
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['code' => 500, 'error' => $e->getMessage()]);
}

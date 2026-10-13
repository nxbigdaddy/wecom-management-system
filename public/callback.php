<?php
/**
 * 企业微信回调入口
 * 
 * 在企业管理后台配置回调 URL：
 *   https://your-domain.com/callback.php
 * 
 * 建议配置：
 *   - URL: https://your-domain.com/callback.php
 *   - Token: 与 config.php 中的 token 一致
 *   - EncodingAESKey: 与 config.php 中的 encoding_aes_key 一致
 * 
 * 企业微信群机器人 Webhook 不需要经过此文件。
 */

// ===================== 自动加载 =====================
require __DIR__ . '/../vendor/autoload.php';

use Wxwork\Wxwork;
use Wxwork\Core\CryptException;

// ===================== 初始化 =====================
try {
    $wx = Wxwork::create();

    // ===================== 注册事件处理器 =====================

    // 通讯录变更事件
    $wx->callback->on('change_contact', \Wxwork\Callback\Handlers\ChangeContact::class);

    // 客户联系变更事件
    $wx->callback->on('change_external_contact', \Wxwork\Callback\Handlers\ChangeExternal::class);

    // 审批状态变更事件（可按需取消注释）
    // $wx->callback->on('sys_approval_change', function($event) use ($wx) {
    //     $logger = $wx->getLogger();
    //     $logger->info('审批事件', $event);
    //     return 'success';
    // });

    // 自定义事件处理示例（可在此添加业务逻辑）
    $wx->callback->on('*', function($event) use ($wx) {
        // 兜底处理器：记录所有未匹配的事件
        $logger = $wx->getLogger();
        $logger->info('未分类回调事件', [
            'event'    => $event['Event'] ?? '',
            'msg_type' => $event['MsgType'] ?? '',
            'change_type' => $event['ChangeType'] ?? '',
        ]);
        return 'success';
    });

    // ===================== 启动回调服务 =====================
    $wx->callback->serve();

} catch (CryptException $e) {
    // 加解密错误（签名验证失败等）
    http_response_code(403);
    echo 'Forbidden';
    // 可记录日志
    error_log('WxWork Callback Error: ' . $e->getMessage());

} catch (\Throwable $e) {
    // 其他未预期错误
    http_response_code(500);
    echo 'Internal Server Error';
    error_log('WxWork Callback Fatal: ' . $e->getMessage());
}

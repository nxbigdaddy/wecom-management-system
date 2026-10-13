<?php
/**
 * 回调服务器
 * 
 * 处理企业微信的三类请求：
 * 1. GET  请求 → URL 验证（首次配置回调 URL）
 * 2. POST 请求 → 事件推送（通讯录变更、客户变更等）
 * 
 * 使用方式：
 *   $wx->callback->on('change_contact', ChangeContact::class);
 *   $wx->callback->serve();
 */

namespace Wxwork\Callback;

use Wxwork\Wxwork;
use Wxwork\Core\Crypt;
use Wxwork\Core\CryptException;

class Server
{
    /** @var Wxwork */
    private Wxwork $wx;

    /** @var Crypt */
    private Crypt $crypt;

    /** @var EventDispatcher */
    private EventDispatcher $dispatcher;

    /** @var bool 是否已处理过（同一请求只处理一次） */
    private bool $handled = false;

    public function __construct(Wxwork $wx)
    {
        $this->wx = $wx;
        $this->crypt = $wx->getCrypt();
        $this->dispatcher = new EventDispatcher($wx);
    }

    /**
     * 注册事件处理器
     * 
     * @param string          $eventType 事件类型（'change_contact', 'change_external_contact', '*' 等）
     * @param callable|string $handler   处理器（闭包或 HandlerInterface 实现类的类名）
     * @return self
     */
    public function on(string $eventType, callable|string $handler): self
    {
        $this->dispatcher->register($eventType, $handler);
        return $this;
    }

    /**
     * 启动回调服务（入口方法）
     * 
     * 根据 HTTP 方法自动分发：
     * - GET  → verifyUrl()
     * - POST → handleEvent()
     */
    public function serve(): void
    {
        if ($this->handled) {
            return;
        }
        $this->handled = true;

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        switch ($method) {
            case 'GET':
                $this->verifyUrl();
                break;
            case 'POST':
                $this->handleEvent();
                break;
            default:
                http_response_code(405);
                echo 'Method Not Allowed';
        }
    }

    /**
     * URL 验证（GET 请求）
     * 
     * 企业微信首次配置回调 URL 时会发送 GET 请求：
     *   callback.php?msg_signature=xxx&timestamp=xxx&nonce=xxx&echostr=xxx
     * 
     * 服务器需要解密 echostr 并返回明文。
     */
    public function verifyUrl(): void
    {
        try {
            $msgSignature = $_GET['msg_signature'] ?? '';
            $timestamp    = $_GET['timestamp'] ?? '';
            $nonce        = $_GET['nonce'] ?? '';
            $echostr      = $_GET['echostr'] ?? '';

            if (empty($msgSignature) || empty($timestamp) || empty($nonce) || empty($echostr)) {
                http_response_code(400);
                echo 'Missing parameters';
                return;
            }

            $result = $this->crypt->verifyUrl($msgSignature, $timestamp, $nonce, $echostr);

            header('Content-Type: text/plain; charset=utf-8');
            echo $result;

        } catch (CryptException $e) {
            http_response_code(403);
            echo 'Verify failed: ' . $e->getMessage();
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Internal error';
        }
    }

    /**
     * 处理事件推送（POST 请求）
     * 
     * 企业微信 POST 加密的 XML，解密后按事件类型分发处理。
     * 必须在 5 秒内返回 "success"，否则企业微信会重试。
     */
    public function handleEvent(): void
    {
        $startTime = microtime(true);

        try {
            // 1. 获取请求参数
            $msgSignature = $_GET['msg_signature'] ?? '';
            $timestamp    = $_GET['timestamp'] ?? '';
            $nonce        = $_GET['nonce'] ?? '';

            // 2. 获取请求体（原始 XML）
            $postData = file_get_contents('php://input');
            if (empty($postData)) {
                echo 'success'; // 空请求静默处理
                return;
            }

            // 3. 解密消息
            $result = $this->crypt->decryptMsg($msgSignature, $timestamp, $nonce, $postData);
            $xmlStr = $result['xml'];

            // 4. 解析 XML → 数组
            $eventData = $this->parseXml($xmlStr);
            $eventType = $eventData['Event'] ?? $eventData['MsgType'] ?? 'unknown';

            // 5. 分发事件
            $handleResult = $this->dispatcher->dispatch($eventType, $eventData);

            // 6. 记录回调日志
            $this->logCallback($eventType, $postData, $xmlStr, $eventData, $handleResult, $startTime);

        } catch (CryptException $e) {
            // 解密失败：记录错误但不暴露细节，返回 success 避免企业微信反复重试
            $this->logCallbackError('decrypt_error', $e->getMessage(), $startTime);
        } catch (\Throwable $e) {
            // 处理异常
            $this->logCallbackError('handler_error', $e->getMessage(), $startTime);
        }

        // 无论成功失败，都返回 "success" 让企业微信不再重试
        // （除非是需要回复消息的场景）
        echo 'success';
    }

    /**
     * 解析 XML 为关联数组
     */
    private function parseXml(string $xmlStr): array
    {
        // 禁止外部实体注入
        libxml_disable_entity_loader(true);
        $xml = simplexml_load_string($xmlStr, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            throw new \RuntimeException('XML 解析失败');
        }

        $data = [];
        foreach ($xml->children() as $child) {
            $data[$child->getName()] = (string)$child;
        }

        // 特殊处理：Department 字段可能是逗号分隔的多个部门ID
        if (isset($data['Department']) && str_contains($data['Department'], ',')) {
            // 保持字符串格式，业务层自行处理
        }

        return $data;
    }

    /**
     * 记录回调日志到数据库
     */
    private function logCallback(string $eventType, string $rawXml, string $decryptedXml, array $eventData, string $handleResult, float $startTime): void
    {
        $elapsed = (int)((microtime(true) - $startTime) * 1000);

        try {
            $db = $this->wx->getDatabase();

            // 截断过长的 XML
            if (mb_strlen($rawXml) > 16384) {
                $rawXml = mb_substr($rawXml, 0, 16384) . '...[truncated]';
            }
            if (mb_strlen($decryptedXml) > 16384) {
                $decryptedXml = mb_substr($decryptedXml, 0, 16384) . '...[truncated]';
            }

            $db->insertRow('wxwork_callback_log', [
                'event_type'     => mb_substr($eventType, 0, 50),
                'change_type'    => mb_substr($eventData['ChangeType'] ?? '', 0, 50),
                'raw_xml'        => $rawXml,
                'decrypted_xml'  => $decryptedXml,
                'parsed_data'    => json_encode($eventData, JSON_UNESCAPED_UNICODE),
                'handler'        => '',
                'handle_result'  => $handleResult,
                'handle_time_ms' => $elapsed,
                'error_msg'      => '',
            ]);
        } catch (\Throwable $e) {
            // 日志记录失败不影响主流程
            $logger = $this->wx->getLogger();
            $logger->error('回调日志入库失败: ' . $e->getMessage());
        }
    }

    /**
     * 记录回调错误日志
     */
    private function logCallbackError(string $errorType, string $errorMsg, float $startTime): void
    {
        $elapsed = (int)((microtime(true) - $startTime) * 1000);

        try {
            $db = $this->wx->getDatabase();
            $db->insertRow('wxwork_callback_log', [
                'event_type'     => $errorType,
                'change_type'    => '',
                'raw_xml'        => mb_substr(file_get_contents('php://input') ?: '', 0, 16384),
                'decrypted_xml'  => '',
                'parsed_data'    => '{}',
                'handler'        => '',
                'handle_result'  => 'error',
                'handle_time_ms' => $elapsed,
                'error_msg'      => mb_substr($errorMsg, 0, 2000),
            ]);
        } catch (\Throwable $e) {
            // 静默
        }

        $logger = $this->wx->getLogger();
        $logger->error("回调{$errorType}: {$errorMsg}");
    }
}

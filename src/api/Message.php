<?php
namespace Wxwork\Api;

/**
 * 消息推送 API 模块
 * 
 * 覆盖：应用消息、群机器人Webhook、撤回消息
 */
class Message extends BaseApi
{
    // ==================== 通用发送方法 ====================

    /**
     * 底层发送方法
     * 
     * @param string $msgType  消息类型：text/image/voice/video/file/textcard/news/mpnews/miniprogram_notice/taskcard/template_card
     * @param array  $receiver 接收人 ['touser'=>'', 'toparty'=>'', 'totag'=>'']
     * @param array  $content  消息内容（按类型不同）
     * @param array  $options  扩展选项 ['safe'=>0, 'enable_id_trans'=>0, 'enable_duplicate_check'=>0, 'duplicate_check_interval'=>1800]
     */
    public function send(string $msgType, array $receiver, array $content, array $options = []): array
    {
        $data = [
            'touser'  => $receiver['touser'] ?? '',
            'toparty' => $receiver['toparty'] ?? '',
            'totag'   => $receiver['totag'] ?? '',
            'agentid' => $this->config->getAgentId(),
            'msgtype' => $msgType,
            $msgType  => $content,
        ];

        // 合并扩展选项
        foreach ($options as $key => $value) {
            $data[$key] = $value;
        }

        return $this->result($this->post('/cgi-bin/message/send', $data));
    }

    // ==================== 应用消息 ====================

    /** 发送文本消息 */
    public function sendText(array $options): array
    {
        $receiver = [
            'touser'  => $options['touser'] ?? '',
            'toparty' => $options['toparty'] ?? '',
            'totag'   => $options['totag'] ?? '',
        ];
        $content = ['content' => $options['content'] ?? ''];
        $extra = array_diff_key($options, $receiver, ['content' => '']);
        return $this->send('text', $receiver, $content, $extra);
    }

    /** 发送图片消息 */
    public function sendImage(array $options): array
    {
        $receiver = [
            'touser'  => $options['touser'] ?? '',
            'toparty' => $options['toparty'] ?? '',
            'totag'   => $options['totag'] ?? '',
        ];
        $content = ['media_id' => $options['media_id'] ?? ''];
        return $this->send('image', $receiver, $content);
    }

    /** 发送语音消息 */
    public function sendVoice(array $options): array
    {
        $receiver = [
            'touser'  => $options['touser'] ?? '',
            'toparty' => $options['toparty'] ?? '',
            'totag'   => $options['totag'] ?? '',
        ];
        $content = ['media_id' => $options['media_id'] ?? ''];
        return $this->send('voice', $receiver, $content);
    }

    /** 发送视频消息 */
    public function sendVideo(array $options): array
    {
        $receiver = [
            'touser'  => $options['touser'] ?? '',
            'toparty' => $options['toparty'] ?? '',
            'totag'   => $options['totag'] ?? '',
        ];
        $content = [
            'media_id'    => $options['media_id'] ?? '',
            'title'       => $options['title'] ?? '',
            'description' => $options['description'] ?? '',
        ];
        return $this->send('video', $receiver, $content);
    }

    /** 发送文件消息 */
    public function sendFile(array $options): array
    {
        $receiver = [
            'touser'  => $options['touser'] ?? '',
            'toparty' => $options['toparty'] ?? '',
            'totag'   => $options['totag'] ?? '',
        ];
        $content = ['media_id' => $options['media_id'] ?? ''];
        return $this->send('file', $receiver, $content);
    }

    /** 发送文本卡片消息 */
    public function sendTextCard(array $options): array
    {
        $receiver = [
            'touser'  => $options['touser'] ?? '',
            'toparty' => $options['toparty'] ?? '',
            'totag'   => $options['totag'] ?? '',
        ];
        $content = [
            'title'       => $options['title'] ?? '',
            'description' => $options['description'] ?? '',
            'url'         => $options['url'] ?? '',
            'btntxt'      => $options['btntxt'] ?? '详情',
        ];
        return $this->send('textcard', $receiver, $content);
    }

    /** 发送图文消息 */
    public function sendNews(array $options): array
    {
        $receiver = [
            'touser'  => $options['touser'] ?? '',
            'toparty' => $options['toparty'] ?? '',
            'totag'   => $options['totag'] ?? '',
        ];
        $content = ['articles' => $options['articles'] ?? []];
        return $this->send('news', $receiver, $content);
    }

    /** 发送图文消息(mpnews) */
    public function sendMpNews(array $options): array
    {
        $receiver = [
            'touser'  => $options['touser'] ?? '',
            'toparty' => $options['toparty'] ?? '',
            'totag'   => $options['totag'] ?? '',
        ];
        $content = ['articles' => $options['articles'] ?? []];
        return $this->send('mpnews', $receiver, $content);
    }

    /** 发送小程序通知消息 */
    public function sendMiniProgramNotice(array $options): array
    {
        $receiver = [
            'touser'  => $options['touser'] ?? '',
            'toparty' => $options['toparty'] ?? '',
            'totag'   => $options['totag'] ?? '',
        ];
        $content = [
            'appid'         => $options['appid'] ?? '',
            'page'          => $options['page'] ?? '',
            'title'         => $options['title'] ?? '',
            'description'   => $options['description'] ?? '',
            'content_item'  => $options['content_item'] ?? [],
            'emphasis_first_item' => $options['emphasis_first_item'] ?? false,
        ];
        return $this->send('miniprogram_notice', $receiver, $content);
    }

    /** 发送任务卡片消息 */
    public function sendTaskCard(array $options): array
    {
        $receiver = [
            'touser'  => $options['touser'] ?? '',
            'toparty' => $options['toparty'] ?? '',
            'totag'   => $options['totag'] ?? '',
        ];
        $content = [
            'title'       => $options['title'] ?? '',
            'description' => $options['description'] ?? '',
            'url'         => $options['url'] ?? '',
            'task_id'     => $options['task_id'] ?? '',
            'btn'         => $options['btn'] ?? [],
        ];
        return $this->send('taskcard', $receiver, $content);
    }

    /** 发送模板卡片消息 */
    public function sendTemplateCard(array $options): array
    {
        $receiver = [
            'touser'  => $options['touser'] ?? '',
            'toparty' => $options['toparty'] ?? '',
            'totag'   => $options['totag'] ?? '',
        ];
        return $this->send('template_card', $receiver, $options['template_card'] ?? []);
    }

    // ==================== 撤回消息 ====================

    /** 撤回应用消息 POST /cgi-bin/message/recall */
    public function recall(string $msgId): array
    {
        return $this->result($this->post('/cgi-bin/message/recall', ['msgid' => $msgId]));
    }

    // ==================== 群机器人 Webhook ====================

    /**
     * 通用 Webhook 发送
     */
    private function sendWebhook(string $webhookUrl, array $data): array
    {
        $response = $this->http->requestRaw($webhookUrl, 'POST', json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response;
    }

    /** Webhook 文本消息 */
    public function sendWebhookText(string $webhookUrl, string $content, array $mentioned = []): array
    {
        $data = [
            'msgtype' => 'text',
            'text' => [
                'content' => $content,
            ],
        ];
        if (!empty($mentioned)) {
            $data['text']['mentioned_list'] = $mentioned;
        }
        return $this->sendWebhook($webhookUrl, $data);
    }

    /** Webhook Markdown 消息 */
    public function sendWebhookMarkdown(string $webhookUrl, string $content): array
    {
        return $this->sendWebhook($webhookUrl, [
            'msgtype'  => 'markdown',
            'markdown' => ['content' => $content],
        ]);
    }

    /** Webhook 图片消息 */
    public function sendWebhookImage(string $webhookUrl, string $base64, string $md5): array
    {
        return $this->sendWebhook($webhookUrl, [
            'msgtype' => 'image',
            'image' => [
                'base64' => $base64,
                'md5'    => $md5,
            ],
        ]);
    }

    /** Webhook 图文消息 */
    public function sendWebhookNews(string $webhookUrl, array $articles): array
    {
        return $this->sendWebhook($webhookUrl, [
            'msgtype' => 'news',
            'news'    => ['articles' => $articles],
        ]);
    }
}

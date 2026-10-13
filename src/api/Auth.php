<?php
namespace Wxwork\Api;

/**
 * 身份认证 API 模块
 * 
 * 覆盖：OAuth2网页授权、扫码登录、JS-SDK签名
 */
class Auth extends BaseApi
{
    // ==================== OAuth2 网页授权 ====================

    /**
     * 构造 OAuth2 授权 URL
     * 
     * @param string $redirectUri 回调地址（需在企业微信后台设置可信域名）
     * @param string $state       自定义参数
     * @param string $scope       snsapi_base（静默）| snsapi_privateinfo（手动授权）
     */
    public function buildOAuthUrl(string $redirectUri, string $state = '', string $scope = 'snsapi_base'): string
    {
        $params = [
            'appid'         => $this->config->getCorpId(),
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => $scope,
            'state'         => $state,
            'agentid'       => $this->config->getAgentId(),
        ];
        return 'https://open.weixin.qq.com/connect/oauth2/authorize?' . http_build_query($params) . '#wechat_redirect';
    }

    /**
     * 通过 code 获取用户身份
     * 
     * @return array userid(企业成员)/openid(非企业成员)/deviceid/user_ticket
     */
    public function getUserInfo(string $code): array
    {
        return $this->result($this->get('/cgi-bin/auth/getuserinfo', ['code' => $code]));
    }

    /**
     * 获取用户详情（需 scope=snsapi_privateinfo）
     */
    public function getUserDetail(string $userTicket): array
    {
        return $this->result($this->post('/cgi-bin/auth/getuserdetail', ['user_ticket' => $userTicket]));
    }

    // ==================== 扫码登录 ====================

    /**
     * 构造扫码登录 URL
     */
    public function buildQrLoginUrl(string $redirectUri, string $state = ''): string
    {
        $params = [
            'appid'        => $this->config->getCorpId(),
            'agentid'      => $this->config->getAgentId(),
            'redirect_uri' => $redirectUri,
            'state'        => $state,
        ];
        return 'https://open.work.weixin.qq.com/wwopen/sso/qrConnect?' . http_build_query($params);
    }

    /**
     * 扫码登录获取身份（需服务商 provider_access_token）
     * 
     * @note 此接口需要服务商身份，此处仅做接口封装
     */
    public function getLoginInfo(string $authCode): array
    {
        // 注意：此接口需要 provider_access_token，普通 access_token 无法调用
        // 此处简化实现，实际需先获取 provider_token
        return $this->result($this->post('/cgi-bin/service/get_login_info', ['auth_code' => $authCode]));
    }

    // ==================== JS-SDK ====================

    /**
     * 获取 JS-SDK ticket
     */
    public function getJsApiTicket(): string
    {
        // 从缓存读取
        $ticket = $this->getTicketFromCache('jsapi_ticket');
        if ($ticket !== null) {
            return $ticket;
        }

        // 调 API 获取
        $result = $this->get('/cgi-bin/get_jsapi_ticket');
        if (($result['errcode'] ?? -1) !== 0) {
            throw new \Wxwork\Core\ApiException('获取 JS-SDK ticket 失败: ' . ($result['errmsg'] ?? ''), $result['errcode'] ?? -1);
        }

        $ticket = $result['ticket'];
        $this->setTicketToCache('jsapi_ticket', $ticket);
        return $ticket;
    }

    /**
     * 获取企业 JS-SDK ticket（agentConfig 用）
     */
    public function getAgentTicket(): string
    {
        // 从缓存读取
        $ticket = $this->getTicketFromCache('agent_ticket');
        if ($ticket !== null) {
            return $ticket;
        }

        // 调 API 获取
        $result = $this->get('/cgi-bin/ticket/get', ['type' => 'agent_config']);
        if (($result['errcode'] ?? -1) !== 0) {
            throw new \Wxwork\Core\ApiException('获取 agent_ticket 失败: ' . ($result['errmsg'] ?? ''), $result['errcode'] ?? -1);
        }

        $ticket = $result['ticket'];
        $this->setTicketToCache('agent_ticket', $ticket);
        return $ticket;
    }

    /**
     * 生成 JS-SDK 签名包
     * 
     * @param string $url 当前页面完整 URL
     * @return array ['appId'=>'', 'timestamp'=>, 'nonceStr'=>'', 'signature'=>'']
     */
    public function buildJsSign(string $url): array
    {
        $ticket = $this->getJsApiTicket();
        $nonceStr = $this->generateNonceStr();
        $timestamp = time();

        // 拼接签名串
        $signStr = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        $signature = sha1($signStr);

        return [
            'appId'     => $this->config->getCorpId(),
            'timestamp' => $timestamp,
            'nonceStr'  => $nonceStr,
            'signature' => $signature,
        ];
    }

    /**
     * 生成 agentConfig 签名包
     * 
     * @param string $url 当前页面完整 URL
     * @return array ['corpid'=>'', 'agentid'=>, 'timestamp'=>, 'nonceStr'=>'', 'signature'=>'']
     */
    public function buildAgentSign(string $url): array
    {
        $ticket = $this->getAgentTicket();
        $nonceStr = $this->generateNonceStr();
        $timestamp = time();

        // 拼接签名串
        $signStr = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
        $signature = sha1($signStr);

        return [
            'corpid'    => $this->config->getCorpId(),
            'agentid'   => $this->config->getAgentId(),
            'timestamp' => $timestamp,
            'nonceStr'  => $nonceStr,
            'signature' => $signature,
        ];
    }

    // ==================== 私有方法 ====================

    /**
     * 生成随机字符串
     */
    private function generateNonceStr(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $str;
    }

    /**
     * 从 Redis 缓存获取 ticket
     */
    private function getTicketFromCache(string $type): ?string
    {
        try {
            $redis = $this->wx->getRedis();
            if (!$redis->isAvailable()) {
                return null;
            }
            return $redis->get($type);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 缓存 ticket 到 Redis
     */
    private function setTicketToCache(string $type, string $ticket, int $ttl = 7000): void
    {
        try {
            $redis = $this->wx->getRedis();
            if ($redis->isAvailable()) {
                $redis->set($type, $ticket, $ttl);
            }
        } catch (\Throwable $e) {
            // 缓存失败不影响
        }
    }
}

<?php
/**
 * HTTP 请求客户端
 * 
 * 基于 cURL，提供：
 * - GET / POST / POST RAW / 文件上传
 * - 自动注入 access_token
 * - Token 过期自动刷新 + 重试
 * - 统一错误处理
 * - API 日志记录
 */

namespace Wxwork\Core;

class HttpClient
{
    /** @var Config */
    private Config $config;

    /** @var TokenManager */
    private TokenManager $tokenManager;

    /** @var int 超时秒数 */
    private int $timeout;

    /** @var int 重试次数 */
    private int $retryCount;

    /** @var Logger|null */
    private ?Logger $logger = null;

    /** @var Database|null 用于记录 API 日志 */
    private ?Database $db = null;

    /** @var array 默认请求头 */
    private array $defaultHeaders = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept'       => 'application/json',
    ];

    /**
     * @param Config       $config
     * @param TokenManager $tokenManager
     */
    public function __construct(Config $config, TokenManager $tokenManager)
    {
        $this->config = $config;
        $this->tokenManager = $tokenManager;
        $this->timeout = $config->getHttpTimeout();
        $this->retryCount = $config->getHttpRetry();
    }

    /**
     * 设置日志实例
     */
    public function setLogger(Logger $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 设置数据库实例（用于记录 API 日志）
     */
    public function setDatabase(Database $db): self
    {
        $this->db = $db;
        return $this;
    }

    /**
     * GET 请求
     * @param string $path      API 路径，如 '/cgi-bin/user/get'
     * @param array  $query     查询参数
     * @param bool   $needToken 是否附加 access_token
     * @return array
     */
    public function get(string $path, array $query = [], bool $needToken = true): array
    {
        $url = $this->buildUrl($path, $query, $needToken);
        return $this->request('GET', $url, null);
    }

    /**
     * POST 请求（JSON body）
     * @param string $path      API 路径
     * @param array  $data      请求体数据（会被 JSON 编码）
     * @param bool   $needToken 是否附加 access_token
     * @return array
     */
    public function post(string $path, array $data = [], bool $needToken = true): array
    {
        $url = $this->buildUrl($path, [], $needToken);
        $body = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : '{}';
        return $this->request('POST', $url, $body);
    }

    /**
     * POST 请求（原始 body）
     * @param string $path
     * @param string $body     原始请求体
     * @param bool   $needToken
     * @return array
     */
    public function postRaw(string $path, string $body, bool $needToken = true): array
    {
        $url = $this->buildUrl($path, [], $needToken);
        return $this->request('POST', $url, $body);
    }

    /**
     * 文件上传（multipart/form-data）
     * @param string $path        如 '/cgi-bin/media/upload'
     * @param string $filePath    本地文件路径
     * @param string $type        如 'image', 'voice', 'video', 'file'
     * @param array  $extraFields 额外字段
     * @return array
     */
    public function upload(string $path, string $filePath, string $type, array $extraFields = []): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("文件不存在: {$filePath}");
        }

        $url = $this->buildUrl($path, ['type' => $type], true);

        // multipart/form-data 构建
        $postFields = [];
        foreach ($extraFields as $key => $value) {
            $postFields[$key] = $value;
        }
        $postFields['media'] = new \CURLFile($filePath);

        return $this->request('UPLOAD', $url, $postFields);
    }

    /**
     * 发送请求到指定完整 URL（不自动加 token，用于 Webhook 等场景）
     * @param string $url    完整 URL
     * @param string $method
     * @param mixed  $body
     * @return array
     */
    public function requestRaw(string $url, string $method = 'POST', mixed $body = null): array
    {
        return $this->request($method, $url, $body, false);
    }

    /**
     * 设置超时
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * 设置重试次数
     */
    public function setRetry(int $times): self
    {
        $this->retryCount = $times;
        return $this;
    }

    /**
     * 核心请求方法
     * 
     * @param string $method    GET / POST / UPLOAD
     * @param string $url       完整 URL
     * @param mixed  $body      请求体（字符串/数组/CURLFile）
     * @param bool   $useJsonContentType POST 请求是否使用 JSON Content-Type
     * @return array
     * @throws ApiException
     */
    private function request(string $method, string $url, mixed $body = null, bool $useJsonContentType = true): array
    {
        $attempt = 0;
        $lastException = null;
        $maxAttempts = $this->retryCount + 1;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $startTime = microtime(true);

            try {
                $ch = curl_init();

                curl_setopt_array($ch, [
                    CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => $this->timeout,
                    CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_HEADER         => false,
                ]);

                // User-Agent
                $userAgent = $this->config->get('http_user_agent', 'WxworkManager/1.0');
                $headers = ["User-Agent: {$userAgent}"];

                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    if ($useJsonContentType) {
                        $headers[] = 'Content-Type: application/json; charset=utf-8';
                    }
                    curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE) : ($body ?? '{}'));
                } elseif ($method === 'UPLOAD') {
                    curl_setopt($ch, CURLOPT_POST, true);
                    // 上传使用 multipart，PHP 自动设置 Content-Type
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                    // 设置安全的 Content-Type，让 PHP 自动添加 boundary
                    unset($headers['Content-Type']);
                } elseif ($method === 'GET') {
                    curl_setopt($ch, CURLOPT_HTTPGET, true);
                } else {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                }

                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                $elapsedMs = (int)((microtime(true) - $startTime) * 1000);

                if ($curlError) {
                    $this->log('error', "cURL 错误: {$curlError} [{$method} {$url}]");
                    $lastException = new ApiException("网络请求失败: {$curlError}", -1);
                    continue; // 重试
                }

                $data = json_decode($response, true);

                if (!is_array($data)) {
                    $this->log('error', "响应解析失败 [{$method} {$url}]: " . substr($response, 0, 500));
                    $lastException = new ApiException("响应解析失败: HTTP {$httpCode}", -1);
                    continue;
                }

                $errcode = (int)($data['errcode'] ?? 0);
                $errmsg  = $data['errmsg'] ?? 'ok';

                // Token 过期（42001）或无效（40014）→ 刷新 token 重试
                if ($errcode === 42001 || $errcode === 40014) {
                    $this->log('info', "Token 过期/无效 (errcode={$errcode})，刷新后重试");
                    $this->tokenManager->clearCache();
                    // 重新构建 URL（获取新 token）
                    // 注意：此时需要重新构建 URL，因为 token 变了
                    // 我们通过清除缓存让 TokenManager 重新获取，然后重试整次请求
                    $parsedUrl = parse_url($url);
                    parse_str($parsedUrl['query'] ?? '', $urlQuery);
                    if (isset($urlQuery['access_token'])) {
                        $newToken = $this->tokenManager->getToken();
                        $urlQuery['access_token'] = $newToken;
                        $parsedUrl['query'] = http_build_query($urlQuery);
                        $url = $this->unparseUrl($parsedUrl);
                    }
                    continue;
                }

                // 频控（45009）→ 等待后重试
                if ($errcode === 45009 && $attempt < $maxAttempts) {
                    $this->log('warn', "API 频控，2秒后重试");
                    sleep(2);
                    continue;
                }

                // 记录 API 日志
                $this->logApiCall($method, $url, $body, $data, $elapsedMs, $attempt - 1);

                return $data;

            } catch (\Throwable $e) {
                $lastException = $e;
                $this->log('error', "请求异常: {$e->getMessage()} [{$method} {$url}]");
                if ($attempt < $maxAttempts) {
                    usleep(500000); // 等 500ms 重试
                }
            }
        }

        if ($lastException instanceof \Throwable) {
            if ($lastException instanceof ApiException) {
                throw $lastException;
            }
            throw new ApiException('请求失败（重试' . $maxAttempts . '次后）: ' . $lastException->getMessage(), 0, $lastException);
        }

        throw new ApiException('请求失败: 未知错误', -1);
    }

    /**
     * 构建完整 URL
     */
    private function buildUrl(string $path, array $query = [], bool $needToken = true): string
    {
        $domain = $this->config->getApiDomain();

        // 如果 path 已经是完整 URL（如 Webhook），直接返回
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        // 确保 path 以 / 开头
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        if ($needToken) {
            try {
                $query['access_token'] = $this->tokenManager->getToken();
            } catch (\Throwable $e) {
                // 如果获取 token 失败，仍然构建 URL（有些接口不需要 token）
            }
        }

        $url = $domain . $path;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    /**
     * 将 parse_url 结果拼回 URL（用于替换 token 后重新构建）
     */
    private function unparseUrl(array $parsed): string
    {
        $scheme   = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host     = $parsed['host'] ?? '';
        $port     = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $user     = $parsed['user'] ?? '';
        $pass     = isset($parsed['pass']) ? ':' . $parsed['pass'] : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = $parsed['path'] ?? '';
        $query    = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        return "{$scheme}{$user}{$pass}{$host}{$port}{$path}{$query}{$fragment}";
    }

    /**
     * 记录 API 调用日志到数据库
     */
    private function logApiCall(string $method, string $url, mixed $body, array $response, int $elapsedMs, int $retryCount): void
    {
        if (!$this->db) {
            return;
        }
        try {
            $requestBody = is_string($body) ? $body : (is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE) : '');
            $responseStr = json_encode($response, JSON_UNESCAPED_UNICODE);

            // 截断过长的内容
            if (mb_strlen($requestBody) > 8192) {
                $requestBody = mb_substr($requestBody, 0, 8192) . '...[truncated]';
            }
            if (mb_strlen($responseStr) > 8192) {
                $responseStr = mb_substr($responseStr, 0, 8192) . '...[truncated]';
            }

            $this->db->insertRow('wxwork_api_log', [
                'method'           => $method,
                'url'              => mb_substr($url, 0, 1024),
                'request_body'     => $requestBody,
                'response_http_code' => 200,
                'response_body'    => $responseStr,
                'response_errcode' => (int)($response['errcode'] ?? -1),
                'response_errmsg'  => mb_substr($response['errmsg'] ?? '', 0, 500),
                'elapsed_ms'       => $elapsedMs,
                'retry_count'      => $retryCount,
                'ip'               => $_SERVER['SERVER_ADDR'] ?? gethostname(),
            ]);
        } catch (\Throwable $e) {
            // 日志记录失败不影响主流程
        }
    }

    /**
     * 写日志
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger) {
            match ($level) {
                'debug' => $this->logger->debug($message),
                'info'  => $this->logger->info($message),
                'warn'  => $this->logger->warn($message),
                'error' => $this->logger->error($message),
                default => $this->logger->info($message),
            };
        }
    }
}

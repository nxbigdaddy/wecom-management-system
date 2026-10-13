<?php
/**
 * API 模块基类
 * 
 * 所有 API 模块（AddressBook、Message 等）继承此基类，
 * 获得 $http、$config 的直接访问权限和辅助方法。
 */

namespace Wxwork\Api;

use Wxwork\Wxwork;
use Wxwork\Core\ApiException;

abstract class BaseApi
{
    /** @var Wxwork */
    protected Wxwork $wx;

    /** @var \Wxwork\Core\HttpClient */
    protected $http;

    /** @var \Wxwork\Core\Config */
    protected $config;

    public function __construct(Wxwork $wx)
    {
        $this->wx = $wx;
        $this->http = $wx->getHttpClient();
        $this->config = $wx->getConfig();
    }

    /**
     * GET 请求便捷方法
     * @param string $path  API 路径（以 / 开头）
     * @param array  $query 查询参数
     * @return array
     */
    protected function get(string $path, array $query = []): array
    {
        return $this->http->get($path, $query);
    }

    /**
     * POST 请求便捷方法
     * @param string $path
     * @param array  $data
     * @return array
     */
    protected function post(string $path, array $data = []): array
    {
        return $this->http->post($path, $data);
    }

    /**
     * 上传文件便捷方法
     * @param string $path
     * @param string $filePath
     * @param string $type
     * @param array  $extra
     * @return array
     */
    protected function upload(string $path, string $filePath, string $type, array $extra = []): array
    {
        return $this->http->upload($path, $filePath, $type, $extra);
    }

    /**
     * 检查 API 响应是否成功
     */
    protected function isSuccess(array $response): bool
    {
        return ($response['errcode'] ?? -1) === 0;
    }

    /**
     * 从响应中提取数据（errcode != 0 时抛异常）
     * @param array $response
     * @return array
     * @throws ApiException
     */
    protected function result(array $response): array
    {
        $errcode = (int)($response['errcode'] ?? -1);
        if ($errcode !== 0) {
            $errmsg = $response['errmsg'] ?? '未知错误';
            throw new ApiException(
                "API 调用失败: {$errmsg} (errcode={$errcode})",
                $errcode,
                null,
                $response
            );
        }

        // 去掉 errcode / errmsg，返回真正的数据
        unset($response['errcode'], $response['errmsg']);
        return $response;
    }

    /**
     * 分页获取全部数据（自动处理 cursor 分页）
     * 
     * 某些 API（如客户列表）需要分页获取全部数据，
     * 此方法自动循环调用直到没有更多数据。
     * 
     * @param callable $fetcher function(string|null $cursor): array
     *        回调应返回 ['data' => [...], 'next_cursor' => '...']
     * @return array 全部数据的合并结果
     */
    protected function fetchAllPages(callable $fetcher): array
    {
        $allData = [];
        $cursor = null;

        do {
            $result = $fetcher($cursor);
            $data = $result['data'] ?? $result['result_list'] ?? [];
            if (empty($data)) break;

            $allData = array_merge($allData, $data);
            $cursor = $result['next_cursor'] ?? null;
        } while ($cursor !== null && $cursor !== '');

        return $allData;
    }
}

<?php
/**
 * 简易路由
 * 
 * 为管理后台提供 URL 路由功能。
 * 支持 GET/POST/PUT/DELETE 方法、动态参数（{id}）、闭包或控制器文件
 */

namespace Wxwork\Core;

class Router
{
    /** @var array 路由表 */
    private array $routes = [];

    /** @var array 当前匹配的参数 */
    private array $currentParams = [];

    /** @var string 404 处理器 */
    /** @var callable|string|null */
    private $notFoundHandler = null;

    /**
     * 添加路由
     * @param string $method  GET|POST|PUT|DELETE
     * @param string $pattern 路径模式，如 '/contacts/departments/{id}'
     * @param callable|string $handler 处理器
     */
    public function add(string $method, string $pattern, callable|string $handler): self
    {
        // 规范化为 / 开头
        if (!str_starts_with($pattern, '/')) {
            $pattern = '/' . $pattern;
        }
        // 去掉末尾 /，但保留根路径 /
        $pattern = rtrim($pattern, '/');
        if ($pattern === '') {
            $pattern = '/';
        }

        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'regex'   => $this->patternToRegex($pattern),
        ];

        return $this;
    }

    /**
     * GET 路由便捷方法
     */
    public function get(string $pattern, callable|string $handler): self
    {
        return $this->add('GET', $pattern, $handler);
    }

    /**
     * POST 路由便捷方法
     */
    public function post(string $pattern, callable|string $handler): self
    {
        return $this->add('POST', $pattern, $handler);
    }

    /**
     * PUT 路由便捷方法
     */
    public function put(string $pattern, callable|string $handler): self
    {
        return $this->add('PUT', $pattern, $handler);
    }

    /**
     * DELETE 路由便捷方法
     */
    public function delete(string $pattern, callable|string $handler): self
    {
        return $this->add('DELETE', $pattern, $handler);
    }

    /**
     * 设置 404 处理器
     */
    public function setNotFound(callable|string $handler): self
    {
        $this->notFoundHandler = $handler;
        return $this;
    }

    /**
     * 分发请求
     * @param string $method GET|POST
     * @param string $uri    请求 URI
     * @return mixed 处理器返回值
     */
    public function dispatch(string $method, string $uri): mixed
    {
        $method = strtoupper($method);

        // 去掉查询字符串
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/');
        if (empty($uri)) {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['regex'], $uri, $matches)) {
                // 提取命名参数
                $this->currentParams = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $this->currentParams[$key] = $value;
                    }
                }

                $handler = $route['handler'];

                // 如果是闭包
                if (is_callable($handler)) {
                    return call_user_func($handler, $this->currentParams);
                }

                // 如果是文件路径
                if (is_string($handler) && file_exists($handler)) {
                    $params = $this->currentParams;
                    return require $handler;
                }

                // 如果是类名::方法
                if (is_string($handler) && str_contains($handler, '::')) {
                    [$class, $methodName] = explode('::', $handler, 2);
                    if (class_exists($class)) {
                        $instance = new $class();
                        return call_user_func([$instance, $methodName], $this->currentParams);
                    }
                }
            }
        }

        // 404
        if ($this->notFoundHandler) {
            if (is_callable($this->notFoundHandler)) {
                return call_user_func($this->notFoundHandler);
            }
        }

        http_response_code(404);
        echo '<h1>404 Not Found</h1>';
        return null;
    }

    /**
     * 获取当前路由参数
     */
    public function getParams(): array
    {
        return $this->currentParams;
    }

    /**
     * 获取某个参数
     */
    public function getParam(string $name, mixed $default = null): mixed
    {
        return $this->currentParams[$name] ?? $default;
    }

    /**
     * 将路径模式转换为正则表达式
     * '/contacts/{id}' → '/^\/contacts\/(?P<id>[^\/]+)$/'
     * '/members/{userid}/edit' → '/^\/members\/(?P<userid>[^\/]+)\/edit$/'
     */
    private function patternToRegex(string $pattern): string
    {
        // 转义特殊字符
        $regex = preg_quote($pattern, '#');

        // 将 \{(\w+)\} 替换为命名捕获组
        $regex = preg_replace('#\\\\\{(\w+)\\\\\}#', '(?P<$1>[^/]+)', $regex);

        return '#^' . $regex . '$#';
    }
}

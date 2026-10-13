<?php
/**
 * 事件分发器
 * 
 * 将解密后的事件分发给注册的处理器。
 * 支持多个处理器注册到同一事件类型，顺序执行。
 */

namespace Wxwork\Callback;

use Wxwork\Wxwork;

class EventDispatcher
{
    /** @var Wxwork */
    private Wxwork $wx;

    /** 
     * @var array 注册的处理器
     * [
     *   'change_contact' => [['handler' => ..., 'type' => 'class|closure'], ...],
     *   '*' => [[...]]  // 兜底
     * ]
     */
    private array $handlers = [];

    public function __construct(Wxwork $wx)
    {
        $this->wx = $wx;
    }

    /**
     * 注册事件处理器
     * 
     * @param string          $eventType 事件类型（'change_contact' 等），'*' 为兜底
     * @param callable|string $handler   处理器
     */
    public function register(string $eventType, callable|string $handler): self
    {
        $this->handlers[$eventType][] = [
            'handler' => $handler,
            'type'    => is_string($handler) ? 'class' : 'closure',
        ];
        return $this;
    }

    /**
     * 分发事件
     * 
     * @param string $eventType 事件类型
     * @param array  $eventData 事件数据
     * @return string 必须返回 'success' 或 'fail'
     */
    public function dispatch(string $eventType, array $eventData): string
    {
        $logger = $this->wx->getLogger();
        $result = 'success';

        // 1. 精确匹配的处理器
        $matchedHandlers = $this->handlers[$eventType] ?? [];

        // 2. 兜底处理器 '*'
        if ($eventType !== '*' && isset($this->handlers['*'])) {
            $matchedHandlers = array_merge($matchedHandlers, $this->handlers['*']);
        }

        if (empty($matchedHandlers)) {
            $logger->info("事件 {$eventType} 没有注册处理器，使用默认处理");
            return 'success';
        }

        foreach ($matchedHandlers as $entry) {
            try {
                $handlerResult = $this->executeHandler($entry, $eventData);
                if ($handlerResult !== 'success') {
                    $logger->warn("事件 {$eventType} 处理器返回非 success: {$handlerResult}");
                    $result = 'fail';
                }
            } catch (\Throwable $e) {
                $logger->error("事件 {$eventType} 处理器异常: " . $e->getMessage());
                $result = 'fail';
                // 继续执行下一个处理器
            }
        }

        return $result;
    }

    /**
     * 执行单个处理器
     */
    private function executeHandler(array $entry, array $eventData): string
    {
        $handler = $entry['handler'];
        $type = $entry['type'];

        if ($type === 'closure' && is_callable($handler)) {
            return (string)$handler($eventData);
        }

        if ($type === 'class' && is_string($handler)) {
            if (!class_exists($handler)) {
                throw new \RuntimeException("处理器类不存在: {$handler}");
            }

            $instance = new $handler();
            if ($instance instanceof HandlerInterface) {
                return $instance->handle($eventData);
            }

            // 尝试闭包方式（类实现了 __invoke）
            if (is_callable($instance)) {
                return (string)$instance($eventData);
            }

            throw new \RuntimeException("处理器类 {$handler} 未实现 HandlerInterface 或 __invoke");
        }

        throw new \RuntimeException("无效的处理器");
    }
}

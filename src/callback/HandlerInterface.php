<?php
/**
 * 事件处理器接口
 * 
 * 所有事件处理器需实现此接口。
 */

namespace Wxwork\Callback;

interface HandlerInterface
{
    /**
     * 处理事件
     * 
     * @param array $event 事件数据（XML 解析后的关联数组）
     * @return string 必须返回 'success' 表示处理成功
     * @throws \Exception 处理失败抛异常
     */
    public function handle(array $event): string;
}

# 06 - 回调系统设计

> 企业微信管理系统 · 原生 PHP · 回调事件处理 · 设计文档

---

## 6.1 回调系统概述

企业微信在以下场景会向配置的 URL 推送 **POST 请求**（XML 格式、AES 加密）：

| 回调场景 | 说明 |
|----------|------|
| **通讯录变更** | 成员/部门/标签的增删改 |
| **客户联系变更** | 客户添加/删除/编辑、客户群变更、离职继承等 |
| **审批状态变更** | 审批申请提交、审批通过/驳回等 |
| **消息推送** | 应用接收消息（如用户发送给应用的消息） |
| **自定义回调** | 其他事件 |

### 数据格式

```
企业微信 POST 一个加密的 XML：
<xml>
    <ToUserName><![CDATA[corpid]]></ToUserName>
    <Encrypt><![CDATA[Base64编码的密文]]></Encrypt>
    <AgentID><![CDATA[应用ID]]></AgentID>
</xml>

URL 参数带上：
?msg_signature=xxx&timestamp=xxx&nonce=xxx
```

解密后得到明文 XML（以成员新增为例）：

```xml
<xml>
    <ToUserName><![CDATA[corpid]]></ToUserName>
    <FromUserName><![CDATA[sys]]></FromUserName>
    <CreateTime>1408091189</CreateTime>
    <MsgType><![CDATA[event]]></MsgType>
    <Event><![CDATA[change_contact]]></Event>
    <ChangeType><![CDATA[create_user]]></ChangeType>
    <UserID><![CDATA[zhangsan]]></UserID>
    <Name><![CDATA[张三]]></Name>
    <Department><![CDATA[1,2]]></Department>
    <Mobile><![CDATA[13800000000]]></Mobile>
    ...
</xml>
```

---

## 6.2 核心组件：Callback Server

```php
namespace Wxwork\Callback;

use Wxwork\Core\Wxwork;
use Wxwork\Core\Crypt;

class Server
{
    private Wxwork $wx;
    private Crypt $crypt;
    private EventDispatcher $dispatcher;
    
    public function __construct(Wxwork $wx);
    
    /**
     * 处理回调请求（入口方法）
     * 
     * 在 public/callback.php 中调用：
     *   $wx->callback->serve();
     */
    public function serve(): void;
    
    /**
     * URL 验证（GET 请求，首次配置回调 URL 时）
     * 企业微信会发 GET 请求验证 URL 有效性
     */
    public function verifyUrl(): void;
    
    /**
     * 处理事件推送（POST 请求）
     */
    public function handleEvent(): void;
    
    /**
     * 注册事件处理器
     * 
     * @param string   $eventType  事件类型（如 'change_contact'）
     * @param callable|string $handler  处理器（闭包或类名）
     */
    public function on(string $eventType, callable|string $handler): self;
}
```

### 入口文件 `public/callback.php`

```php
<?php
/**
 * 企业微信回调入口
 * 
 * 在企业管理后台配置回调 URL 为:
 *   https://yourdomain.com/callback.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Wxwork\Core\Wxwork;
use Wxwork\Callback\Handlers\ChangeContact;
use Wxwork\Callback\Handlers\ChangeExternal;

// 初始化
$wx = new Wxwork();

// 注册事件处理器
$wx->callback->on('change_contact', ChangeContact::class);
$wx->callback->on('change_external_contact', ChangeExternal::class);

// 也可以直接用闭包
$wx->callback->on('sys_approval_change', function($event) use ($wx) {
    // 处理审批变更
    // $event 是解析后的数组
    $spNo = $event['SpNo'];
    $status = $event['ApprovalStatus'];
    // ... 业务逻辑
    return 'success';
});

// 启动回调服务
$wx->callback->serve();
```

---

## 6.3 事件分发器

```php
namespace Wxwork\Callback;

class EventDispatcher
{
    /**
     * 注册的处理器
     * [
     *   'change_contact' => [ChangeContact::class, ...],
     *   'change_external_contact' => [ChangeExternal::class, ...],
     *   '*' => [DefaultHandler::class],  // 兜底
     * ]
     */
    private array $handlers = [];
    
    /**
     * 注册事件处理器
     */
    public function register(string $eventType, callable|string $handler): self;
    
    /**
     * 分发事件
     * 
     * @param string $eventType  事件类型（从 MsgType 或 Event 字段取）
     * @param array  $eventData  解析后的事件数据
     */
    public function dispatch(string $eventType, array $eventData): string;
    
    /**
     * 执行单个处理器
     */
    private function executeHandler(callable|string $handler, array $eventData): string;
}
```

### 分发逻辑

```
┌─ 收到事件
│
├─ 1. 从 XML 提取 Event + ChangeType
│   例: Event='change_contact', ChangeType='create_user'
│
├─ 2. 找到注册的处理器
│   - 先匹配 Event (如 'change_contact')
│   - 没有则匹配 '*'
│
├─ 3. 顺序执行处理器（支持多个）
│   - 每个处理器返回 'success' 或抛异常
│   - 任一返回非 'success' 则记录错误但继续
│
├─ 4. 记录回调日志到 wxwork_callback_log
│
└─ 5. 返回 'success' 给企业微信
```

---

## 6.4 事件处理器接口

```php
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
```

---

## 6.5 内置事件处理器

### 6.5.1 ChangeContact — 通讯录变更

```php
namespace Wxwork\Callback\Handlers;

use Wxwork\Callback\HandlerInterface;
use Wxwork\Models\DepartmentModel;
use Wxwork\Models\MemberModel;
use Wxwork\Models\TagModel;

class ChangeContact implements HandlerInterface
{
    /**
     * 处理通讯录变更事件
     * 
     * ChangeType 可能性：
     * - create_user    新增成员
     * - update_user    更新成员
     * - delete_user    删除成员
     * - create_party   新增部门
     * - update_party   更新部门
     * - delete_party   删除部门
     * - update_tag     标签变更
     */
    public function handle(array $event): string
    {
        $changeType = $event['ChangeType'] ?? '';
        
        switch ($changeType) {
            case 'create_user':
            case 'update_user':
                // 同步到本地 wxwork_member 表
                MemberModel::upsert([
                    'userid'   => $event['UserID'],
                    'name'     => $event['Name'] ?? '',
                    'mobile'   => $event['Mobile'] ?? '',
                    'position' => $event['Position'] ?? '',
                    // ... 更多字段
                ]);
                break;
                
            case 'delete_user':
                // 标记删除
                MemberModel::softDelete($event['UserID']);
                break;
                
            case 'create_party':
            case 'update_party':
                DepartmentModel::upsert([
                    'dept_id'   => $event['Id'],
                    'name'      => $event['Name'] ?? '',
                    'parent_id' => $event['ParentId'] ?? 0,
                    'order'     => $event['Order'] ?? 0,
                ]);
                break;
                
            case 'delete_party':
                DepartmentModel::softDelete($event['Id']);
                break;
                
            case 'update_tag':
                TagModel::syncByCallback($event);
                break;
        }
        
        return 'success';
    }
}
```

### 6.5.2 ChangeExternal — 客户变更

```php
namespace Wxwork\Callback\Handlers;

use Wxwork\Callback\HandlerInterface;

class ChangeExternal implements HandlerInterface
{
    /**
     * ChangeType 可能性：
     * - add_external_contact        添加客户
     * - edit_external_contact       编辑客户
     * - del_external_contact        删除客户
     * - add_half_external_contact   免验证添加（已作废）
     * - del_follow_user             被客户删除
     * - transfer_fail               接替失败
     * - change_external_chat        客户群变更
     * - create_chat                 创建客户群
     * - update_chat                 更新客户群
     * - dismiss_chat                解散客户群
     */
    public function handle(array $event): string
    {
        $changeType = $event['ChangeType'] ?? '';
        
        switch ($changeType) {
            case 'add_external_contact':
                // 新客户添加 → 同步到 wxwork_external_contact + wxwork_external_follow
                break;
                
            case 'del_external_contact':
            case 'del_follow_user':
                // 客户删除 → 标记删除
                break;
                
            case 'change_external_chat':
                // 客户群变更 → 更新 wxwork_external_group
                break;
                
            // ... 更多
        }
        
        return 'success';
    }
}
```

---

## 6.6 完整事件类型参考

### 通讯录变更（Event = `change_contact`）

| ChangeType | 说明 | 关键字段 |
|------------|------|----------|
| `create_user` | 新增成员 | UserID, Name, Department, Mobile |
| `update_user` | 更新成员 | UserID, 变更的字段 |
| `delete_user` | 删除成员 | UserID |
| `create_party` | 新增部门 | Id, Name, ParentId, Order |
| `update_party` | 更新部门 | Id, 变更的字段 |
| `delete_party` | 删除部门 | Id |
| `update_tag` | 标签变更 | TagId, AddUserItems, DelUserItems |

### 客户联系变更（Event = `change_external_contact`）

| ChangeType | 说明 |
|------------|------|
| `add_external_contact` | 成员添加客户 |
| `edit_external_contact` | 成员编辑客户备注 |
| `del_external_contact` | 成员删除客户 |
| `del_follow_user` | 客户删除成员 |
| `transfer_fail` | 客户接替失败 |
| `change_external_chat` | 客户群变更 |
| `create_chat` | 客户群创建 |
| `update_chat` | 客户群更新 |
| `dismiss_chat` | 客户群解散 |

### 审批状态变更（Event = `sys_approval_change`）

| 状态 | 说明 |
|------|------|
| `1` | 审批中 |
| `2` | 已通过 |
| `3` | 已驳回 |
| `4` | 已撤销 |

---

## 6.7 回调安全

### 签名验证流程

```
1. 企业微信 POST 到 callback.php?msg_signature=SIGN&timestamp=TS&nonce=N
2. 取请求体中的 <Encrypt> 值（密文）
3. 计算签名: SHA1(sort(token, timestamp, nonce, encrypt))
4. 比对 msg_signature == 计算的签名
5. 一致 → 解密 → 处理
6. 不一致 → 丢弃（可能是伪造请求）
```

### 回复加密

处理完成后，如果需要回复（如消息推送场景），需要将回复 XML 加密后返回：

```
1. 构造回复 XML（明文）
2. AES-256-CBC 加密 → Base64
3. 生成时间戳 + nonce
4. 计算签名 SHA1(sort(token, timestamp, nonce, encrypt))
5. 构造加密回复 XML:
   <xml>
     <Encrypt><![CDATA[base64密文]]></Encrypt>
     <MsgSignature><![CDATA[签名]]></MsgSignature>
     <TimeStamp>时间戳</TimeStamp>
     <Nonce><![CDATA[随机数]]></Nonce>
   </xml>
6. 返回（content-type: text/xml）
```

---

## 6.8 消息推送回调（接收用户消息）

如果开启消息推送回调，用户发送给应用的消息也会推送到回调 URL。

### 消息类型

| MsgType | 说明 |
|---------|------|
| `text` | 文本消息 |
| `image` | 图片消息 |
| `voice` | 语音消息 |
| `video` | 视频消息 |
| `location` | 位置消息 |
| `link` | 链接消息 |
| `event` | 事件（如菜单点击） |

### 示例：文本消息

```xml
<xml>
    <ToUserName><![CDATA[corpid]]></ToUserName>
    <FromUserName><![CDATA[UserID]]></FromUserName>
    <CreateTime>1348831860</CreateTime>
    <MsgType><![CDATA[text]]></MsgType>
    <Content><![CDATA[你好]]></Content>
    <MsgId>1234567890123456</MsgId>
    <AgentID>1</AgentID>
</xml>
```

处理后可回复文本（需加密）或调用 API 主动发送。

---

## 6.9 可靠性保证

| 机制 | 说明 |
|------|------|
| **幂等处理** | 根据 `MsgId` 或 `CreateTime` 去重（企业微信可能重试） |
| **快速响应** | 5 秒内必须返回，否则企业微信认为失败 |
| **失败重试** | 企业微信在失败后会每隔 5 分钟重试，最多 3 次 |
| **异步化** | 耗时操作（如同步数据）放入队列异步执行，不阻塞响应 |
| **日志记录** | 所有回调记录到 `wxwork_callback_log` 表，可追溯 |

### 异步处理实现（可选 Redis 队列）

```php
// 耗时操作放入队列
public function handle(array $event): string
{
    // 1. 快速记录日志
    $this->logCallback($event);
    
    // 2. 耗时操作推入 Redis 队列
    if ($this->isHeavyOperation($event)) {
        $this->pushToQueue($event);
    } else {
        $this->process($event);
    }
    
    // 3. 立即返回
    return 'success';
}
```

---

> 下一章节：[07 - 开发路线图](./07-development-roadmap.md)

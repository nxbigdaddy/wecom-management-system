# 04 - API 模块设计

> 企业微信管理系统 · 原生 PHP · API 模块层 · 设计文档

---

## 4.1 BaseApi — 基类

所有 API 模块继承此基类，获得 `$http`、`$config` 的直接访问权限：

```php
namespace Wxwork\Api;

use Wxwork\Core\Wxwork;

abstract class BaseApi
{
    protected Wxwork $wx;
    protected \Wxwork\Core\HttpClient $http;
    protected \Wxwork\Core\Config $config;
    
    public function __construct(Wxwork $wx);
    
    /**
     * GET 请求便捷方法
     */
    protected function get(string $path, array $query = []): array;
    
    /**
     * POST 请求便捷方法
     */
    protected function post(string $path, array $data = []): array;
    
    /**
     * 上传文件便捷方法
     */
    protected function upload(string $path, string $filePath, string $type, array $extra = []): array;
    
    /**
     * 检查 API 响应是否成功
     */
    protected function isSuccess(array $response): bool;
    
    /**
     * 从响应中提取数据（errcode != 0 时抛异常）
     */
    protected function result(array $response): array;
}
```

---

## 4.2 AddressBook — 通讯录管理

> 覆盖：部门管理、成员管理、标签管理、异步任务、批量导入

### 4.2.1 部门管理

```php
class AddressBook extends BaseApi
{
    // ========== 部门管理 ==========
    
    /**
     * 创建部门
     * POST /cgi-bin/department/create
     */
    public function departmentCreate(array $data): array;
    // $data: ['name'=>'技术部', 'parentid'=>1, 'order'=>1, 'id'=>2]
    
    /**
     * 更新部门
     * POST /cgi-bin/department/update
     */
    public function departmentUpdate(array $data): array;
    
    /**
     * 删除部门
     * GET /cgi-bin/department/delete?id=2
     */
    public function departmentDelete(int $deptId): array;
    
    /**
     * 获取部门列表
     * GET /cgi-bin/department/list?id=1
     */
    public function departmentList(int $parentId = 0): array;
    
    /**
     * 获取全部部门（递归拉取）
     */
    public function departmentListAll(): array;
    
    /**
     * 获取单个部门详情
     * GET /cgi-bin/department/get?id=2
     */
    public function departmentGet(int $deptId): array;
    
    // ========== 成员管理 ==========
    
    /**
     * 创建成员
     * POST /cgi-bin/user/create
     */
    public function userCreate(array $data): array;
    // $data: ['userid'=>'zhangsan', 'name'=>'张三', 'mobile'=>'13800138000',
    //         'department'=>[1,2], 'position'=>'工程师', ...]
    
    /**
     * 更新成员
     * POST /cgi-bin/user/update
     */
    public function userUpdate(array $data): array;
    
    /**
     * 删除成员
     * GET /cgi-bin/user/delete?userid=zhangsan
     */
    public function userDelete(string $userid): array;
    
    /**
     * 批量删除成员
     * POST /cgi-bin/user/batchdelete
     */
    public function userBatchDelete(array $userids): array;
    
    /**
     * 获取成员详情
     * GET /cgi-bin/user/get?userid=zhangsan
     */
    public function userGet(string $userid): array;
    
    /**
     * 获取部门成员
     * GET /cgi-bin/user/simplelist?department_id=1&fetch_child=1
     */
    public function userSimpleList(int $deptId, bool $fetchChild = false): array;
    
    /**
     * 获取部门成员（详情）
     * GET /cgi-bin/user/list?department_id=1&fetch_child=1
     */
    public function userList(int $deptId, bool $fetchChild = false): array;
    
    /**
     * userid 与 open_userid 互转
     * POST /cgi-bin/user/convert_to_openid
     */
    public function userConvertToOpenId(string $userid): array;
    
    /**
     * open_userid 转 userid
     * POST /cgi-bin/user/convert_to_userid
     */
    public function userConvertToUserId(string $openUserid): array;
    
    /**
     * 二次验证
     * GET /cgi-bin/user/authsucc?userid=zhangsan
     */
    public function userAuthSucc(string $userid): array;
    
    /**
     * 获取成员加入企业二维码
     * GET /cgi-bin/user/get_join_qrcode?size_type=1
     */
    public function userGetJoinQrcode(int $sizeType = 1): array;
    
    /**
     * 获取成员ID列表（批量工具）
     * POST /cgi-bin/user/list_id
     */
    public function userListId(?string $cursor = null, int $limit = 10000): array;
    
    /**
     * 手机号获取 userid
     * POST /cgi-bin/user/getuserid
     */
    public function userGetUserIdByMobile(string $mobile): array;
    
    /**
     * 邮箱获取 userid
     * POST /cgi-bin/user/get_userid_by_email
     */
    public function userGetUserIdByEmail(string $email, int $emailType = 1): array;
    
    // ========== 标签管理 ==========
    
    /**
     * 创建标签
     * POST /cgi-bin/tag/create
     */
    public function tagCreate(string $tagName, ?int $tagId = null): array;
    
    /**
     * 更新标签名称
     * POST /cgi-bin/tag/update
     */
    public function tagUpdate(int $tagId, string $tagName): array;
    
    /**
     * 删除标签
     * GET /cgi-bin/tag/delete?tagid=1
     */
    public function tagDelete(int $tagId): array;
    
    /**
     * 获取标签列表
     * GET /cgi-bin/tag/list
     */
    public function tagList(): array;
    
    /**
     * 获取标签成员
     * GET /cgi-bin/tag/get?tagid=1
     */
    public function tagGet(int $tagId): array;
    
    /**
     * 增加标签成员
     * POST /cgi-bin/tag/addtagusers
     */
    public function tagAddUsers(int $tagId, array $userids, array $partyids = []): array;
    
    /**
     * 删除标签成员
     * POST /cgi-bin/tag/deltagusers
     */
    public function tagDeleteUsers(int $tagId, array $userids, array $partyids = []): array;
    
    // ========== 异步批量任务 ==========
    
    /**
     * 增量更新成员（异步）
     * POST /cgi-bin/batch/syncuser
     */
    public function batchSyncUser(array $data): array;
    // 返回 jobid
    
    /**
     * 全量覆盖成员（异步）
     * POST /cgi-bin/batch/replaceuser
     */
    public function batchReplaceUser(array $data): array;
    
    /**
     * 全量覆盖部门（异步）
     * POST /cgi-bin/batch/replaceparty
     */
    public function batchReplaceParty(array $data): array;
    
    /**
     * 获取异步任务结果
     * GET /cgi-bin/batch/getresult?jobid=xxx
     */
    public function batchGetResult(string $jobId): array;
}
```

---

## 4.3 Message — 消息推送

> 覆盖：应用消息（全部消息类型）、群机器人 Webhook、撤回消息

```php
class Message extends BaseApi
{
    // ========== 应用消息 ==========
    
    /**
     * 发送文本消息
     * POST /cgi-bin/message/send
     */
    public function sendText(array $options): array;
    // $options: ['touser'=>'', 'toparty'=>'', 'totag'=>'', 'content'=>'文本内容', 'safe'=>0]
    
    /**
     * 发送图片消息
     */
    public function sendImage(array $options): array;
    // $options: [..., 'media_id'=>'xxx']
    
    /**
     * 发送语音消息
     */
    public function sendVoice(array $options): array;
    
    /**
     * 发送视频消息
     */
    public function sendVideo(array $options): array;
    // $options: [..., 'media_id'=>'', 'title'=>'', 'description'=>'']
    
    /**
     * 发送文件消息
     */
    public function sendFile(array $options): array;
    // $options: [..., 'media_id'=>'']
    
    /**
     * 发送文本卡片消息
     */
    public function sendTextCard(array $options): array;
    // $options: [..., 'title'=>'', 'description'=>'', 'url'=>'', 'btntxt'=>'详情']
    
    /**
     * 发送图文消息（news）
     */
    public function sendNews(array $options): array;
    // $options: [..., 'articles'=>[['title'=>'','description'=>'','url'=>'','picurl'=>'']]]
    
    /**
     * 发送图文消息（mpnews）- 需先上传封面和内容
     */
    public function sendMpNews(array $options): array;
    
    /**
     * 发送小程序通知消息
     */
    public function sendMiniProgramNotice(array $options): array;
    
    /**
     * 发送任务卡片消息
     */
    public function sendTaskCard(array $options): array;
    
    /**
     * 发送模板卡片消息
     */
    public function sendTemplateCard(array $options): array;
    
    /**
     * 创建企业群发（应用消息群发到全部/指定成员）
     * POST /cgi-bin/message/send_to_chat
     */
    public function sendToChat(array $options): array;
    
    // ========== 撤回消息 ==========
    
    /**
     * 撤回应用消息
     * POST /cgi-bin/message/recall
     */
    public function recall(string $msgId): array;
    
    // ========== 群机器人 Webhook ==========
    
    /**
     * 通过 Webhook 发送消息（不需要 access_token！）
     * 
     * @param string $webhookUrl 群机器人的 webhook 地址
     */
    public function sendWebhook(string $webhookUrl, string $msgType, array $data): array;
    
    /**
     * Webhook 文本消息
     */
    public function sendWebhookText(string $webhookUrl, string $content, array $mentioned = []): array;
    
    /**
     * Webhook Markdown 消息
     */
    public function sendWebhookMarkdown(string $webhookUrl, string $content): array;
    
    /**
     * Webhook 图片消息
     */
    public function sendWebhookImage(string $webhookUrl, string $base64, string $md5): array;
    
    /**
     * Webhook 图文消息
     */
    public function sendWebhookNews(string $webhookUrl, array $articles): array;
    
    // ========== 通用发送方法 ==========
    
    /**
     * 底层 send 方法（消息的 receiver 和 content 分离）
     */
    public function send(string $msgType, array $receiver, array $content, array $options = []): array;
}
```

### 消息发送核心参数说明

```php
// $receiver: 接收人（三选一或组合）
[
    'touser'  => 'zhangsan|lisi',       // 成员ID列表，最多1000个，用|分隔
    'toparty' => '1|2',                  // 部门ID列表
    'totag'   => '1|2',                  // 标签ID列表
]

// $content: 按消息类型不同
// text:        ['content' => '文本内容']
// textcard:    ['title'=>'', 'description'=>'', 'url'=>'', 'btntxt'=>'']
// news:        ['articles' => [['title'=>'', 'description'=>'', 'url'=>'', 'picurl'=>'']]]
// image:       ['media_id' => '']
// 等等...

// $options: 扩展选项
[
    'safe'                   => 0,  // 是否保密消息
    'enable_id_trans'        => 0,  // 是否开启ID转译
    'enable_duplicate_check' => 0,  // 是否开启重复消息检查
    'duplicate_check_interval' => 1800, // 重复检查间隔（秒）
]
```

---

## 4.4 Auth — 身份认证

> 覆盖：OAuth2 网页授权、扫码登录、JS-SDK 签名

```php
class Auth extends BaseApi
{
    // ========== OAuth2 网页授权 ==========
    
    /**
     * 构造 OAuth2 授权 URL
     * GET https://open.weixin.qq.com/connect/oauth2/authorize
     * 
     * @param string $redirectUri 回调地址（需在可信域名白名单中）
     * @param string $state       自定义参数，回调时会原样返回
     * @param string $scope       snsapi_base（静默）| snsapi_privateinfo（手动授权）
     */
    public function buildOAuthUrl(string $redirectUri, string $state = '', string $scope = 'snsapi_base'): string;
    
    /**
     * 通过 OAuth code 获取成员身份
     * GET /cgi-bin/auth/getuserinfo?code=CODE
     * 
     * 返回: ['userid'=>'', 'openid'=>'', 'deviceid'=>'', 'user_ticket'=>'']
     */
    public function getUserInfo(string $code): array;
    
    /**
     * 通过 user_ticket 获取成员详情（需 scope=snsapi_privateinfo）
     * POST /cgi-bin/auth/getuserdetail
     */
    public function getUserDetail(string $userTicket): array;
    
    // ========== 扫码登录（管理后台/网站扫码登录） ==========
    
    /**
     * 构造扫码登录 URL
     * GET https://open.work.weixin.qq.com/wwopen/sso/qrConnect
     */
    public function buildQrLoginUrl(string $redirectUri, string $state = ''): string;
    
    /**
     * 扫码登录 code 换取身份
     * POST /cgi-bin/service/get_login_info
     */
    public function getLoginInfo(string $authCode): array;
    
    // ========== JS-SDK ==========
    
    /**
     * 获取 JS-SDK ticket
     * GET /cgi-bin/get_jsapi_ticket
     */
    public function getJsApiTicket(): string;
    
    /**
     * 获取企业 JS-SDK ticket（agentConfig 用）
     * GET /cgi-bin/ticket/get?type=agent_config
     */
    public function getAgentTicket(): string;
    
    /**
     * 生成 JS-SDK 签名包
     * 
     * @param string $url 当前页面的完整URL
     * @return array 包含 appId, timestamp, nonceStr, signature
     */
    public function buildJsSign(string $url): array;
    
    /**
     * 生成 agentConfig 签名包
     */
    public function buildAgentSign(string $url): array;
}
```

---

## 4.5 ExternalContact — 客户联系/SCRM

> 最复杂的模块之一，覆盖：客户获取、客户详情、客户群、标签、离职继承、在职继承、朋友圈、统计等

```php
class ExternalContact extends BaseApi
{
    // ========== 客户管理 ==========
    
    /**
     * 获取客户列表（成员维度）
     * GET /cgi-bin/externalcontact/list?userid=zhangsan
     */
    public function list(string $userid): array;
    
    /**
     * 获取客户详情
     * GET /cgi-bin/externalcontact/get?external_userid=xxx
     */
    public function get(string $externalUserid): array;
    
    /**
     * 批量获取客户详情（最多 100 个）
     * POST /cgi-bin/externalcontact/get_by_user
     */
    public function getByUser(string $userid, string $cursor = '', int $limit = 100): array;
    
    /**
     * 修改客户备注信息
     * POST /cgi-bin/externalcontact/remark
     */
    public function remark(array $data): array;
    // $data: ['userid'=>'', 'external_userid'=>'', 'remark'=>'', 'description'=>'', ...]
    
    /**
     * 获取离职成员的客户列表
     * POST /cgi-bin/externalcontact/get_unassigned_list
     */
    public function getUnassignedList(int $pageId = 0, int $pageSize = 100): array;
    
    /**
     * 离职成员客户继承（分配）
     * POST /cgi-bin/externalcontact/transfer
     */
    public function transfer(string $handoverUserid, string $takeoverUserid, array $externalUserids): array;
    
    /**
     * 查询客户接替结果
     * POST /cgi-bin/externalcontact/get_transfer_result
     */
    public function getTransferResult(string $handoverUserid, string $takeoverUserid): array;
    
    /**
     * 在职成员的客户转接
     * POST /cgi-bin/externalcontact/transfer_customer
     */
    public function transferCustomer(string $handoverUserid, string $takeoverUserid, array $externalUserids): array;
    
    /**
     * 查询在职成员转接结果
     * POST /cgi-bin/externalcontact/get_transfer_customer_result
     */
    public function getTransferCustomerResult(string $handoverUserid, string $takeoverUserid): array;
    
    // ========== 客户群管理 ==========
    
    /**
     * 获取客户群列表
     * POST /cgi-bin/externalcontact/groupchat/list
     */
    public function groupChatList(array $params = []): array;
    // $params: ['status_filter'=>0, 'owner_filter'=>['userid'], 'cursor'=>'', 'limit'=>100]
    
    /**
     * 获取客户群详情
     * POST /cgi-bin/externalcontact/groupchat/get
     */
    public function groupChatGet(string $chatId): array;
    
    /**
     * 客户群 openGid 转 chatId
     * POST /cgi-bin/externalcontact/opengid_to_chatid
     */
    public function openGidToChatId(string $openGid): array;
    
    // ========== 客户标签 ==========
    
    /**
     * 获取企业标签库
     * POST /cgi-bin/externalcontact/get_corp_tag_list
     */
    public function getCorpTagList(array $tagIds = []): array;
    
    /**
     * 添加企业标签
     * POST /cgi-bin/externalcontact/add_corp_tag
     */
    public function addCorpTag(array $data): array;
    
    /**
     * 编辑企业标签
     * POST /cgi-bin/externalcontact/edit_corp_tag
     */
    public function editCorpTag(string $id, string $name, int $order = 1): array;
    
    /**
     * 删除企业标签
     * POST /cgi-bin/externalcontact/del_corp_tag
     */
    public function delCorpTag(array $tagIds, array $groupIds = []): array;
    
    /**
     * 给客户打标签
     * POST /cgi-bin/externalcontact/mark_tag
     */
    public function markTag(string $userid, string $externalUserid, array $addTag, array $removeTag = []): array;
    
    // ========== 客户朋友圈 ==========
    
    /**
     * 获取企业当前可发朋友圈成员列表
     * GET /cgi-bin/externalcontact/get_moment_list
     */
    public function getMomentList(string $startTime, string $endTime, string $cursor = '', int $limit = 100): array;
    
    /**
     * 创建朋友圈任务
     * POST /cgi-bin/externalcontact/add_moment_task
     */
    public function addMomentTask(array $data): array;
    
    /**
     * 获取朋友圈任务结果
     * GET /cgi-bin/externalcontact/get_moment_task_result
     */
    public function getMomentTaskResult(string $momentId): array;
    
    /**
     * 停止朋友圈任务
     * POST /cgi-bin/externalcontact/cancel_moment_task
     */
    public function cancelMomentTask(string $momentId): array;
    
    // ========== 客户群发消息 ==========
    
    /**
     * 创建企业群发消息
     * POST /cgi-bin/externalcontact/add_msg_template
     */
    public function addMsgTemplate(array $data): array;
    
    /**
     * 获取群发记录列表
     * POST /cgi-bin/externalcontact/get_groupmsg_list_v2
     */
    public function getGroupMsgListV2(array $params): array;
    
    /**
     * 获取群发发送结果
     * POST /cgi-bin/externalcontact/get_groupmsg_task
     */
    public function getGroupMsgTask(string $msgid, int $limit = 1000, string $cursor = ''): array;
    
    // ========== 统计 ==========
    
    /**
     * 获取联系客户统计
     * POST /cgi-bin/externalcontact/get_user_behavior_data
     */
    public function getUserBehaviorData(array $data): array;
    
    /**
     * 获取「群聊数据统计」
     * POST /cgi-bin/externalcontact/groupchat/statistic
     */
    public function groupChatStatistic(array $data): array;
    
    // ========== 欢迎语 ==========
    
    /**
     * 发送新客户欢迎语
     * POST /cgi-bin/externalcontact/send_welcome_msg
     */
    public function sendWelcomeMsg(array $data): array;
    
    /**
     * 配置客户群进群方式
     * POST /cgi-bin/externalcontact/groupchat/add_join_way
     */
    public function addJoinWay(array $data): array;
    
    /**
     * 获取客户群进群方式配置
     * POST /cgi-bin/externalcontact/groupchat/get_join_way
     */
    public function getJoinWay(string $configId): array;
    
    /**
     * 更新客户群进群方式配置
     * POST /cgi-bin/externalcontact/groupchat/update_join_way
     */
    public function updateJoinWay(array $data): array;
    
    /**
     * 删除客户群进群方式配置
     * POST /cgi-bin/externalcontact/groupchat/del_join_way
     */
    public function delJoinWay(string $configId): array;
    
    // ========== 联系我与客户同意归档 ==========
    
    /**
     * 获取配置了「联系我」的二维码列表
     * POST /cgi-bin/externalcontact/get_contact_way
     */
    public function getContactWay(string $configId): array;
    
    /**
     * 新增「联系我」方式
     * POST /cgi-bin/externalcontact/add_contact_way
     */
    public function addContactWay(array $data): array;
    
    /**
     * 更新「联系我」方式
     * POST /cgi-bin/externalcontact/update_contact_way
     */
    public function updateContactWay(array $data): array;
    
    /**
     * 删除「联系我」方式
     * POST /cgi-bin/externalcontact/del_contact_way
     */
    public function delContactWay(string $configId): array;
}
```

---

## 4.6 Media — 素材管理

```php
class Media extends BaseApi
{
    // ========== 临时素材 ==========
    
    /**
     * 上传临时素材（3天有效）
     * POST /cgi-bin/media/upload?type=image
     */
    public function upload(string $filePath, string $type): array;
    // $type: image | voice | video | file
    
    /**
     * 获取临时素材
     * GET /cgi-bin/media/get?media_id=xxx
     * 
     * @return string 图片/文件内容（二进制），或 JSON（视频）
     */
    public function get(string $mediaId): string|array;
    
    /**
     * 上传图片（上传后返回 URL，永久有效）
     * POST /cgi-bin/media/uploadimg
     */
    public function uploadImage(string $filePath): array;
    // 返回: ['url'=>'https://...']
    
    // ========== 永久素材 ==========
    
    /**
     * 上传永久素材
     * POST /cgi-bin/material/add_material
     */
    public function addMaterial(string $filePath, string $type, array $extra = []): array;
    // $type: image | voice | video | file
    
    /**
     * 获取永久素材
     * POST /cgi-bin/material/get_material
     */
    public function getMaterial(string $mediaId): string|array;
    
    /**
     * 删除永久素材
     * GET /cgi-bin/material/del_material?media_id=xxx
     */
    public function delMaterial(string $mediaId): array;
    
    /**
     * 获取永久素材数量
     * GET /cgi-bin/material/get_material_count
     */
    public function getMaterialCount(): array;
    
    /**
     * 分页获取永久素材列表
     * POST /cgi-bin/material/batchget_material
     */
    public function batchGetMaterial(string $type, int $offset = 0, int $count = 20): array;
}
```

---

## 4.7 AppManage — 应用管理

```php
class AppManage extends BaseApi
{
    /**
     * 获取企业所有应用列表
     * GET /cgi-bin/agent/list
     */
    public function agentList(): array;
    
    /**
     * 获取应用详情
     * GET /cgi-bin/agent/get?agentid=1000002
     */
    public function agentGet(int $agentId): array;
    
    /**
     * 设置应用信息
     * POST /cgi-bin/agent/set
     */
    public function agentSet(array $data): array;
    
    // ========== 自定义菜单 ==========
    
    /**
     * 创建菜单
     * POST /cgi-bin/menu/create?agentid=1000002
     */
    public function menuCreate(int $agentId, array $buttons): array;
    
    /**
     * 获取菜单
     * GET /cgi-bin/menu/get?agentid=1000002
     */
    public function menuGet(int $agentId): array;
    
    /**
     * 删除菜单
     * GET /cgi-bin/menu/delete?agentid=1000002
     */
    public function menuDelete(int $agentId): array;
}
```

---

## 4.8 Approval — OA 审批

```php
class Approval extends BaseApi
{
    /**
     * 获取审批模板详情
     * POST /cgi-bin/oa/gettemplatedetail
     */
    public function getTemplateDetail(string $templateId): array;
    
    /**
     * 创建审批申请（代提交）
     * POST /cgi-bin/oa/applyevent
     */
    public function applyEvent(array $data): array;
    
    /**
     * 获取审批申请详情
     * POST /cgi-bin/oa/getapprovaldetail
     */
    public function getApprovalDetail(string $spNo): array;
    
    /**
     * 获取审批单号列表
     * POST /cgi-bin/oa/getapprovalinfo
     */
    public function getApprovalInfo(array $filters): array;
    // $filters: ['starttime'=>'', 'endtime'=>'', 'cursor'=>'', 'size'=>100, 'filters'=>[]]
    
    /**
     * 快速同意/驳回审批
     * POST /cgi-bin/oa/approve
     */
    public function approve(string $spNo, int $status, string $comment = ''): array;
    // $status: 1 同意, 2 驳回
}
```

---

## 4.9 Calendar — 日程

```php
class Calendar extends BaseApi
{
    /**
     * 创建日历
     * POST /cgi-bin/oa/calendar/add
     */
    public function add(array $data): array;
    
    /**
     * 更新日历
     * POST /cgi-bin/oa/calendar/update
     */
    public function update(array $data): array;
    
    /**
     * 获取日历
     * POST /cgi-bin/oa/calendar/get
     */
    public function get(array $calIds): array;
    
    /**
     * 删除日历
     * POST /cgi-bin/oa/calendar/del
     */
    public function del(string $calId): array;
    
    /**
     * 创建日程
     * POST /cgi-bin/oa/schedule/add
     */
    public function scheduleAdd(array $data): array;
    
    /**
     * 更新日程
     * POST /cgi-bin/oa/schedule/update
     */
    public function scheduleUpdate(array $data): array;
    
    /**
     * 获取日程详情
     * POST /cgi-bin/oa/schedule/get
     */
    public function scheduleGet(array $scheduleIds): array;
    
    /**
     * 获取日历下的日程列表
     * POST /cgi-bin/oa/schedule/get_by_calendar
     */
    public function scheduleListByCalendar(string $calId, int $offset = 0, int $limit = 100): array;
    
    /**
     * 取消日程
     * POST /cgi-bin/oa/schedule/del
     */
    public function scheduleDel(string $scheduleId): array;
}
```

---

## 4.10 Meeting — 会议

```php
class Meeting extends BaseApi
{
    /**
     * 创建会议
     * POST /cgi-bin/meeting/create
     */
    public function create(array $data): array;
    
    /**
     * 修改会议
     * POST /cgi-bin/meeting/update
     */
    public function update(array $data): array;
    
    /**
     * 取消会议
     * POST /cgi-bin/meeting/cancel
     */
    public function cancel(string $meetingId): array;
    
    /**
     * 获取会议详情
     * POST /cgi-bin/meeting/get_info
     */
    public function getInfo(string $meetingId): array;
    
    /**
     * 获取用户所有会议列表
     * POST /cgi-bin/meeting/get_user_meetingid
     */
    public function getUserMeetingId(string $userid, string $startTime, string $endTime): array;
}
```

---

## 4.11 Checkin — 打卡

```php
class Checkin extends BaseApi
{
    /**
     * 获取打卡规则
     * POST /cgi-bin/checkin/getcheckinoption
     */
    public function getCheckinOption(array $data): array;
    
    /**
     * 获取打卡数据
     * POST /cgi-bin/checkin/getcheckindata
     */
    public function getCheckinData(array $data): array;
    // $data: ['opencheckindatatype'=>3, 'starttime'=>'', 'endtime'=>'', 'useridlist'=>[]]
    
    /**
     * 获取打卡日报数据
     * POST /cgi-bin/checkin/getcheckin_day_data
     */
    public function getCheckinDayData(array $data): array;
    
    /**
     * 获取打卡月报数据
     * POST /cgi-bin/checkin/getcheckin_month_data
     */
    public function getCheckinMonthData(array $data): array;
    
    /**
     * 获取排班列表
     * POST /cgi-bin/checkin/getcheckinschedulist
     */
    public function getScheduleList(array $data): array;
    
    /**
     * 设置排班
     * POST /cgi-bin/checkin/setcheckinschedulist
     */
    public function setScheduleList(array $data): array;
}
```

---

## 4.12 Kf — 微信客服

```php
class Kf extends BaseApi
{
    /**
     * 获取客服账号列表
     * GET /cgi-bin/kf/account/list
     */
    public function accountList(int $offset = 0, int $limit = 100): array;
    
    /**
     * 创建客服账号
     * POST /cgi-bin/kf/account/add
     */
    public function accountAdd(array $data): array;
    
    /**
     * 更新客服账号
     * POST /cgi-bin/kf/account/update
     */
    public function accountUpdate(array $data): array;
    
    /**
     * 删除客服账号
     * POST /cgi-bin/kf/account/del
     */
    public function accountDel(string $openKfId): array;
    
    /**
     * 获取接待人员列表
     * GET /cgi-bin/kf/servicer/list?open_kfid=xxx
     */
    public function servicerList(string $openKfId): array;
    
    /**
     * 添加接待人员
     * POST /cgi-bin/kf/servicer/add
     */
    public function servicerAdd(string $openKfId, array $userids): array;
    
    /**
     * 删除接待人员
     * POST /cgi-bin/kf/servicer/del
     */
    public function servicerDel(string $openKfId, array $userids): array;
    
    /**
     * 获取会话内容
     * POST /cgi-bin/kf/sync_msg
     */
    public function syncMsg(array $data): array;
    
    /**
     * 发送消息
     * POST /cgi-bin/kf/send_msg
     */
    public function sendMsg(array $data): array;
}
```

---

## 4.13 MsgAudit — 会话内容存档

```php
class MsgAudit extends BaseApi
{
    /**
     * 获取机器人信息（需先开通会话存档功能）
     * GET /cgi-bin/msgaudit/get_robot_info
     */
    public function getRobotInfo(): array;
    
    /**
     * 获取会话内容存档开启状态
     * GET /cgi-bin/msgaudit/get_permit_user_list
     */
    public function getPermitUserList(): array;
    
    /**
     * 获取会话内容
     * POST /cgi-bin/msgaudit/get_chat_data
     */
    public function getChatData(array $data): array;
    // $data: ['seq'=>0, 'limit'=>100, 'proxy'=>'', 'passwd'=>'']
    
    /**
     * 处理会话内容（解密消息等）
     * 注意：会话存档的解密需要用 RSA 私钥，与企业微信回调加解密不同！
     */
    public function decryptChatData(string $encryptRandomKey, string $encryptChatMsg): string;
}
```

---

## 4.14 Wedrive — 微盘

```php
class Wedrive extends BaseApi
{
    // ==================== 空间管理 ====================

    /**
     * 创建空间
     * POST /cgi-bin/wedrive/space_create
     */
    public function spaceCreate(string $name, array $adminUserids = [], array $options = []): array;

    /**
     * 重命名空间
     * POST /cgi-bin/wedrive/space_rename
     */
    public function spaceRename(string $spaceId, string $newName): array;

    /**
     * 解散空间
     * POST /cgi-bin/wedrive/space_dismiss
     */
    public function spaceDismiss(string $spaceId): array;

    /**
     * 获取空间信息
     * POST /cgi-bin/wedrive/space_info
     */
    public function spaceInfo(string $spaceId): array;

    /**
     * 获取空间列表
     * POST /cgi-bin/wedrive/space_list
     */
    public function spaceList(): array;

    /**
     * 空间安全设置
     * POST /cgi-bin/wedrive/space_setting
     */
    public function spaceSetting(string $spaceId, array $settings): array;

    // ==================== 空间权限管理 ====================

    /** 添加空间成员 POST /cgi-bin/wedrive/space_acl_add */
    public function spaceAclAdd(string $spaceId, array $authInfo): array;

    /** 移除空间成员 POST /cgi-bin/wedrive/space_acl_del */
    public function spaceAclDel(string $spaceId, array $authInfo): array;

    /** 获取空间成员列表 POST /cgi-bin/wedrive/space_acl_list */
    public function spaceAclList(string $spaceId): array;

    // ==================== 文件管理 ====================

    /**
     * 获取文件列表
     * POST /cgi-bin/wedrive/get_file_list
     */
    public function getFileList(string $spaceId, string $fatherId = '', int $fileType = 6, int $start = 0, int $limit = 100): array;
    // $fileType: 1-文件夹 2-文件 3-文档 4-表格 5-收集表 6-全部 10-智能表

    /** 获取文件信息 POST /cgi-bin/wedrive/get_file_info */
    public function getFileInfo(string $fileId): array;

    /** 上传文件 POST /cgi-bin/wedrive/file_upload */
    public function fileUpload(string $spaceId, string $filePath, string $fatherId = '', array $options = []): array;

    /** 下载文件 POST /cgi-bin/wedrive/file_download */
    public function fileDownload(string $fileId, array $options = []): array;

    /**
     * 新建文件夹/文档
     * POST /cgi-bin/wedrive/file_create
     */
    public function fileCreate(string $spaceId, string $name, int $fileType = 1, string $fatherId = ''): array;

    /** 重命名文件 POST /cgi-bin/wedrive/file_rename */
    public function fileRename(string $fileId, string $newName): array;

    /** 移动文件 POST /cgi-bin/wedrive/file_move */
    public function fileMove(array $fileIds, string $targetSpaceId, string $targetFatherId = ''): array;

    /** 删除文件 POST /cgi-bin/wedrive/file_delete */
    public function fileDelete(array $fileIds): array;

    // ==================== 文件权限管理 ====================

    /** 添加文件成员 POST /cgi-bin/wedrive/file_acl_add */
    public function fileAclAdd(string $fileId, array $authInfo): array;

    /** 删除文件成员 POST /cgi-bin/wedrive/file_acl_del */
    public function fileAclDel(string $fileId, array $authInfo): array;

    /** 获取文件成员列表 POST /cgi-bin/wedrive/file_acl_list */
    public function fileAclList(string $fileId): array;

    // ==================== 分享管理 ====================

    /** 创建文件分享链接 POST /cgi-bin/wedrive/file_share */
    public function fileShareCreate(string $fileId, array $shareSetting = []): array;

    /** 获取文件分享信息 POST /cgi-bin/wedrive/file_share_get */
    public function fileShareGet(string $shareId): array;

    // ==================== 容量管理 ====================

    /** 获取微盘容量信息 POST /cgi-bin/wedrive/get_capacity */
    public function getCapacity(string $spaceId = ''): array;
}
```

---

## 4.15 Wedoc — 文档

```php
class Wedoc extends BaseApi
{
    // ==================== 文档创建 ====================

    /**
     * 创建文档
     * POST /cgi-bin/wedoc/create_doc
     * 
     * @param string $docName   文档名称
     * @param string $docType   文档类型：doc/sheet/smartsheet/collection/form
     * @param string $fatherId  父目录 ID（可选）
     * @param array  $adminUsers 管理员 userid 列表（可选）
     */
    public function createDoc(string $docName, string $docType, string $fatherId = '', array $adminUsers = []): array;

    /** 创建智能表格（文档格式: smartsheet） */
    public function createSmartsheet(string $docName, string $spaceId, string $fatherId = '', array $adminUsers = []): array;

    // ==================== 文档基础操作 ====================

    /** 获取文档基础信息 POST /cgi-bin/wedoc/document/get */
    public function getDocument(string $docid): array;

    /** 重命名文档 POST /cgi-bin/wedoc/document/rename */
    public function renameDocument(string $docid, string $newName): array;

    /** 删除文档 POST /cgi-bin/wedoc/document/delete */
    public function deleteDocument(string $docid): array;

    /**
     * 获取文档列表
     * POST /cgi-bin/wedoc/get_doc_list
     */
    public function getDocumentList(string $spaceId = '', string $fatherId = '', int $offset = 0, int $limit = 50): array;

    // ==================== 文档内容编辑 ====================

    /** 编辑文档内容 POST /cgi-bin/wedoc/document/edit */
    public function editDocument(string $docid, array $editData): array;

    /** 获取文档数据 POST /cgi-bin/wedoc/document/get_data */
    public function getDocumentData(string $docid, string $sheetId = ''): array;

    // ==================== 文档分享 ====================

    /** 分享文档 POST /cgi-bin/wedoc/doc_share */
    public function shareDoc(string $docid, array $shareSetting = []): array;

    /** 获取分享信息 POST /cgi-bin/wedoc/doc_share_get */
    public function getShareInfo(string $shareId): array;

    // ==================== 文档权限管理 ====================

    /** 获取文档权限信息 POST /cgi-bin/wedoc/doc_get_auth */
    public function getDocAuth(string $docid): array;

    /** 修改文档成员与权限 POST /cgi-bin/wedoc/doc_mod_auth */
    public function modDocAuth(string $docid, array $members): array;

    /** 修改文档加入规则 POST /cgi-bin/wedoc/mod_doc_join_rule */
    public function modDocJoinRule(string $docid, array $joinRule): array;

    /** 修改文档安全设置 POST /cgi-bin/wedoc/doc_safety_setting */
    public function modDocSafety(string $docid, array $settings): array;

    /** 管理智能表格内容权限 POST /cgi-bin/wedoc/smartsheet_mod_auth */
    public function smartsheetModAuth(string $docid, string $sheetId, array $authSetting): array;
}
```

---

## 4.16 其他预留模块

| 模块 | 文件 | 主要内容 |
|------|------|------|
| 直播 | `Live.php` | 创建直播、获取观看数据 |
| 健康上报 | `Health.php` | 获取健康数据、上报统计 |
| 企业支付 | `Pay.php` | 发红包、付款、退款 |

---

> 下一章节：[05 - 管理后台设计](./05-admin-backend.md)

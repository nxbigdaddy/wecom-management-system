<?php
namespace Wxwork\Api;

/**
 * 客户联系 API 模块 (SCRM)
 * 
 * 覆盖：客户管理、客户群管理、标签管理、离职继承、在职继承、
 *       朋友圈、群发消息、统计、欢迎语、联系我、进群方式
 */
class ExternalContact extends BaseApi
{
    // ==================== 客户管理 ====================
    
    /** 获取客户列表 GET /cgi-bin/externalcontact/list */
    public function customerList(string $userid): array
    {
        return $this->result($this->get('/cgi-bin/externalcontact/list', ['userid' => $userid]));
    }

    /** 获取客户详情 GET /cgi-bin/externalcontact/get */
    public function customerGet(string $externalUserid): array
    {
        return $this->result($this->get('/cgi-bin/externalcontact/get', ['external_userid' => $externalUserid]));
    }

    /** 批量获取客户详情 POST /cgi-bin/externalcontact/get_by_user */
    public function getByUser(string $userid, string $cursor = '', int $limit = 100): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/get_by_user', [
            'userid' => $userid, 'cursor' => $cursor, 'limit' => $limit
        ]));
    }

    /** 修改客户备注 POST /cgi-bin/externalcontact/remark */
    public function remark(array $data): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/remark', $data));
    }

    /** 获取离职成员的客户列表 POST /cgi-bin/externalcontact/get_unassigned_list */
    public function getUnassignedList(int $pageId = 0, int $pageSize = 100): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/get_unassigned_list', [
            'page_id' => $pageId, 'page_size' => $pageSize
        ]));
    }

    /** 离职继承 POST /cgi-bin/externalcontact/transfer */
    public function transfer(string $handoverUserid, string $takeoverUserid, array $externalUserids): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/transfer', [
            'handover_userid' => $handoverUserid,
            'takeover_userid' => $takeoverUserid,
            'external_userid' => $externalUserids,
        ]));
    }

    /** 查询离职继承结果 POST /cgi-bin/externalcontact/get_transfer_result */
    public function getTransferResult(string $handoverUserid, string $takeoverUserid): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/get_transfer_result', [
            'handover_userid' => $handoverUserid,
            'takeover_userid' => $takeoverUserid,
        ]));
    }

    /** 在职继承 POST /cgi-bin/externalcontact/transfer_customer */
    public function transferCustomer(string $handoverUserid, string $takeoverUserid, array $externalUserids): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/transfer_customer', [
            'handover_userid' => $handoverUserid,
            'takeover_userid' => $takeoverUserid,
            'external_userid' => $externalUserids,
        ]));
    }

    /** 查询在职继承结果 POST /cgi-bin/externalcontact/get_transfer_customer_result */
    public function getTransferCustomerResult(string $handoverUserid, string $takeoverUserid): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/get_transfer_customer_result', [
            'handover_userid' => $handoverUserid,
            'takeover_userid' => $takeoverUserid,
        ]));
    }

    // ==================== 客户群管理 ====================

    /** 获取客户群列表 POST /cgi-bin/externalcontact/groupchat/list */
    public function groupChatList(array $params = []): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/groupchat/list', $params));
    }

    /** 获取客户群详情 POST /cgi-bin/externalcontact/groupchat/get */
    public function groupChatGet(string $chatId): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/groupchat/get', ['chat_id' => $chatId]));
    }

    /** openGid转chatId POST /cgi-bin/externalcontact/opengid_to_chatid */
    public function openGidToChatId(string $openGid): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/opengid_to_chatid', ['opengid' => $openGid]));
    }

    // ==================== 企业标签 ====================

    /** 获取企业标签库 POST /cgi-bin/externalcontact/get_corp_tag_list */
    public function getCorpTagList(array $tagIds = []): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/get_corp_tag_list', ['tag_id' => $tagIds]));
    }

    /** 添加企业标签 POST /cgi-bin/externalcontact/add_corp_tag */
    public function addCorpTag(array $data): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/add_corp_tag', $data));
    }

    /** 编辑企业标签 POST /cgi-bin/externalcontact/edit_corp_tag */
    public function editCorpTag(string $id, string $name, int $order = 1): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/edit_corp_tag', [
            'id' => $id, 'name' => $name, 'order' => $order
        ]));
    }

    /** 删除企业标签 POST /cgi-bin/externalcontact/del_corp_tag */
    public function delCorpTag(array $tagIds, array $groupIds = []): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/del_corp_tag', [
            'tag_id' => $tagIds, 'group_id' => $groupIds
        ]));
    }

    /** 给客户打标签 POST /cgi-bin/externalcontact/mark_tag */
    public function markTag(string $userid, string $externalUserid, array $addTag, array $removeTag = []): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/mark_tag', [
            'userid' => $userid,
            'external_userid' => $externalUserid,
            'add_tag' => $addTag,
            'remove_tag' => $removeTag,
        ]));
    }

    // ==================== 客户朋友圈 ====================

    /** 获取朋友圈列表 GET /cgi-bin/externalcontact/get_moment_list */
    public function getMomentList(string $startTime, string $endTime, string $cursor = '', int $limit = 100): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/get_moment_list', [
            'start_time' => $startTime, 'end_time' => $endTime,
            'cursor' => $cursor, 'limit' => $limit
        ]));
    }

    /** 创建朋友圈任务 POST /cgi-bin/externalcontact/add_moment_task */
    public function addMomentTask(array $data): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/add_moment_task', $data));
    }

    /** 获取朋友圈任务结果 GET /cgi-bin/externalcontact/get_moment_task_result */
    public function getMomentTaskResult(string $momentId): array
    {
        return $this->result($this->get('/cgi-bin/externalcontact/get_moment_task_result', ['moment_id' => $momentId]));
    }

    // ==================== 群发消息 ====================

    /** 创建企业群发 POST /cgi-bin/externalcontact/add_msg_template */
    public function addMsgTemplate(array $data): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/add_msg_template', $data));
    }

    /** 获取群发记录列表 POST /cgi-bin/externalcontact/get_groupmsg_list_v2 */
    public function getGroupMsgListV2(array $params): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/get_groupmsg_list_v2', $params));
    }

    /** 获取群发发送结果 POST /cgi-bin/externalcontact/get_groupmsg_task */
    public function getGroupMsgTask(string $msgid, int $limit = 1000, string $cursor = ''): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/get_groupmsg_task', [
            'msgid' => $msgid, 'limit' => $limit, 'cursor' => $cursor
        ]));
    }

    // ==================== 统计 ====================

    /** 获取联系客户统计 POST /cgi-bin/externalcontact/get_user_behavior_data */
    public function getUserBehaviorData(array $data): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/get_user_behavior_data', $data));
    }

    /** 获取群聊统计 POST /cgi-bin/externalcontact/groupchat/statistic */
    public function groupChatStatistic(array $data): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/groupchat/statistic', $data));
    }

    // ==================== 欢迎语 ====================

    /** 发送新客户欢迎语 POST /cgi-bin/externalcontact/send_welcome_msg */
    public function sendWelcomeMsg(array $data): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/send_welcome_msg', $data));
    }

    // ==================== 进群方式 ====================

    /** 添加进群方式 POST /cgi-bin/externalcontact/groupchat/add_join_way */
    public function addJoinWay(array $data): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/groupchat/add_join_way', $data));
    }

    /** 获取进群方式 POST /cgi-bin/externalcontact/groupchat/get_join_way */
    public function getJoinWay(string $configId): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/groupchat/get_join_way', ['config_id' => $configId]));
    }

    /** 更新进群方式 POST /cgi-bin/externalcontact/groupchat/update_join_way */
    public function updateJoinWay(array $data): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/groupchat/update_join_way', $data));
    }

    /** 删除进群方式 POST /cgi-bin/externalcontact/groupchat/del_join_way */
    public function delJoinWay(string $configId): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/groupchat/del_join_way', ['config_id' => $configId]));
    }

    // ==================== 联系我 ====================

    /** 获取联系我方式 POST /cgi-bin/externalcontact/get_contact_way */
    public function getContactWay(string $configId): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/get_contact_way', ['config_id' => $configId]));
    }

    /** 新增联系我方式 POST /cgi-bin/externalcontact/add_contact_way */
    public function addContactWay(array $data): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/add_contact_way', $data));
    }

    /** 更新联系我方式 POST /cgi-bin/externalcontact/update_contact_way */
    public function updateContactWay(array $data): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/update_contact_way', $data));
    }

    /** 删除联系我方式 POST /cgi-bin/externalcontact/del_contact_way */
    public function delContactWay(string $configId): array
    {
        return $this->result($this->post('/cgi-bin/externalcontact/del_contact_way', ['config_id' => $configId]));
    }
}

<?php
namespace Wxwork\Api;

/**
 * 会话内容存档 API 模块
 */
class MsgAudit extends BaseApi
{
    /** 获取机器人信息 GET /cgi-bin/msgaudit/get_robot_info */
    public function getRobotInfo(): array
    {
        return $this->result($this->get('/cgi-bin/msgaudit/get_robot_info'));
    }

    /** 获取允许存档成员列表 GET /cgi-bin/msgaudit/get_permit_user_list */
    public function getPermitUserList(): array
    {
        return $this->result($this->get('/cgi-bin/msgaudit/get_permit_user_list'));
    }

    /** 获取会话内容 POST /cgi-bin/msgaudit/get_chat_data */
    public function getChatData(array $data): array
    {
        return $this->result($this->post('/cgi-bin/msgaudit/get_chat_data', $data));
    }

    /** 获取单成员同意状态 GET /cgi-bin/msgaudit/check_single_agree */
    public function checkSingleAgree(array $data): array
    {
        return $this->result($this->post('/cgi-bin/msgaudit/check_single_agree', $data));
    }
}

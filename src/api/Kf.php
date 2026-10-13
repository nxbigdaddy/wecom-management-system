<?php
namespace Wxwork\Api;

/**
 * 微信客服 API 模块
 */
class Kf extends BaseApi
{
    /** 获取客服账号列表 GET /cgi-bin/kf/account/list */
    public function accountList(int $offset = 0, int $limit = 100): array
    {
        return $this->result($this->get('/cgi-bin/kf/account/list', ['offset' => $offset, 'limit' => $limit]));
    }

    /** 创建客服账号 POST /cgi-bin/kf/account/add */
    public function accountAdd(array $data): array
    {
        return $this->result($this->post('/cgi-bin/kf/account/add', $data));
    }

    /** 更新客服账号 POST /cgi-bin/kf/account/update */
    public function accountUpdate(array $data): array
    {
        return $this->result($this->post('/cgi-bin/kf/account/update', $data));
    }

    /** 删除客服账号 POST /cgi-bin/kf/account/del */
    public function accountDel(string $openKfId): array
    {
        return $this->result($this->post('/cgi-bin/kf/account/del', ['open_kfid' => $openKfId]));
    }

    /** 获取接待人员列表 GET /cgi-bin/kf/servicer/list */
    public function servicerList(string $openKfId): array
    {
        return $this->result($this->get('/cgi-bin/kf/servicer/list', ['open_kfid' => $openKfId]));
    }

    /** 添加接待人员 POST /cgi-bin/kf/servicer/add */
    public function servicerAdd(string $openKfId, array $userids): array
    {
        return $this->result($this->post('/cgi-bin/kf/servicer/add', [
            'open_kfid' => $openKfId, 'userid_list' => $userids
        ]));
    }

    /** 删除接待人员 POST /cgi-bin/kf/servicer/del */
    public function servicerDel(string $openKfId, array $userids): array
    {
        return $this->result($this->post('/cgi-bin/kf/servicer/del', [
            'open_kfid' => $openKfId, 'userid_list' => $userids
        ]));
    }

    /** 获取会话内容 POST /cgi-bin/kf/sync_msg */
    public function syncMsg(array $data): array
    {
        return $this->result($this->post('/cgi-bin/kf/sync_msg', $data));
    }

    /** 发送消息 POST /cgi-bin/kf/send_msg */
    public function sendMsg(array $data): array
    {
        return $this->result($this->post('/cgi-bin/kf/send_msg', $data));
    }
}

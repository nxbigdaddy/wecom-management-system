<?php
namespace Wxwork\Api;

/**
 * 应用管理 API 模块
 * 
 * 覆盖：应用信息、自定义菜单
 */
class AppManage extends BaseApi
{
    /** 获取应用列表 GET /cgi-bin/agent/list */
    public function agentList(): array
    {
        return $this->result($this->get('/cgi-bin/agent/list'));
    }

    /** 获取应用详情 GET /cgi-bin/agent/get */
    public function agentGet(int $agentId): array
    {
        return $this->result($this->get('/cgi-bin/agent/get', ['agentid' => $agentId]));
    }

    /** 设置应用信息 POST /cgi-bin/agent/set */
    public function agentSet(array $data): array
    {
        return $this->result($this->post('/cgi-bin/agent/set', $data));
    }

    /** 创建菜单 POST /cgi-bin/menu/create */
    public function menuCreate(int $agentId, array $buttons): array
    {
        return $this->result($this->post('/cgi-bin/menu/create', [
            'agentid' => $agentId, 'button' => $buttons
        ], false));
    }

    /** 获取菜单 GET /cgi-bin/menu/get */
    public function menuGet(int $agentId): array
    {
        return $this->result($this->get('/cgi-bin/menu/get', ['agentid' => $agentId]));
    }

    /** 删除菜单 GET /cgi-bin/menu/delete */
    public function menuDelete(int $agentId): array
    {
        return $this->result($this->get('/cgi-bin/menu/delete', ['agentid' => $agentId]));
    }
}

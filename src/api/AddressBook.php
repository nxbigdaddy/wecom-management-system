<?php
namespace Wxwork\Api;

/**
 * 通讯录管理 API 模块
 * 
 * 覆盖：部门管理、成员管理、标签管理、异步批量任务
 */
class AddressBook extends BaseApi
{
    // ==================== 部门管理 ====================

    /** 创建部门 POST /cgi-bin/department/create */
    public function departmentCreate(array $data): array
    {
        return $this->result($this->post('/cgi-bin/department/create', $data));
    }

    /** 更新部门 POST /cgi-bin/department/update */
    public function departmentUpdate(array $data): array
    {
        return $this->result($this->post('/cgi-bin/department/update', $data));
    }

    /** 删除部门 GET /cgi-bin/department/delete?id= */
    public function departmentDelete(int $deptId): array
    {
        return $this->result($this->get('/cgi-bin/department/delete', ['id' => $deptId]));
    }

    /** 获取部门列表 GET /cgi-bin/department/list */
    public function departmentList(int $parentId = 0): array
    {
        $query = $parentId ? ['id' => $parentId] : [];
        return $this->result($this->get('/cgi-bin/department/list', $query));
    }

    /** 获取单个部门 GET /cgi-bin/department/get?id= */
    public function departmentGet(int $deptId): array
    {
        return $this->result($this->get('/cgi-bin/department/get', ['id' => $deptId]));
    }

    // ==================== 成员管理 ====================

    /** 创建成员 POST /cgi-bin/user/create */
    public function userCreate(array $data): array
    {
        return $this->result($this->post('/cgi-bin/user/create', $data));
    }

    /** 更新成员 POST /cgi-bin/user/update */
    public function userUpdate(array $data): array
    {
        return $this->result($this->post('/cgi-bin/user/update', $data));
    }

    /** 删除成员 GET /cgi-bin/user/delete?userid= */
    public function userDelete(string $userid): array
    {
        return $this->result($this->get('/cgi-bin/user/delete', ['userid' => $userid]));
    }

    /** 批量删除成员 POST /cgi-bin/user/batchdelete */
    public function userBatchDelete(array $userids): array
    {
        return $this->result($this->post('/cgi-bin/user/batchdelete', ['useridlist' => $userids]));
    }

    /** 获取成员详情 GET /cgi-bin/user/get?userid= */
    public function userGet(string $userid): array
    {
        return $this->result($this->get('/cgi-bin/user/get', ['userid' => $userid]));
    }

    /** 获取部门成员(简单) GET /cgi-bin/user/simplelist */
    public function userSimpleList(int $deptId, bool $fetchChild = false): array
    {
        return $this->result($this->get('/cgi-bin/user/simplelist', [
            'department_id' => $deptId,
            'fetch_child' => $fetchChild ? 1 : 0
        ]));
    }

    /** 获取部门成员(详情) GET /cgi-bin/user/list */
    public function userList(int $deptId, bool $fetchChild = false): array
    {
        return $this->result($this->get('/cgi-bin/user/list', [
            'department_id' => $deptId,
            'fetch_child' => $fetchChild ? 1 : 0
        ]));
    }

    /** userid转openid POST /cgi-bin/user/convert_to_openid */
    public function userConvertToOpenId(string $userid): array
    {
        return $this->result($this->post('/cgi-bin/user/convert_to_openid', ['userid' => $userid]));
    }

    /** openid转userid POST /cgi-bin/user/convert_to_userid */
    public function userConvertToUserId(string $openUserid): array
    {
        return $this->result($this->post('/cgi-bin/user/convert_to_userid', ['openid' => $openUserid]));
    }

    /** 二次验证 GET /cgi-bin/user/authsucc */
    public function userAuthSucc(string $userid): array
    {
        return $this->result($this->get('/cgi-bin/user/authsucc', ['userid' => $userid]));
    }

    /** 获取加入企业二维码 GET /cgi-bin/user/get_join_qrcode */
    public function userGetJoinQrcode(int $sizeType = 1): array
    {
        return $this->result($this->get('/cgi-bin/user/get_join_qrcode', ['size_type' => $sizeType]));
    }

    /** 获取成员ID列表 POST /cgi-bin/user/list_id */
    public function userListId(?string $cursor = null, int $limit = 10000): array
    {
        $data = ['limit' => $limit];
        if ($cursor !== null) $data['cursor'] = $cursor;
        return $this->result($this->post('/cgi-bin/user/list_id', $data));
    }

    /** 手机号获取userid POST /cgi-bin/user/getuserid */
    public function userGetUserIdByMobile(string $mobile): array
    {
        return $this->result($this->post('/cgi-bin/user/getuserid', ['mobile' => $mobile]));
    }

    // ==================== 标签管理 ====================

    /** 创建标签 POST /cgi-bin/tag/create */
    public function tagCreate(string $tagName, ?int $tagId = null): array
    {
        $data = ['tagname' => $tagName];
        if ($tagId !== null) $data['tagid'] = $tagId;
        return $this->result($this->post('/cgi-bin/tag/create', $data));
    }

    /** 更新标签 POST /cgi-bin/tag/update */
    public function tagUpdate(int $tagId, string $tagName): array
    {
        return $this->result($this->post('/cgi-bin/tag/update', ['tagid' => $tagId, 'tagname' => $tagName]));
    }

    /** 删除标签 GET /cgi-bin/tag/delete */
    public function tagDelete(int $tagId): array
    {
        return $this->result($this->get('/cgi-bin/tag/delete', ['tagid' => $tagId]));
    }

    /** 获取标签列表 GET /cgi-bin/tag/list */
    public function tagList(): array
    {
        return $this->result($this->get('/cgi-bin/tag/list'));
    }

    /** 获取标签成员 GET /cgi-bin/tag/get */
    public function tagGet(int $tagId): array
    {
        return $this->result($this->get('/cgi-bin/tag/get', ['tagid' => $tagId]));
    }

    /** 增加标签成员 POST /cgi-bin/tag/addtagusers */
    public function tagAddUsers(int $tagId, array $userids, array $partyids = []): array
    {
        $data = ['tagid' => $tagId];
        if (!empty($userids)) $data['userlist'] = $userids;
        if (!empty($partyids)) $data['partylist'] = $partyids;
        return $this->result($this->post('/cgi-bin/tag/addtagusers', $data));
    }

    /** 删除标签成员 POST /cgi-bin/tag/deltagusers */
    public function tagDeleteUsers(int $tagId, array $userids, array $partyids = []): array
    {
        $data = ['tagid' => $tagId];
        if (!empty($userids)) $data['userlist'] = $userids;
        if (!empty($partyids)) $data['partylist'] = $partyids;
        return $this->result($this->post('/cgi-bin/tag/deltagusers', $data));
    }

    // ==================== 异步批量任务 ====================

    /** 增量更新成员 POST /cgi-bin/batch/syncuser */
    public function batchSyncUser(array $data): array
    {
        return $this->result($this->post('/cgi-bin/batch/syncuser', $data));
    }

    /** 全量覆盖成员 POST /cgi-bin/batch/replaceuser */
    public function batchReplaceUser(array $data): array
    {
        return $this->result($this->post('/cgi-bin/batch/replaceuser', $data));
    }

    /** 全量覆盖部门 POST /cgi-bin/batch/replaceparty */
    public function batchReplaceParty(array $data): array
    {
        return $this->result($this->post('/cgi-bin/batch/replaceparty', $data));
    }

    /** 获取异步任务结果 GET /cgi-bin/batch/getresult */
    public function batchGetResult(string $jobId): array
    {
        return $this->result($this->get('/cgi-bin/batch/getresult', ['jobid' => $jobId]));
    }
}

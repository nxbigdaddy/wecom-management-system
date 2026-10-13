<?php
namespace Wxwork\Api;

/**
 * 文档管理 API 模块
 * 
 * 覆盖：文档创建（含智能表格）、文档 CRUD、文档内容编辑、
 *       文档分享、文档权限设置、文档安全设置
 */
class Wedoc extends BaseApi
{
    // ==================== 文档创建 ====================

    /**
     * 创建文档 POST /cgi-bin/wedoc/create_doc
     * 
     * @param string $docName   文档名称
     * @param string $docType   文档类型：doc(文档)/sheet(表格)/smartsheet(智能表格)/collection(收集表)/form(收集表)
     * @param string $fatherId  父目录 ID（可选）
     * @param array  $adminUsers 管理员 userid 列表（可选）
     */
    public function createDoc(string $docName, string $docType, string $fatherId = '', array $adminUsers = []): array
    {
        $data = [
            'doc_name' => $docName,
            'doc_type' => $docType,
        ];
        if ($fatherId) $data['fatherid'] = $fatherId;
        if (!empty($adminUsers)) $data['admin_users'] = $adminUsers;
        return $this->result($this->post('/cgi-bin/wedoc/create_doc', $data));
    }

    /** 创建智能表格（文档格式: smartsheet）POST /cgi-bin/wedoc/create_smartsheet */
    public function createSmartsheet(string $docName, string $spaceId, string $fatherId = '', array $adminUsers = []): array
    {
        return $this->createDoc($docName, 'smartsheet', $fatherId, $adminUsers);
    }

    // ==================== 文档基础操作 ====================

    /** 获取文档基础信息 POST /cgi-bin/wedoc/document/get */
    public function getDocument(string $docid): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/document/get', ['docid' => $docid]));
    }

    /** 重命名文档 POST /cgi-bin/wedoc/document/rename */
    public function renameDocument(string $docid, string $newName): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/document/rename', [
            'docid'   => $docid,
            'new_name' => $newName,
        ]));
    }

    /** 删除文档 POST /cgi-bin/wedoc/document/delete */
    public function deleteDocument(string $docid): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/document/delete', ['docid' => $docid]));
    }

    /**
     * 获取文档列表 POST /cgi-bin/wedoc/get_doc_list
     * 
     * @param string $spaceId  空间 ID（可选）
     * @param string $fatherId 父目录 ID（可选）
     * @param int    $offset   偏移量
     * @param int    $limit    每页数量（最大 100）
     */
    public function getDocumentList(string $spaceId = '', string $fatherId = '', int $offset = 0, int $limit = 50): array
    {
        $data = ['offset' => $offset, 'limit' => $limit];
        if ($spaceId) $data['spaceid'] = $spaceId;
        if ($fatherId) $data['fatherid'] = $fatherId;
        return $this->result($this->post('/cgi-bin/wedoc/get_doc_list', $data));
    }

    // ==================== 文档内容编辑 ====================

    /**
     * 编辑文档内容 POST /cgi-bin/wedoc/document/edit
     * 
     * @param string $docid   文档 ID
     * @param array  $editData 编辑数据（按文档类型不同，通常包含 version 和 operations）
     */
    public function editDocument(string $docid, array $editData): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/document/edit', array_merge([
            'docid' => $docid,
        ], $editData)));
    }

    /**
     * 获取文档数据 POST /cgi-bin/wedoc/document/get_data
     * 
     * @param string $docid   文档 ID
     * @param string $sheetId 子表 ID（可选，仅表格类文档需要）
     */
    public function getDocumentData(string $docid, string $sheetId = ''): array
    {
        $data = ['docid' => $docid];
        if ($sheetId) $data['sheet_id'] = $sheetId;
        return $this->result($this->post('/cgi-bin/wedoc/document/get_data', $data));
    }

    // ==================== 文档分享 ====================

    /**
     * 分享文档 POST /cgi-bin/wedoc/doc_share
     * 
     * @param string $docid        文档 ID
     * @param array  $shareSetting 分享设置（perm/open_range/share_time/pwd 等）
     */
    public function shareDoc(string $docid, array $shareSetting = []): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/doc_share', array_merge([
            'docid' => $docid,
        ], $shareSetting)));
    }

    /**
     * 获取分享信息 POST /cgi-bin/wedoc/doc_share_get
     * 
     * @param string $shareId 分享 ID
     */
    public function getShareInfo(string $shareId): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/doc_share_get', [
            'share_id' => $shareId,
        ]));
    }

    // ==================== 文档权限管理 ====================

    /** 获取文档权限信息 POST /cgi-bin/wedoc/doc_get_auth */
    public function getDocAuth(string $docid): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/doc_get_auth', [
            'docid' => $docid,
        ]));
    }

    /**
     * 修改文档成员与权限 POST /cgi-bin/wedoc/doc_mod_auth
     * 
     * @param string $docid   文档 ID
     * @param array  $members 成员权限列表 [{userid, auth, type}, ...]
     */
    public function modDocAuth(string $docid, array $members): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/doc_mod_auth', [
            'docid'    => $docid,
            'members'  => $members,
        ]));
    }

    /**
     * 修改文档加入规则 POST /cgi-bin/wedoc/mod_doc_join_rule
     * 
     * @param string $docid    文档 ID
     * @param array  $joinRule 加入规则配置
     */
    public function modDocJoinRule(string $docid, array $joinRule): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/mod_doc_join_rule', array_merge([
            'docid' => $docid,
        ], $joinRule)));
    }

    /**
     * 修改文档安全设置 POST /cgi-bin/wedoc/doc_safety_setting
     * 
     * @param string $docid   文档 ID
     * @param array  $settings 安全设置（watermark/allow_copy/allow_print 等）
     */
    public function modDocSafety(string $docid, array $settings): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/doc_safety_setting', array_merge([
            'docid' => $docid,
        ], $settings)));
    }

    // ==================== 智能表格内容权限 ====================

    /**
     * 管理智能表格内容权限 POST /cgi-bin/wedoc/smartsheet_mod_auth
     * 
     * @param string $docid       文档 ID
     * @param string $sheetId     子表 ID
     * @param array  $authSetting 权限设置
     */
    public function smartsheetModAuth(string $docid, string $sheetId, array $authSetting): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet_mod_auth', array_merge([
            'docid'    => $docid,
            'sheet_id' => $sheetId,
        ], $authSetting)));
    }
}

<?php
namespace Wxwork\Api;

/**
 * 智能表格 API 模块
 * 
 * 覆盖：子表管理、视图管理、字段管理、记录管理、编组管理
 * 
 * @see https://developer.work.weixin.qq.com/document/path/97794
 */
class Smartsheet extends BaseApi
{
    // ==================== 记录管理 ====================

    /**
     * 添加记录
     * POST /cgi-bin/wedoc/smartsheet/add_records
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $records 记录数组
     * @param string $keyType key 类型：FIELD_TITLE 或 FIELD_ID
     * @return array
     * 
     * 示例：
     * $wx->smartsheet->addRecords('DOCID', '123Abc', [
     *     ['values' => ['字段1' => [['type'=>'text', 'text'=>'内容']]]]
     * ]);
     */
    public function addRecords(string $docid, string $sheetId, array $records, string $keyType = 'CELL_VALUE_KEY_TYPE_FIELD_TITLE'): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/add_records', [
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'key_type' => $keyType,
            'records' => $records
        ]));
    }

    /**
     * 查询记录
     * POST /cgi-bin/wedoc/smartsheet/get_records
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $options 可选参数：view_id, record_ids, field_titles, sort, offset, limit, filter_spec
     * @return array
     */
    public function getRecords(string $docid, string $sheetId, array $options = []): array
    {
        $params = array_merge([
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'key_type' => $options['key_type'] ?? 'CELL_VALUE_KEY_TYPE_FIELD_TITLE',
            'offset' => $options['offset'] ?? 0,
            'limit' => $options['limit'] ?? 100
        ], $options);
        
        unset($params['key_type']);
        $params['key_type'] = $options['key_type'] ?? 'CELL_VALUE_KEY_TYPE_FIELD_TITLE';
        
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/get_records', $params));
    }

    /**
     * 更新记录
     * POST /cgi-bin/wedoc/smartsheet/update_records
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $records 需要更新的记录数组（必须包含 record_id）
     * @param string $keyType key 类型
     * @return array
     */
    public function updateRecords(string $docid, string $sheetId, array $records, string $keyType = 'CELL_VALUE_KEY_TYPE_FIELD_TITLE'): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/update_records', [
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'key_type' => $keyType,
            'records' => $records
        ]));
    }

    /**
     * 删除记录
     * POST /cgi-bin/wedoc/smartsheet/delete_records
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $recordIds 记录 ID 数组
     * @return array
     */
    public function deleteRecords(string $docid, string $sheetId, array $recordIds): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/delete_records', [
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'record_ids' => $recordIds
        ]));
    }

    // ==================== 字段管理 ====================

    /**
     * 添加字段
     * POST /cgi-bin/wedoc/smartsheet/add_fields
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $fields 字段数组
     * @return array
     * 
     * 示例：
     * $wx->smartsheet->addFields('DOCID', '123Abc', [
     *     ['field_title' => '新字段', 'field_type' => 'FIELD_TYPE_TEXT']
     * ]);
     */
    public function addFields(string $docid, string $sheetId, array $fields): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/add_fields', [
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'fields' => $fields
        ]));
    }

    /**
     * 查询字段
     * POST /cgi-bin/wedoc/smartsheet/get_fields
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $options 可选参数
     * @return array
     */
    public function getFields(string $docid, string $sheetId, array $options = []): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/get_fields', array_merge([
            'docid' => $docid,
            'sheet_id' => $sheetId
        ], $options)));
    }

    /**
     * 更新字段
     * POST /cgi-bin/wedoc/smartsheet/update_fields
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $fields 需要更新的字段数组
     * @return array
     */
    public function updateFields(string $docid, string $sheetId, array $fields): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/update_fields', [
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'fields' => $fields
        ]));
    }

    /**
     * 删除字段
     * POST /cgi-bin/wedoc/smartsheet/delete_fields
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $fieldIds 字段 ID 数组
     * @return array
     */
    public function deleteFields(string $docid, string $sheetId, array $fieldIds): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/delete_fields', [
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'field_ids' => $fieldIds
        ]));
    }

    // ==================== 子表管理 ====================

    /**
     * 添加子表
     * POST /cgi-bin/wedoc/smartsheet/add_sheet
     * 
     * @param string $docid 文档的 docid
     * @param string $title 子表标题
     * @param array $properties 子表属性（可选）
     * @return array
     */
    public function addSheet(string $docid, string $title, array $properties = []): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/add_sheet', array_merge([
            'docid' => $docid,
            'title' => $title
        ], $properties)));
    }

    /**
     * 删除子表
     * POST /cgi-bin/wedoc/smartsheet/delete_sheet
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @return array
     */
    public function deleteSheet(string $docid, string $sheetId): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/delete_sheet', [
            'docid' => $docid,
            'sheet_id' => $sheetId
        ]));
    }

    /**
     * 更新子表
     * POST /cgi-bin/wedoc/smartsheet/update_sheet
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $properties 子表属性
     * @return array
     */
    public function updateSheet(string $docid, string $sheetId, array $properties): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/update_sheet', array_merge([
            'docid' => $docid,
            'sheet_id' => $sheetId
        ], $properties)));
    }

    /**
     * 查询子表
     * POST /cgi-bin/wedoc/smartsheet/get_sheet
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @return array
     */
    public function getSheet(string $docid, string $sheetId): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/get_sheet', [
            'docid' => $docid,
            'sheet_id' => $sheetId
        ]));
    }

    // ==================== 视图管理 ====================

    /**
     * 添加视图
     * POST /cgi-bin/wedoc/smartsheet/add_view
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $view 视图属性
     * @return array
     */
    public function addView(string $docid, string $sheetId, array $view): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/add_view', [
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'view' => $view
        ]));
    }

    /**
     * 删除视图
     * POST /cgi-bin/wedoc/smartsheet/delete_view
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param string $viewId 视图 ID
     * @return array
     */
    public function deleteView(string $docid, string $sheetId, string $viewId): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/delete_view', [
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'view_id' => $viewId
        ]));
    }

    /**
     * 更新视图
     * POST /cgi-bin/wedoc/smartsheet/update_view
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $view 视图属性（必须包含 view_id）
     * @return array
     */
    public function updateView(string $docid, string $sheetId, array $view): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/update_view', [
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'view' => $view
        ]));
    }

    /**
     * 查询视图
     * POST /cgi-bin/wedoc/smartsheet/get_view
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $options 可选参数
     * @return array
     */
    public function getView(string $docid, string $sheetId, array $options = []): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/get_view', array_merge([
            'docid' => $docid,
            'sheet_id' => $sheetId
        ], $options)));
    }

    // ==================== 编组管理 ====================

    /**
     * 添加编组
     * POST /cgi-bin/wedoc/smartsheet/add_group
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $group 编组属性
     * @return array
     */
    public function addGroup(string $docid, string $sheetId, array $group): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/add_group', [
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'group' => $group
        ]));
    }

    /**
     * 删除编组
     * POST /cgi-bin/wedoc/smartsheet/delete_group
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param string $groupId 编组 ID
     * @return array
     */
    public function deleteGroup(string $docid, string $sheetId, string $groupId): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/delete_group', [
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'group_id' => $groupId
        ]));
    }

    /**
     * 更新编组
     * POST /cgi-bin/wedoc/smartsheet/update_group
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $group 编组属性（必须包含 group_id）
     * @return array
     */
    public function updateGroup(string $docid, string $sheetId, array $group): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/update_group', [
            'docid' => $docid,
            'sheet_id' => $sheetId,
            'group' => $group
        ]));
    }

    /**
     * 获取编组
     * POST /cgi-bin/wedoc/smartsheet/get_group
     * 
     * @param string $docid 文档的 docid
     * @param string $sheetId 子表 ID
     * @param array $options 可选参数
     * @return array
     */
    public function getGroup(string $docid, string $sheetId, array $options = []): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/get_group', array_merge([
            'docid' => $docid,
            'sheet_id' => $sheetId
        ], $options)));
    }

    // ==================== 权限管理 ====================

    /**
     * 管理智能表格内容权限
     * POST /cgi-bin/wedoc/smartsheet/modify_doc_auth
     * 
     * @param string $docid 文档的 docid
     * @param array $auth 权限设置
     * @return array
     */
    public function modifyDocAuth(string $docid, array $auth): array
    {
        return $this->result($this->post('/cgi-bin/wedoc/smartsheet/modify_doc_auth', [
            'docid' => $docid,
            'auth' => $auth
        ]));
    }
}

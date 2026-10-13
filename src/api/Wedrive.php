<?php
namespace Wxwork\Api;

/**
 * 微盘管理 API 模块
 * 
 * 覆盖：空间管理（创建/重命名/解散/查询）、
 *       文件管理（列表/上传/下载/新建/重命名/移动/删除）、
 *       权限管理（空间/文件级别 ACL）、
 *       分享管理、容量查询
 */
class Wedrive extends BaseApi
{
    // ==================== 空间管理 ====================

    /** 创建空间 POST /cgi-bin/wedrive/space_create */
    public function spaceCreate(string $name, array $adminUserids = [], array $options = []): array
    {
        $data = array_merge([
            'space_name' => $name,
            'auth_info'  => [
                'userid' => $adminUserids,
            ],
        ], $options);
        return $this->result($this->post('/cgi-bin/wedrive/space_create', $data));
    }

    /** 重命名空间 POST /cgi-bin/wedrive/space_rename */
    public function spaceRename(string $spaceId, string $newName): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/space_rename', [
            'spaceid'    => $spaceId,
            'space_name' => $newName,
        ]));
    }

    /** 解散空间 POST /cgi-bin/wedrive/space_dismiss */
    public function spaceDismiss(string $spaceId): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/space_dismiss', [
            'spaceid' => $spaceId,
        ]));
    }

    /** 获取空间信息 POST /cgi-bin/wedrive/space_info */
    public function spaceInfo(string $spaceId): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/space_info', [
            'spaceid' => $spaceId,
        ]));
    }

    /** 获取空间列表 POST /cgi-bin/wedrive/space_list */
    public function spaceList(): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/space_list', []));
    }

    /** 空间安全设置 POST /cgi-bin/wedrive/space_setting */
    public function spaceSetting(string $spaceId, array $settings): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/space_setting', array_merge([
            'spaceid' => $spaceId,
        ], $settings)));
    }

    // ==================== 空间权限管理 ====================

    /** 添加空间成员 POST /cgi-bin/wedrive/space_acl_add */
    public function spaceAclAdd(string $spaceId, array $authInfo): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/space_acl_add', [
            'spaceid'   => $spaceId,
            'auth_info' => $authInfo,
        ]));
    }

    /** 移除空间成员 POST /cgi-bin/wedrive/space_acl_del */
    public function spaceAclDel(string $spaceId, array $authInfo): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/space_acl_del', [
            'spaceid'   => $spaceId,
            'auth_info' => $authInfo,
        ]));
    }

    /** 获取空间成员列表 POST /cgi-bin/wedrive/space_acl_list */
    public function spaceAclList(string $spaceId): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/space_acl_list', [
            'spaceid' => $spaceId,
        ]));
    }

    // ==================== 文件管理 ====================

    /** 获取文件列表 POST /cgi-bin/wedrive/get_file_list */
    public function getFileList(string $spaceId, string $fatherId = '', int $fileType = 6, int $start = 0, int $limit = 100): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/get_file_list', [
            'spaceid'  => $spaceId,
            'fatherid' => $fatherId,
            'file_type' => $fileType,
            'start'    => $start,
            'limit'    => $limit,
        ]));
    }

    /** 获取文件信息 POST /cgi-bin/wedrive/get_file_info */
    public function getFileInfo(string $fileId): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/get_file_info', [
            'fileid' => $fileId,
        ]));
    }

    /** 上传文件 POST /cgi-bin/wedrive/file_upload */
    public function fileUpload(string $spaceId, string $filePath, string $fatherId = '', array $options = []): array
    {
        $data = array_merge([
            'spaceid'  => $spaceId,
            'fatherid' => $fatherId,
        ], $options);
        return $this->result($this->upload('/cgi-bin/wedrive/file_upload', $filePath, 'file', $data));
    }

    /** 下载文件 POST /cgi-bin/wedrive/file_download */
    public function fileDownload(string $fileId, array $options = []): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/file_download', array_merge([
            'fileid' => $fileId,
        ], $options)));
    }

    /**
     * 新建文件夹/文档 POST /cgi-bin/wedrive/file_create
     * 
     * @param string $spaceId  空间 ID
     * @param string $name     文件/文件夹名称
     * @param int    $fileType 1-文件夹 3-文档 4-表格
     * @param string $fatherId 父目录 ID（空=根目录）
     */
    public function fileCreate(string $spaceId, string $name, int $fileType = 1, string $fatherId = ''): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/file_create', [
            'spaceid'  => $spaceId,
            'file_name' => $name,
            'file_type' => $fileType,
            'fatherid' => $fatherId,
        ]));
    }

    /** 重命名文件 POST /cgi-bin/wedrive/file_rename */
    public function fileRename(string $fileId, string $newName): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/file_rename', [
            'fileid'   => $fileId,
            'new_name' => $newName,
        ]));
    }

    /** 移动文件 POST /cgi-bin/wedrive/file_move */
    public function fileMove(array $fileIds, string $targetSpaceId, string $targetFatherId = ''): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/file_move', [
            'fileid'          => $fileIds,
            'target_spaceid'  => $targetSpaceId,
            'target_fatherid' => $targetFatherId,
        ]));
    }

    /** 删除文件 POST /cgi-bin/wedrive/file_delete */
    public function fileDelete(array $fileIds): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/file_delete', [
            'fileid' => $fileIds,
        ]));
    }

    // ==================== 文件权限管理 ====================

    /** 添加文件成员 POST /cgi-bin/wedrive/file_acl_add */
    public function fileAclAdd(string $fileId, array $authInfo): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/file_acl_add', [
            'fileid'    => $fileId,
            'auth_info' => $authInfo,
        ]));
    }

    /** 删除文件成员 POST /cgi-bin/wedrive/file_acl_del */
    public function fileAclDel(string $fileId, array $authInfo): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/file_acl_del', [
            'fileid'    => $fileId,
            'auth_info' => $authInfo,
        ]));
    }

    /** 获取文件成员列表 POST /cgi-bin/wedrive/file_acl_list */
    public function fileAclList(string $fileId): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/file_acl_list', [
            'fileid' => $fileId,
        ]));
    }

    // ==================== 分享管理 ====================

    /** 创建文件分享链接 POST /cgi-bin/wedrive/file_share */
    public function fileShareCreate(string $fileId, array $shareSetting = []): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/file_share', array_merge([
            'fileid'  => $fileId,
        ], $shareSetting)));
    }

    /** 获取文件分享信息 POST /cgi-bin/wedrive/file_share_get */
    public function fileShareGet(string $shareId): array
    {
        return $this->result($this->post('/cgi-bin/wedrive/file_share_get', [
            'shareid' => $shareId,
        ]));
    }

    // ==================== 容量管理 ====================

    /** 获取微盘容量信息 POST /cgi-bin/wedrive/get_capacity */
    public function getCapacity(string $spaceId = ''): array
    {
        $data = [];
        if ($spaceId) $data['spaceid'] = $spaceId;
        return $this->result($this->post('/cgi-bin/wedrive/get_capacity', $data));
    }
}

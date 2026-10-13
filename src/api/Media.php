<?php
namespace Wxwork\Api;

/**
 * 素材管理 API 模块
 * 
 * 覆盖：临时素材上传/获取、永久素材管理、上传图片
 * 注意: 方法名加 m_ 前缀避免与 BaseApi 的 get/upload 方法冲突
 */
class Media extends BaseApi
{
    /** 上传临时素材 POST /cgi-bin/media/upload */
    public function uploadMedia(string $filePath, string $type): array
    {
        return $this->result(parent::upload('/cgi-bin/media/upload', $filePath, $type));
    }

    /** 获取临时素材 GET /cgi-bin/media/get */
    public function getMedia(string $mediaId): array
    {
        return $this->result(parent::get('/cgi-bin/media/get', ['media_id' => $mediaId]));
    }

    /** 上传图片(返回URL) POST /cgi-bin/media/uploadimg */
    public function uploadImage(string $filePath): array
    {
        return $this->result(parent::upload('/cgi-bin/media/uploadimg', $filePath, 'image'));
    }

    /** 上传永久素材 POST /cgi-bin/material/add_material */
    public function addMaterial(string $filePath, string $type, array $extra = []): array
    {
        return $this->result(parent::upload('/cgi-bin/material/add_material', $filePath, $type, $extra));
    }

    /** 获取永久素材 POST /cgi-bin/material/get_material */
    public function getMediaMaterial(string $mediaId): array
    {
        return $this->result($this->post('/cgi-bin/material/get_material', ['media_id' => $mediaId]));
    }

    /** 删除永久素材 GET /cgi-bin/material/del_material */
    public function delMaterial(string $mediaId): array
    {
        return $this->result(parent::get('/cgi-bin/material/del_material', ['media_id' => $mediaId]));
    }

    /** 获取素材总数 GET /cgi-bin/material/get_material_count */
    public function getMaterialCount(): array
    {
        return $this->result(parent::get('/cgi-bin/material/get_material_count'));
    }

    /** 分页获取素材列表 POST /cgi-bin/material/batchget_material */
    public function batchGetMaterial(string $type, int $offset = 0, int $count = 20): array
    {
        return $this->result($this->post('/cgi-bin/material/batchget_material', [
            'type' => $type, 'offset' => $offset, 'count' => $count
        ]));
    }
}

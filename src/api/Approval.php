<?php
namespace Wxwork\Api;

/**
 * OA 审批 API 模块
 * 
 * 覆盖：审批模板、审批申请、审批详情、审批操作
 */
class Approval extends BaseApi
{
    /** 获取审批模板详情 POST /cgi-bin/oa/gettemplatedetail */
    public function getTemplateDetail(string $templateId): array
    {
        return $this->result($this->post('/cgi-bin/oa/gettemplatedetail', ['template_id' => $templateId]));
    }

    /** 创建审批申请(代提交) POST /cgi-bin/oa/applyevent */
    public function applyEvent(array $data): array
    {
        return $this->result($this->post('/cgi-bin/oa/applyevent', $data));
    }

    /** 获取审批申请详情 POST /cgi-bin/oa/getapprovaldetail */
    public function getApprovalDetail(string $spNo): array
    {
        return $this->result($this->post('/cgi-bin/oa/getapprovaldetail', ['sp_no' => $spNo]));
    }

    /** 获取审批单号列表 POST /cgi-bin/oa/getapprovalinfo */
    public function getApprovalInfo(array $filters): array
    {
        return $this->result($this->post('/cgi-bin/oa/getapprovalinfo', $filters));
    }

    /** 审批操作(同意/驳回) POST /cgi-bin/oa/approve */
    public function approve(string $spNo, int $status, string $comment = ''): array
    {
        $data = [
            'sp_no'  => $spNo,
            'status' => $status,
        ];
        if ($comment) $data['comment'] = $comment;
        return $this->result($this->post('/cgi-bin/oa/approve', $data));
    }

    /** 复制审批模板 POST /cgi-bin/oa/copytemplate */
    public function copyTemplate(string $templateId): array
    {
        return $this->result($this->post('/cgi-bin/oa/copytemplate', ['template_id' => $templateId]));
    }
}

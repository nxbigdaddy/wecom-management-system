<?php
namespace Wxwork\Api;

/**
 * 会议管理 API 模块
 */
class Meeting extends BaseApi
{
    /** 创建会议 POST /cgi-bin/meeting/create */
    public function create(array $data): array
    {
        return $this->result($this->post('/cgi-bin/meeting/create', $data));
    }

    /** 修改会议 POST /cgi-bin/meeting/update */
    public function update(array $data): array
    {
        return $this->result($this->post('/cgi-bin/meeting/update', $data));
    }

    /** 取消会议 POST /cgi-bin/meeting/cancel */
    public function cancel(string $meetingId): array
    {
        return $this->result($this->post('/cgi-bin/meeting/cancel', ['meetingid' => $meetingId]));
    }

    /** 获取会议详情 POST /cgi-bin/meeting/get_info */
    public function getInfo(string $meetingId): array
    {
        return $this->result($this->post('/cgi-bin/meeting/get_info', ['meetingid' => $meetingId]));
    }

    /** 获取用户会议列表 POST /cgi-bin/meeting/get_user_meetingid */
    public function getUserMeetings(string $userid, string $startTime, string $endTime): array
    {
        return $this->result($this->post('/cgi-bin/meeting/get_user_meetingid', [
            'userid' => $userid, 'begin_time' => $startTime, 'end_time' => $endTime
        ]));
    }
}

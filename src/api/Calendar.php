<?php
namespace Wxwork\Api;

/**
 * 日程管理 API 模块
 */
class Calendar extends BaseApi
{
    /** 创建日历 POST /cgi-bin/oa/calendar/add */
    public function add(array $data): array
    {
        return $this->result($this->post('/cgi-bin/oa/calendar/add', $data));
    }

    /** 更新日历 POST /cgi-bin/oa/calendar/update */
    public function update(array $data): array
    {
        return $this->result($this->post('/cgi-bin/oa/calendar/update', $data));
    }

    /** 获取日历 POST /cgi-bin/oa/calendar/get */
    public function getCalendar(array $calIds): array
    {
        return $this->result($this->post('/cgi-bin/oa/calendar/get', ['cal_id' => $calIds]));
    }

    /** 删除日历 POST /cgi-bin/oa/calendar/del */
    public function del(string $calId): array
    {
        return $this->result($this->post('/cgi-bin/oa/calendar/del', ['cal_id' => $calId]));
    }

    /** 创建日程 POST /cgi-bin/oa/schedule/add */
    public function scheduleAdd(array $data): array
    {
        return $this->result($this->post('/cgi-bin/oa/schedule/add', $data));
    }

    /** 更新日程 POST /cgi-bin/oa/schedule/update */
    public function scheduleUpdate(array $data): array
    {
        return $this->result($this->post('/cgi-bin/oa/schedule/update', $data));
    }

    /** 获取日程详情 POST /cgi-bin/oa/schedule/get */
    public function scheduleGet(array $scheduleIds): array
    {
        return $this->result($this->post('/cgi-bin/oa/schedule/get', ['schedule_id' => $scheduleIds]));
    }

    /** 获取日历下的日程 POST /cgi-bin/oa/schedule/get_by_calendar */
    public function scheduleListByCalendar(string $calId, int $offset = 0, int $limit = 100): array
    {
        return $this->result($this->post('/cgi-bin/oa/schedule/get_by_calendar', [
            'cal_id' => $calId, 'offset' => $offset, 'limit' => $limit
        ]));
    }

    /** 取消日程 POST /cgi-bin/oa/schedule/del */
    public function scheduleDel(string $scheduleId): array
    {
        return $this->result($this->post('/cgi-bin/oa/schedule/del', ['schedule_id' => $scheduleId]));
    }
}

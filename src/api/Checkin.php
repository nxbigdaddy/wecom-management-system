<?php
namespace Wxwork\Api;

/**
 * 打卡管理 API 模块
 */
class Checkin extends BaseApi
{
    /** 获取打卡规则 POST /cgi-bin/checkin/getcheckinoption */
    public function getCheckinOption(array $data): array
    {
        return $this->result($this->post('/cgi-bin/checkin/getcheckinoption', $data));
    }

    /** 获取打卡数据 POST /cgi-bin/checkin/getcheckindata */
    public function getCheckinData(array $data): array
    {
        return $this->result($this->post('/cgi-bin/checkin/getcheckindata', $data));
    }

    /** 获取打卡日报 POST /cgi-bin/checkin/getcheckin_day_data */
    public function getDayData(array $data): array
    {
        return $this->result($this->post('/cgi-bin/checkin/getcheckin_day_data', $data));
    }

    /** 获取打卡月报 POST /cgi-bin/checkin/getcheckin_month_data */
    public function getMonthData(array $data): array
    {
        return $this->result($this->post('/cgi-bin/checkin/getcheckin_month_data', $data));
    }

    /** 获取排班列表 POST /cgi-bin/checkin/getcheckinschedulist */
    public function getScheduleList(array $data): array
    {
        return $this->result($this->post('/cgi-bin/checkin/getcheckinschedulist', $data));
    }

    /** 设置排班 POST /cgi-bin/checkin/setcheckinschedulist */
    public function setScheduleList(array $data): array
    {
        return $this->result($this->post('/cgi-bin/checkin/setcheckinschedulist', $data));
    }
}

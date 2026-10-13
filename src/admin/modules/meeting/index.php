<?php
/**
 * 管理后台 - 会议管理（优化版）
 * 
 * 功能：
 *   - 创建会议（预约/快速会议）
 *   - 查询成员会议列表（成员选择器 + 日期预设）
 *   - 会议详情（主题/时间/状态/参会人/录制）
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '会议管理';
$error = ''; $success = '';
$members = [];
$meetings = [];
$tab = $_GET['tab'] ?? $_POST['tab'] ?? 'query';

// 获取成员列表
try {
    $result = $wx->get('/cgi-bin/user/simplelist', ['department_id' => 1, 'fetch_child' => 1]);
    $members = ($result['errcode'] ?? -1) === 0 ? ($result['userlist'] ?? []) : [];
} catch (\Throwable $e) {}

// ===== 处理创建会议 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    try {
        $title_text = trim($_POST['title'] ?? '');
        $startTime = strtotime($_POST['start_time'] ?? '');
        $duration = (int)($_POST['duration'] ?? 60);
        $attendees = array_filter(array_map('trim', explode(',', $_POST['attendees'] ?? '')));
        $type = (int)($_POST['meeting_type'] ?? 1);

        if (empty($title_text)) throw new \RuntimeException('请输入会议主题');
        if ($startTime <= time()) throw new \RuntimeException('开始时间必须是将来的时间');
        if (empty($attendees)) throw new \RuntimeException('请选择参会成员');

        $createData = [
            'title'         => $title_text,
            'meeting_start' => $startTime,
            'meeting_duration' => $duration * 60,
            'type'          => $type,
            'attendees'     => array_map(fn($u) => ['userid' => $u], $attendees),
        ];

        // 可选：会议密码、地点
        if (!empty($_POST['password'])) $createData['password'] = $_POST['password'];
        if (!empty($_POST['location'])) $createData['location'] = $_POST['location'];

	        $result = $wx->meeting->create($createData);
	        $meetingId = $result['meetingid'] ?? '';
	        $success = "会议创建成功！会议ID: <code>{$meetingId}</code>";
	        if (!empty($result['meeting_link'] ?? '')) {
	            $success .= "，<a href=\"{$result['meeting_link']}\" target=\"_blank\">加入会议</a>";
	        }
        $tab = 'create';
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        $tab = 'create';
    }
}

// ===== 处理查询会议 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'query') {
    try {
        $queryUserid = $_POST['query_userid'] ?? '';
        if (empty($queryUserid)) throw new \RuntimeException('请选择要查询的成员');

        $beginTime = strtotime($_POST['begin_time'] ?? '-7 days');
        $endTime = strtotime(($_POST['end_time'] ?? date('Y-m-d')) . ' 23:59:59');

	$result = $wx->meeting->getUserMeetings($queryUserid, (string)$beginTime, (string)$endTime);
	        $meetingIds = $result['meetingid_list'] ?? [];

        foreach ($meetingIds as $mid) {
            try {
		    $info = $wx->meeting->getInfo($mid['meetingid']);
	            $meetings[] = $info['meeting_info'] ?? [];
            } catch (\Throwable $e) {}
        }

        // CSV 导出
        if (!empty($_POST['export'])) {
            $headers = ['MeetingID', '主题', '类型', '创建者', '状态', '开始时间', '时长(分)', '地点', '入会号'];
            $rows = array_map(function($m) {
                $statusMap = [0=>'未开始', 1=>'进行中', 2=>'已结束'];
                return [
                    'meetingid' => $m['meetingid'] ?? '',
                    '主题' => $m['title'] ?? '',
                    '类型' => ($m['type']??1)===1?'预约':'快速',
                    '创建者' => $m['creator_userid'] ?? '',
                    '状态' => $statusMap[$m['status']??0] ?? '未知',
                    '开始时间' => date('Y-m-d H:i', (int)($m['meeting_start']??0)),
                    '时长(分)' => (int)(($m['meeting_duration']??3600)/60),
                    '地点' => $m['location'] ?? '',
                    '入会号' => $m['meeting_code'] ?? '',
                ];
            }, $meetings);
            exportCsv($headers, $rows, '会议记录_' . date('Ymd'));
        }

        $success = "查询到 " . count($meetings) . " 个会议";
        $tab = 'query';
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        $tab = 'query';
    }
}

$defaultStart = date('Y-m-d', strtotime('-7 days'));
$defaultEnd = date('Y-m-d');

require __DIR__ . '/../../layout/header.php';
?>

<style>
.meeting-tabs { border-bottom: 2px solid #e0e0e0; margin-bottom: 15px; }
.meeting-tabs a { display: inline-block; padding: 10px 18px; color: #666; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; font-size: 14px; }
.meeting-tabs a:hover { color: #2196f3; }
.meeting-tabs a.active { color: #1565c0; border-bottom-color: #1565c0; font-weight: 600; }
.member-picker { max-height: 220px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 8px; background: #fafafa; }
.member-picker label { display: block; padding: 4px 6px; font-size: 13px; cursor: pointer; border-radius: 3px; margin: 1px 0; }
.member-picker label:hover { background: #e3f2fd; }
.member-picker label.checked { background: #bbdefb; }
.date-presets { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 8px; }
.date-presets .btn { font-size: 11px; }
.meeting-card { border: 1px solid #e5e5e5; border-radius: 6px; padding: 15px; margin-bottom: 12px; transition: box-shadow 0.2s; }
.meeting-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.meeting-card .meeting-status { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; }
</style>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Tab 切换 -->
<div class="meeting-tabs">
    <a href="?tab=query" class="<?= $tab==='query'?'active':'' ?>"><i class="fa fa-search"></i> 查询会议</a>
    <a href="?tab=create" class="<?= $tab==='create'?'active':'' ?>"><i class="fa fa-plus-circle"></i> 创建会议</a>
</div>

<?php if ($tab === 'create'): ?>
<!-- ===== 创建会议 ===== -->
<div class="row">
    <div class="col-md-7">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-video-camera"></i> <span class="caption-subject bold uppercase">创建新会议</span></div>
            </div>
            <div class="portlet-body">
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="tab" value="create">

                    <div class="form-group">
                        <label>会议主题 <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control input-lg" 
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" 
                               placeholder="例如：项目周会、产品评审" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>开始时间 <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="start_time" class="form-control"
                                       value="<?= htmlspecialchars($_POST['start_time'] ?? date('Y-m-d\TH:i', strtotime('+1 hour'))) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>会议时长（分钟）</label>
                                <select name="duration" class="form-control">
                                    <option value="15">15 分钟</option>
                                    <option value="30" selected>30 分钟</option>
                                    <option value="60">1 小时</option>
                                    <option value="120">2 小时</option>
                                    <option value="180">3 小时</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>会议类型</label>
                                <select name="meeting_type" class="form-control">
                                    <option value="1">预约会议（需成员确认）</option>
                                    <option value="0">快速会议（即时开始）</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>入会密码（可选）</label>
                                <input type="text" name="password" class="YOUR_PASSWORD" placeholder="4-6位数字" maxlength="6">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>会议地点（可选）</label>
                        <input type="text" name="location" class="form-control" placeholder="例如：3楼会议室A">
                    </div>

                    <div class="form-group">
                        <label>参会成员 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control input-sm" id="meetingMemberSearch" placeholder="🔍 搜索成员..." style="margin-bottom:6px">
                        <div class="member-picker" id="meetingMemberPicker">
                            <?php foreach ($members as $m): ?>
                            <label data-userid="<?= htmlspecialchars($m['userid']) ?>" data-name="<?= htmlspecialchars($m['name']) ?>">
                                <input type="checkbox" class="attendee-check" value="<?= htmlspecialchars($m['userid']) ?>">
                                <strong><?= htmlspecialchars($m['name']) ?></strong>
                                <span style="color:#999;font-size:11px;margin-left:4px"><?= htmlspecialchars($m['userid']) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:6px">
                            <button type="button" class="btn btn-xs btn-default" onclick="$('#meetingMemberPicker .attendee-check').prop('checked',true);$('#meetingMemberPicker label').addClass('checked');updateAttendees()">全选</button>
                            <button type="button" class="btn btn-xs btn-default" onclick="$('#meetingMemberPicker .attendee-check').prop('checked',false);$('#meetingMemberPicker label').removeClass('checked');updateAttendees()">清空</button>
                            <span style="margin-left:8px;font-size:12px">已选 <strong id="attendeeCount">0</strong> 人</span>
                        </div>
                        <input type="hidden" name="attendees" id="attendeesInput" value="">
                    </div>

                    <button type="submit" class="btn btn-lg btn-primary"><i class="fa fa-video-camera"></i> 创建会议</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="portlet light">
            <div class="portlet-body">
                <h5><i class="fa fa-info-circle"></i> 使用说明</h5>
                <ul style="font-size:13px;color:#666">
                    <li><strong>预约会议</strong>：设置未来时间，成员会收到通知</li>
                    <li><strong>快速会议</strong>：即时开始，可立即邀请成员加入</li>
                    <li>创建成功后，参会成员会在企业微信中收到会议邀请</li>
                    <li>开启录制后，可在会议列表查看录制文件</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ===== 查询会议 ===== -->
<div class="row">
    <div class="col-md-4">
        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-search"></i> 查询条件</div></div>
            <div class="portlet-body">
                <form method="post" id="queryForm">
                    <input type="hidden" name="action" value="query">
                    <input type="hidden" name="tab" value="query">

                    <div class="form-group">
                        <label><i class="fa fa-user"></i> 成员</label>
                        <select name="query_userid" class="form-control" required>
                            <option value="">-- 选择成员 --</option>
                            <?php foreach ($members as $m): ?>
                            <option value="<?= htmlspecialchars($m['userid']) ?>" <?= ($_POST['query_userid']??'')===$m['userid']?'selected':'' ?>>
                                <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['userid']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fa fa-calendar"></i> 日期范围</label>
                        <div class="date-presets">
                            <button type="button" class="btn btn-xs btn-default" onclick="setDatePreset(0)">今天</button>
                            <button type="button" class="btn btn-xs btn-default" onclick="setDatePreset(-7)">近7天</button>
                            <button type="button" class="btn btn-xs btn-default" onclick="setDatePreset(-30)">近30天</button>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <input type="date" name="begin_time" id="beginDate" class="form-control input-sm" value="<?= $_POST['begin_time'] ?? $defaultStart ?>">
                            </div>
                            <div class="col-md-6">
                                <input type="date" name="end_time" id="endDate" class="form-control input-sm" value="<?= $_POST['end_time'] ?? $defaultEnd ?>">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i> 查询</button>
                    <button type="submit" name="export" value="1" class="btn btn-success btn-block" style="margin-top:5px"><i class="fa fa-download"></i> 查询并导出 CSV</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <?php if (!empty($meetings)): ?>
            <?php foreach ($meetings as $m): 
                $status = $m['status'] ?? 0;
                $statusMap = [0 => ['未开始', '#607d8b'], 1 => ['进行中', '#4caf50'], 2 => ['已结束', '#9e9e9e']];
                [$statusLabel, $statusColor] = $statusMap[$status] ?? ['未知', '#999'];
            ?>
            <div class="meeting-card">
                <div class="row">
                    <div class="col-md-9">
                        <h4 style="margin-top:0">
                            <i class="fa fa-video-camera text-primary"></i> 
                            <?= htmlspecialchars($m['title'] ?? '未命名') ?>
                        </h4>
                        <div style="margin:8px 0">
                            <i class="fa fa-clock-o"></i> 
                            <?= date('Y-m-d H:i', (int)($m['meeting_start'] ?? 0)) ?>
                            (<?= (int)(($m['meeting_duration'] ?? 3600) / 60) ?> 分钟)
                        </div>
                        <div>
                            <i class="fa fa-user"></i> 创建者: <?= htmlspecialchars($m['creator_userid'] ?? '-') ?>
                            <?php if (!empty($m['location'])): ?>
                                | <i class="fa fa-map-marker"></i> <?= htmlspecialchars($m['location']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($m['attendees'])): ?>
                        <div style="margin-top:8px">
                            <i class="fa fa-users"></i> 
                            <?php foreach (array_slice($m['attendees'], 0, 10) as $a): ?>
                                <span class="label label-default"><?= htmlspecialchars($a['userid'] ?? '') ?></span>
                            <?php endforeach; ?>
                            <?php if (count($m['attendees']) > 10): ?>
                                <span class="text-muted">+<?= count($m['attendees']) - 10 ?> 人</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="meeting-status" style="background:<?= $statusColor ?>;color:#fff"><?= $statusLabel ?></div>
                        <div style="margin-top:8px">
                            <code style="font-size:11px"><?= htmlspecialchars($m['meetingid'] ?? '') ?></code>
                        </div>
                        <?php if ($status === 1 && !empty($m['meeting_code'])): ?>
                        <div style="margin-top:5px">
                            <span class="text-muted">入会号: <?= htmlspecialchars($m['meeting_code']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($m['meeting_link'] ?? '')): ?>
                        <a href="<?= htmlspecialchars($m['meeting_link']) ?>" target="_blank" class="btn btn-xs btn-primary" style="margin-top:8px">
                            <i class="fa fa-sign-in"></i> 加入
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="portlet light">
            <div class="portlet-body">
                <?= renderEmpty('fa-video-camera', '暂无会议数据', '选择一个成员和日期范围后点击查询，或切换到"创建会议"Tab 新建会议', '/meeting?tab=create', '创建新会议') ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
// 日期预设
function setDatePreset(offset) {
    var start, end = new Date();
    start = new Date();
    start.setDate(start.getDate() + offset);
    var beginEl = document.getElementById('beginDate');
    var endEl = document.getElementById('endDate');
    if (beginEl) beginEl.value = formatDate(start);
    if (endEl) endEl.value = formatDate(end);
}
function formatDate(d) {
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

// ===== 创建会议：参会人选择 =====
(function() {
    var searchEl = document.getElementById('meetingMemberSearch');
    if (!searchEl) return;

    searchEl.addEventListener('input', function() {
        var kw = this.value.toLowerCase();
        document.querySelectorAll('#meetingMemberPicker label').forEach(function(l) {
            var name = (l.dataset.name || '').toLowerCase();
            var uid = (l.dataset.userid || '').toLowerCase();
            l.style.display = (name.includes(kw) || uid.includes(kw)) ? '' : 'none';
        });
    });

    document.querySelectorAll('#meetingMemberPicker .attendee-check').forEach(function(cb) {
        cb.addEventListener('change', function() {
            this.closest('label').classList.toggle('checked', this.checked);
            updateAttendees();
        });
    });

    document.querySelectorAll('#meetingMemberPicker label').forEach(function(l) {
        l.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT') return;
            var cb = this.querySelector('.attendee-check');
            cb.checked = !cb.checked;
            this.classList.toggle('checked', cb.checked);
            updateAttendees();
        });
    });
})();

function updateAttendees() {
    var ids = [], count = 0;
    document.querySelectorAll('#meetingMemberPicker .attendee-check:checked').forEach(function(cb) {
        ids.push(cb.value);
        count++;
    });
    var countEl = document.getElementById('attendeeCount');
    if (countEl) countEl.textContent = count;
    var inputEl = document.getElementById('attendeesInput');
    if (inputEl) inputEl.value = ids.join(',');
}
</script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

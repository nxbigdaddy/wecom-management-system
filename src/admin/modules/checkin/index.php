<?php
/**
 * 管理后台 - 打卡管理（优化版）
 * 
 * 功能：
 *   - 成员可视化选择器（搜索/全选/清空）
 *   - 日期快捷预设（今天/近7天/近30天/本月）
 *   - 打卡数据查询与统计摘要
 *   - 按成员分组的打卡详情
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '打卡管理';
$error = ''; $success = '';
$members = [];
$checkinData = [];
$stats = ['total' => 0, 'users' => 0, 'normal' => 0, 'late' => 0, 'early' => 0];
$queryParams = [];

// 获取成员列表
try {
    $result = $wx->get('/cgi-bin/user/simplelist', ['department_id' => 1, 'fetch_child' => 1]);
    $members = ($result['errcode'] ?? -1) === 0 ? ($result['userlist'] ?? []) : [];
} catch (\Throwable $e) {}

// 处理查询
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $startDate = $_POST['starttime'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $_POST['endtime'] ?? date('Y-m-d');
        $userlist = array_filter(array_map('trim', explode(',', $_POST['useridlist'] ?? '')));
        
        // 如果选了"指定成员"模式，使用选中的成员
        $queryMode = $_POST['query_mode'] ?? 'all';
        if ($queryMode === 'selected' && !empty($_POST['selected_users'])) {
            $userlist = explode(',', $_POST['selected_users']);
        }
        
        if (empty($userlist)) {
            // 未选成员：取前20个成员
            $userlist = array_slice(array_column($members, 'userid'), 0, 20);
        }

        $queryParams = ['start' => $startDate, 'end' => $endDate, 'users' => $userlist];

	        $result = $wx->checkin->getCheckinData([
	            'opencheckindatatype' => 3,
	            'starttime' => strtotime($startDate),
	            'endtime'   => strtotime($endDate . ' 23:59:59'),
	            'useridlist' => $userlist,
	        ]);

	        $checkinData = $result['checkindata'] ?? [];

            // 统计
            $userSet = [];
            foreach ($checkinData as $d) {
                $userSet[$d['userid'] ?? ''] = true;
                $exception = $d['exception_type'] ?? '';
                if (empty($exception) || $exception === '正常') {
                    $stats['normal']++;
                } elseif (str_contains($exception, '迟到')) {
                    $stats['late']++;
                } elseif (str_contains($exception, '早退')) {
                    $stats['early']++;
                }
            }
            $stats['total'] = count($checkinData);
            $stats['users'] = count($userSet);
            $success = "查询成功：{$stats['total']} 条打卡记录，涉及 {$stats['users']} 人";

            // CSV 导出
            if (!empty($_POST['export'])) {
                $headers = ['UserID', '姓名', '日期', '上班时间', '下班时间', '班次', '类型', '异常类型', '地点', 'WiFi'];
                $nameMap = [];
                foreach ($members as $m) $nameMap[$m['userid']] = $m['name'];
                $rows = array_map(function($d) use ($nameMap) {
                    $items = $d['checkindetail'] ?? [];
                    $times = [];
                    foreach ($items as $item) {
                        $times[] = date('H:i:s', (int)($item['checkin_time']??0));
                    }
                    return [
                        'userid' => $d['userid'] ?? '',
                        '姓名' => $nameMap[$d['userid']] ?? '',
                        '日期' => date('Y-m-d', (int)($d['checkin_time']??0)),
                        '上班时间' => date('H:i', (int)($d['sch_checkin_time']??0)),
                        '下班时间' => date('H:i', (int)($d['sch_checkout_time']??0)),
                        '班次' => $d['groupname'] ?? '',
                        '类型' => $d['checkin_type'] ?? '',
                        '异常类型' => $d['exception_type'] ?? '正常',
                        '地点' => $d['location_title'] ?? '',
                        'WiFi' => $d['wifiname'] ?? '',
                    ];
                }, $checkinData);
                exportCsv($headers, $rows, '打卡记录_' . date('Ymd'));
	            }
	    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

// 默认日期
$defaultStart = date('Y-m-d', strtotime('-7 days'));
$defaultEnd = date('Y-m-d');

require __DIR__ . '/../../layout/header.php';
?>

<style>
.date-presets { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 10px; }
.date-presets .btn { font-size: 12px; }
.member-picker { max-height: 260px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: #fafafa; }
.member-picker label { display: block; padding: 5px 10px; margin: 0; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f0f0f0; transition: background 0.15s; }
.member-picker label:hover { background: #e3f2fd; }
.member-picker label.checked { background: #bbdefb; }
.stat-card { text-align: center; padding: 15px 10px; border-radius: 6px; color: #fff; }
.stat-card .stat-num { font-size: 28px; font-weight: bold; }
.stat-card .stat-label { font-size: 12px; opacity: 0.85; margin-top: 4px; }
.checkin-user-group { margin-bottom: 20px; }
.checkin-user-group .user-header { background: #f5f5f5; padding: 10px 15px; border-radius: 4px; margin-bottom: 8px; }
</style>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="row">
    <!-- 查询面板 -->
    <div class="col-md-5">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-search"></i> <span class="caption-subject bold uppercase">打卡查询</span></div>
            </div>
            <div class="portlet-body">
                <form method="post" id="checkinForm">
                    <!-- 日期快捷预设 -->
                    <div class="form-group">
                        <label><i class="fa fa-calendar"></i> 日期范围</label>
                        <div class="date-presets">
                            <button type="button" class="btn btn-xs btn-default" onclick="setDatePreset(0)">今天</button>
                            <button type="button" class="btn btn-xs btn-default" onclick="setDatePreset(-1)">昨天</button>
                            <button type="button" class="btn btn-xs btn-default" onclick="setDatePreset(-7)">近7天</button>
                            <button type="button" class="btn btn-xs btn-default" onclick="setDatePreset(-30)">近30天</button>
                            <button type="button" class="btn btn-xs btn-default" onclick="setDatePreset('month')">本月</button>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <input type="date" name="starttime" id="startDate" class="form-control input-sm" value="<?= $queryParams['start'] ?? $defaultStart ?>">
                            </div>
                            <div class="col-md-6">
                                <input type="date" name="endtime" id="endDate" class="form-control input-sm" value="<?= $queryParams['end'] ?? $defaultEnd ?>">
                            </div>
                        </div>
                    </div>

                    <!-- 成员选择 -->
                    <div class="form-group">
                        <label><i class="fa fa-users"></i> 查询成员</label>
                        <div style="margin-bottom:6px">
                            <label class="radio-inline">
                                <input type="radio" name="query_mode" value="all" checked onchange="toggleMemberPicker()"> 自动（前20人）
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="query_mode" value="selected" onchange="toggleMemberPicker()"> 指定成员
                            </label>
                        </div>
                        <div id="memberPickerPanel" style="display:none">
                            <input type="text" class="form-control input-sm" id="memberSearch" placeholder="🔍 搜索姓名或 UserID..." style="margin-bottom:6px">
                            <div class="member-picker" id="memberPicker">
                                <?php foreach ($members as $m): ?>
                                <label data-userid="<?= htmlspecialchars($m['userid']) ?>" data-name="<?= htmlspecialchars($m['name']) ?>">
                                    <input type="checkbox" class="member-check" value="<?= htmlspecialchars($m['userid']) ?>">
                                    <strong><?= htmlspecialchars($m['name']) ?></strong>
                                    <span style="color:#999;font-size:11px;margin-left:4px"><?= htmlspecialchars($m['userid']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div style="margin-top:6px">
                                <button type="button" class="btn btn-xs btn-default" onclick="$('#memberPicker .member-check').prop('checked',true);$('#memberPicker label').addClass('checked');updateMemberCount()">全选</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="$('#memberPicker .member-check').prop('checked',false);$('#memberPicker label').removeClass('checked');updateMemberCount()">清空</button>
                                <span style="margin-left:8px;font-size:12px">已选 <strong id="memberCount">0</strong> 人</span>
                            </div>
                            <input type="hidden" name="selected_users" id="selectedUsers" value="">
                        </div>
                        <input type="hidden" name="useridlist" value="">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fa fa-search"></i> 查询打卡数据
                    </button>
                    <button type="submit" name="export" value="1" class="btn btn-success btn-block" style="margin-top:5px">
                        <i class="fa fa-download"></i> 查询并导出 CSV
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- 统计面板 -->
    <div class="col-md-7">
        <?php if (!empty($checkinData)): ?>
        <div class="row">
            <div class="col-md-3 col-xs-6">
                <div class="stat-card" style="background:#2196f3">
                    <div class="stat-num"><?= $stats['total'] ?></div>
                    <div class="stat-label">打卡总次数</div>
                </div>
            </div>
            <div class="col-md-3 col-xs-6">
                <div class="stat-card" style="background:#4caf50">
                    <div class="stat-num"><?= $stats['users'] ?></div>
                    <div class="stat-label">打卡人数</div>
                </div>
            </div>
            <div class="col-md-3 col-xs-6">
                <div class="stat-card" style="background:#ff9800">
                    <div class="stat-num"><?= $stats['normal'] ?></div>
                    <div class="stat-label">正常打卡</div>
                </div>
            </div>
            <div class="col-md-3 col-xs-6">
                <div class="stat-card" style="background:#f44336">
                    <div class="stat-num"><?= $stats['late'] + $stats['early'] ?></div>
                    <div class="stat-label">异常打卡</div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="portlet light">
            <div class="portlet-body">
                <?= renderEmpty('fa-calendar-check-o', '暂无打卡数据', '选择日期和成员后点击查询查看打卡记录。支持按成员搜索选择，可导出 CSV') ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 打卡记录列表（按成员分组） -->
<?php if (!empty($checkinData)): 
    // 按成员分组
    $grouped = [];
    foreach ($checkinData as $d) {
        $uid = $d['userid'] ?? 'unknown';
        $grouped[$uid][] = $d;
    }
    
    // 获取成员名映射
    $nameMap = [];
    foreach ($members as $m) $nameMap[$m['userid']] = $m['name'];
?>
<div class="row"><div class="col-md-12">
    <div class="portlet light">
        <div class="portlet-title">
            <div class="caption"><i class="fa fa-list"></i> <span class="caption-subject bold uppercase">打卡详情</span></div>
            <div class="actions">
                <span class="text-muted"><?= date('Y-m-d', strtotime($queryParams['start'] ?? '-7 days')) ?> ~ <?= date('Y-m-d', strtotime($queryParams['end'] ?? 'now')) ?></span>
            </div>
        </div>
        <div class="portlet-body">
            <?php foreach ($grouped as $uid => $records): ?>
            <div class="checkin-user-group">
                <div class="user-header">
                    <strong><i class="fa fa-user"></i> <?= htmlspecialchars($nameMap[$uid] ?? $uid) ?></strong>
                    <code style="margin-left:8px"><?= htmlspecialchars($uid) ?></code>
                    <span class="pull-right text-muted"><?= count($records) ?> 条记录</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead>
                            <tr>
                                <th width="100">日期</th>
                                <th>打卡时间</th>
                                <th>班次</th>
                                <th>类型</th>
                                <th width="120">异常类型</th>
                                <th>地点</th>
                                <th>WiFi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $d): ?>
                            <tr>
                                <td><?= date('Y-m-d', (int)($d['checkin_time'] ?? 0)) ?></td>
                                <td>
                                    <?php 
                                    $items = $d['checkindetail'] ?? [];
                                    foreach ($items as $item) {
                                        $time = date('H:i:s', (int)($item['checkin_time'] ?? 0));
                                        $type = $item['checkin_type'] ?? '';
                                        $icon = $type === '上班' ? 'fa-arrow-up text-success' : 'fa-arrow-down text-warning';
                                        echo "<span style='margin:2px'><i class='fa {$icon}'></i> {$time}</span> ";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?= date('H:i', (int)($d['sch_checkin_time'] ?? 0)) ?> ~ 
                                    <?= date('H:i', (int)($d['sch_checkout_time'] ?? 0)) ?>
                                </td>
                                <td><?= htmlspecialchars($d['groupname'] ?? '-') ?></td>
                                <td>
                                    <?php $ex = $d['exception_type'] ?? ''; ?>
                                    <?php if (empty($ex)): ?>
                                        <span class="label label-sm label-success">正常</span>
                                    <?php else: ?>
                                        <span class="label label-sm label-<?= str_contains($ex,'迟到')?'warning':'danger' ?>"><?= htmlspecialchars($ex) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($d['location_title'] ?? $d['location_detail'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($d['wifiname'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div></div>
<?php endif; ?>

<script>
// 日期预设
function setDatePreset(offset) {
    var start, end = new Date();
    if (offset === 'month') {
        start = new Date(end.getFullYear(), end.getMonth(), 1);
    } else if (offset === 0) {
        start = new Date();
    } else {
        start = new Date();
        start.setDate(start.getDate() + offset);
    }
    document.getElementById('startDate').value = formatDate(start);
    document.getElementById('endDate').value = formatDate(end);
}

function formatDate(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

// 切换成员选择面板
function toggleMemberPicker() {
    var mode = document.querySelector('input[name=query_mode]:checked')?.value;
    document.getElementById('memberPickerPanel').style.display = mode === 'selected' ? '' : 'none';
}

// 成员搜索
document.getElementById('memberSearch')?.addEventListener('input', function() {
    var kw = this.value.toLowerCase();
    document.querySelectorAll('#memberPicker label').forEach(function(l) {
        var name = (l.dataset.name || '').toLowerCase();
        var uid = (l.dataset.userid || '').toLowerCase();
        l.style.display = (name.includes(kw) || uid.includes(kw)) ? '' : 'none';
    });
});

// 成员选择变化
document.querySelectorAll('#memberPicker .member-check').forEach(function(cb) {
    cb.addEventListener('change', function() {
        this.closest('label').classList.toggle('checked', this.checked);
        updateMemberCount();
    });
});

document.querySelectorAll('#memberPicker label').forEach(function(l) {
    l.addEventListener('click', function(e) {
        if (e.target.tagName === 'INPUT') return;
        var cb = this.querySelector('.member-check');
        cb.checked = !cb.checked;
        this.classList.toggle('checked', cb.checked);
        updateMemberCount();
    });
});

function updateMemberCount() {
    var count = document.querySelectorAll('#memberPicker .member-check:checked').length;
    var el = document.getElementById('memberCount');
    if (el) el.textContent = count;

    var ids = [];
    document.querySelectorAll('#memberPicker .member-check:checked').forEach(function(cb) {
        ids.push(cb.value);
    });
    document.getElementById('selectedUsers').value = ids.join(',');
}

// 表单提交前确保选择了成员
document.getElementById('checkinForm')?.addEventListener('submit', function() {
    var mode = document.querySelector('input[name=query_mode]:checked')?.value;
    if (mode === 'selected') {
        var count = document.querySelectorAll('#memberPicker .member-check:checked').length;
        if (count === 0) {
            alert('请至少选择一个成员，或切换到"自动"模式');
            event.preventDefault();
            return false;
        }
    }
});
</script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

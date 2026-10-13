<?php
/**
 * 管理后台 - 日程管理（日历 + 日程）
 * 
 * 功能：
 *   - 日历管理：创建日历、查询日历列表、删除日历
 *   - 日程管理：按日历查询日程、创建日程
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '日程管理';
$error = ''; $success = '';
$calendars = [];
$scheduleList = [];
$tab = $_GET['tab'] ?? $_POST['tab'] ?? 'calendars';

// ===== 日历管理 =====
if ($tab === 'calendars') {
    // 创建日历
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_cal') {
        try {
            $title_text = trim($_POST['title'] ?? '');
            if (empty($title_text)) throw new \RuntimeException('请输入日历标题');

            $calData = [
                'calendar' => [
                    'title' => $title_text,
                    'type'  => (int)($_POST['type'] ?? 1),
                ],
                'summary' => $_POST['summary'] ?? '',
                'color'   => $_POST['color'] ?? '#41b135',
            ];
            // 可选：日历权限
            if (!empty($_POST['readers'])) {
                $calData['calendar']['readers'] = json_decode($_POST['readers'], true) ?? [];
            }
            if (!empty($_POST['writers'])) {
                $calData['calendar']['writers'] = json_decode($_POST['writers'], true) ?? [];
            }

            $result = $wx->calendar->add($calData);
            $calId = $result['cal_id'] ?? '';
            $success = "日历创建成功！cal_id: <code>{$calId}</code>";
            $tab = 'calendars';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }

    // 删除日历
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'del_cal') {
        try {
            $calId = $_POST['cal_id'] ?? '';
            if (empty($calId)) throw new \RuntimeException('请选择要删除的日历');
            $wx->calendar->del($calId);
            $success = "日历已删除";
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }

    // 加载日历列表（使用预置的日历 ID 查询）
    if (isset($_POST['cal_ids']) || isset($_GET['cal_ids'])) {
        try {
            $calIds = explode(',', $_POST['cal_ids'] ?? $_GET['cal_ids'] ?? '');
            $calIds = array_filter(array_map('trim', $calIds));
            if (!empty($calIds)) {
                $result = $wx->calendar->get($calIds);
                $calendars = $result['calendar_list'] ?? [];
            }
        } catch (\Throwable $e) {
            $error = '查询日历失败: ' . $e->getMessage();
        }
    }
}

// ===== 日程管理 =====
if ($tab === 'schedules') {
    $currentCalId = $_GET['cal_id'] ?? $_POST['cal_id'] ?? '';

    // 创建日程
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_sch') {
        try {
            $summary = trim($_POST['summary'] ?? '');
            if (empty($summary)) throw new \RuntimeException('请输入日程主题');

            $schData = [
                'calendar_id' => $currentCalId,
                'summary'     => $summary,
                'start_time'  => (int)(strtotime($_POST['start_time'] ?? '')),
                'end_time'    => (int)(strtotime($_POST['end_time'] ?? '')),
            ];
            if (empty($schData['start_time']) || empty($schData['end_time'])) {
                throw new \RuntimeException('请设置日程开始和结束时间');
            }

            // 可选参数
            if (!empty($_POST['description'])) $schData['description'] = $_POST['description'];
            if (!empty($_POST['location'])) $schData['location'] = $_POST['location'];
            if (!empty($_POST['attendees'])) {
                $attendees = array_map('trim', explode(',', $_POST['attendees']));
                $schData['attendees'] = array_map(fn($u) => ['userid' => $u], $attendees);
            }
            if (!empty($_POST['remind_time'])) $schData['remind_time'] = (int)$_POST['remind_time'];
            if (!empty($_POST['color'])) $schData['color'] = $_POST['color'];

            $result = $wx->calendar->scheduleAdd($schData);
            $success = "日程创建成功！";
            $tab = 'schedules';
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }

    // 删除日程
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'del_sch') {
        try {
            $scheduleId = $_POST['schedule_id'] ?? '';
            if (empty($scheduleId)) throw new \RuntimeException('请选择要删除的日程');
            $wx->calendar->scheduleDel($scheduleId);
            $success = "日程已删除";
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }

    // 查询日程
    if (!empty($currentCalId)) {
        try {
            $offset = (int)($_GET['offset'] ?? 0);
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $result = $wx->calendar->scheduleListByCalendar($currentCalId, $offset, $limit);
            $scheduleList = $result['schedule_list'] ?? [];
        } catch (\Throwable $e) {
            $error = '查询日程失败: ' . $e->getMessage();
        }
    }
}

require __DIR__ . '/../../layout/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Tab 切换 -->
<div class="tabbable-custom" style="margin-bottom:15px;border-bottom:2px solid #e0e0e0">
    <a href="?tab=calendars" style="display:inline-block;padding:10px 18px;color:#666;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;font-size:14px;<?= $tab==='calendars'?'color:#1565c0;border-bottom-color:#1565c0;font-weight:600':'' ?>">
        <i class="fa fa-calendar"></i> 日历管理
    </a>
    <a href="?tab=schedules" style="display:inline-block;padding:10px 18px;color:#666;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;font-size:14px;<?= $tab==='schedules'?'color:#1565c0;border-bottom-color:#1565c0;font-weight:600':'' ?>">
        <i class="fa fa-list"></i> 日程管理
    </a>
</div>

<?php if ($tab === 'calendars'): ?>

<!-- ===== 日历管理 ===== -->
<div class="row">
    <div class="col-md-5">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-plus-circle"></i> <span class="caption-subject bold uppercase">创建日历</span></div>
            </div>
            <div class="portlet-body">
                <form method="post">
                    <input type="hidden" name="action" value="create_cal">
                    <input type="hidden" name="tab" value="calendars">

                    <div class="form-group">
                        <label>日历标题 <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="例如：我的日历" required>
                    </div>

                    <div class="form-group">
                        <label>日历类型</label>
                        <select name="type" class="form-control">
                            <option value="1">个人日历</option>
                            <option value="2">共享日历</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>摘要/描述</label>
                        <textarea name="summary" class="form-control" rows="2" placeholder="日历简要描述"></textarea>
                    </div>

                    <div class="form-group">
                        <label>日历颜色</label>
                        <div class="input-group" style="width:120px">
                            <input type="color" name="color" class="form-control" value="#41b135" style="height:34px;padding:2px">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fa fa-calendar-plus-o"></i> 创建日历</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-search"></i> <span class="caption-subject bold uppercase">查询日历</span></div>
            </div>
            <div class="portlet-body">
                <form method="post" class="form-inline" style="margin-bottom:15px">
                    <input type="hidden" name="tab" value="calendars">
                    <div class="form-group">
                        <label>输入日历 ID（多个用逗号分隔）：</label>
                        <input type="text" name="cal_ids" class="form-control input-sm" style="width:300px" 
                               value="<?= htmlspecialchars($_POST['cal_ids'] ?? $_GET['cal_ids'] ?? '') ?>"
                               placeholder="cal_id1, cal_id2, ...">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-search"></i> 查询</button>
                </form>

                <?php if (!empty($calendars)): ?>
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>标题</th>
                            <th>类型</th>
                            <th>权限</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calendars as $cal): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($cal['title'] ?? '') ?></strong>
                                <br><code style="font-size:10px"><?= htmlspecialchars($cal['cal_id'] ?? '') ?></code>
                            </td>
                            <td><?= ($cal['type'] ?? 1) === 1 ? '个人' : '共享' ?></td>
                            <td>
                                <span class="text-muted" style="font-size:11px">
                                    读者: <?= count($cal['readers'] ?? []) ?> 人 |
                                    写者: <?= count($cal['writers'] ?? []) ?> 人
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="?tab=schedules&cal_id=<?= urlencode($cal['cal_id'] ?? '') ?>" class="btn btn-xs blue">
                                    <i class="fa fa-list"></i> 查看日程
                                </a>
                                <form method="post" style="display:inline" onsubmit="return confirm('确定删除此日历？日程也会被删除')">
                                    <input type="hidden" name="action" value="del_cal">
                                    <input type="hidden" name="tab" value="calendars">
                                    <input type="hidden" name="cal_id" value="<?= htmlspecialchars($cal['cal_id'] ?? '') ?>">
                                    <button type="submit" class="btn btn-xs red"><i class="fa fa-trash"></i> 删除</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted">暂无日历数据。输入日历 ID 查询，或创建一个新日历。</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<!-- ===== 日程管理 ===== -->
<div class="row">
    <div class="col-md-4">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-plus-circle"></i> <span class="caption-subject bold uppercase">创建日程</span></div>
            </div>
            <div class="portlet-body">
                <form method="post">
                    <input type="hidden" name="action" value="create_sch">
                    <input type="hidden" name="tab" value="schedules">
                    <input type="hidden" name="cal_id" value="<?= htmlspecialchars($currentCalId) ?>">

                    <div class="form-group">
                        <label>所属日历 ID <span class="text-danger">*</span></label>
                        <input type="text" name="cal_id_display" class="form-control input-sm" value="<?= htmlspecialchars($currentCalId) ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>日程主题 <span class="text-danger">*</span></label>
                        <input type="text" name="summary" class="form-control" placeholder="例如：项目评审" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>开始时间 <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="start_time" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>结束时间 <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="end_time" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>地点（可选）</label>
                        <input type="text" name="location" class="form-control" placeholder="例如：3楼会议室A">
                    </div>

                    <div class="form-group">
                        <label>描述（可选）</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="日程详情描述"></textarea>
                    </div>

                    <div class="form-group">
                        <label>提醒（分钟提前）</label>
                        <select name="remind_time" class="form-control">
                            <option value="0">不提醒</option>
                            <option value="5">5 分钟</option>
                            <option value="15" selected>15 分钟</option>
                            <option value="30">30 分钟</option>
                            <option value="60">1 小时</option>
                            <option value="1440">1 天</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-plus"></i> 创建日程</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-list"></i> <span class="caption-subject bold uppercase">日程列表</span>
                </div>
                <div class="actions">
                    <form method="get" class="form-inline">
                        <input type="hidden" name="tab" value="schedules">
                        <div class="input-group">
                            <input type="text" name="cal_id" class="form-control input-sm" style="width:250px" 
                                   placeholder="输入日历 ID" value="<?= htmlspecialchars($currentCalId) ?>">
                            <span class="input-group-btn">
                                <button class="btn btn-sm default" type="submit"><i class="fa fa-search"></i></button>
                            </span>
                        </div>
                    </form>
                </div>
            </div>
            <div class="portlet-body">
                <?php if (empty($currentCalId)): ?>
                    <?= renderEmpty('fa-calendar', '请先选择日历', '在右侧输入框输入日历 ID，或从日历管理页面跳转过来') ?>
                <?php elseif (empty($scheduleList)): ?>
                    <?= renderEmpty('fa-list', '暂无日程', '此日历下还没有日程，在左侧创建') ?>
                <?php else: ?>
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>主题</th>
                            <th>开始时间</th>
                            <th>结束时间</th>
                            <th>地点</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduleList as $sch): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sch['summary'] ?? '') ?></strong></td>
                            <td><?= date('Y-m-d H:i', (int)($sch['start_time'] ?? 0)) ?></td>
                            <td><?= date('Y-m-d H:i', (int)($sch['end_time'] ?? 0)) ?></td>
                            <td><?= htmlspecialchars($sch['location'] ?? '-') ?></td>
                            <td>
                                <?php $st = $sch['status'] ?? 0; ?>
                                <span class="label label-sm label-<?= $st===0?'info':($st===1?'success':'default') ?>">
                                    <?= $st===0?'进行中':($st===1?'已完成':'已取消') ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <form method="post" style="display:inline" onsubmit="return confirm('确定删除此日程？')">
                                    <input type="hidden" name="action" value="del_sch">
                                    <input type="hidden" name="tab" value="schedules">
                                    <input type="hidden" name="cal_id" value="<?= htmlspecialchars($currentCalId) ?>">
                                    <input type="hidden" name="schedule_id" value="<?= htmlspecialchars($sch['schedule_id'] ?? '') ?>">
                                    <button type="submit" class="btn btn-xs red"><i class="fa fa-trash"></i> 删除</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

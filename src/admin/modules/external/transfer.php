<?php
/**
 * 管理后台 - 客户继承管理（优化版）
 * 
 * 两个 Tab：
 *   1. 离职继承 — 将离职成员的客户分配给其他成员
 *   2. 在职继承 — 将在职成员的客户转接给其他成员
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '客户继承管理';
$error = ''; $success = '';
$members = [];
$unassigned = [];
$tab = $_GET['tab'] ?? 'dimission';

// 获取成员列表
try {
    $result = $wx->get('/cgi-bin/user/simplelist', ['department_id' => 1, 'fetch_child' => 1]);
    $members = ($result['errcode'] ?? -1) === 0 ? ($result['userlist'] ?? []) : [];
} catch (\Throwable $e) {}

// ===== 离职继承：获取待分配客户 =====
if ($tab === 'dimission' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $result = $wx->post('/cgi-bin/externalcontact/get_unassigned_list', [
            'page_id'   => (int)($_GET['page'] ?? 0),
            'page_size' => 100,
        ]);
        if (($result['errcode'] ?? -1) === 0) {
            $unassigned = $result['info'] ?? [];
            // 标记是否还有更多
            $hasMore = $result['is_last'] ?? true;
        }
    } catch (\Throwable $e) { $error = $e->getMessage(); }
}

// ===== 处理操作 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            // 离职继承：执行分配
            case 'dimission_transfer':
                $handover = $_POST['handover_userid'] ?? '';
                $takeover = $_POST['takeover_userid'] ?? '';
                $externalIds = array_filter(array_map('trim', explode("\n", $_POST['external_userids'] ?? '')));
                if (!$handover || !$takeover || empty($externalIds)) throw new \RuntimeException('请填写完整信息');
                $wx->post('/cgi-bin/externalcontact/transfer', [
                    'handover_userid' => $handover,
                    'takeover_userid' => $takeover,
                    'external_userid' => $externalIds,
                ]);
                $success = '离职继承分配已提交！客户将在24小时内确认';
                break;

            // 离职继承：查询结果
            case 'dimission_check':
                $handover = $_POST['handover_userid'] ?? '';
                $takeover = $_POST['takeover_userid'] ?? '';
                if ($handover && $takeover) {
                    $result = $wx->post('/cgi-bin/externalcontact/get_transfer_result', [
                        'handover_userid' => $handover,
                        'takeover_userid' => $takeover,
                    ]);
                    $transferResult = ($result['errcode'] ?? -1) === 0 ? ($result['customer'] ?? []) : [];
                }
                break;

            // 在职继承：执行分配
            case 'active_transfer':
                $handover = $_POST['handover_userid'] ?? '';
                $takeover = $_POST['takeover_userid'] ?? '';
                $externalIds = array_filter(array_map('trim', explode("\n", $_POST['external_userids'] ?? '')));
                if (!$handover || !$takeover || empty($externalIds)) throw new \RuntimeException('请填写完整信息');
                $wx->post('/cgi-bin/externalcontact/transfer_customer', [
                    'handover_userid' => $handover,
                    'takeover_userid' => $takeover,
                    'external_userid' => $externalIds,
                ]);
                $success = '在职继承分配已提交！客户将在24小时内确认';
                break;

            // 在职继承：查询结果
            case 'active_check':
                $handover = $_POST['handover_userid'] ?? '';
                $takeover = $_POST['takeover_userid'] ?? '';
                if ($handover && $takeover) {
                    $result = $wx->post('/cgi-bin/externalcontact/get_transfer_customer_result', [
                        'handover_userid' => $handover,
                        'takeover_userid' => $takeover,
                    ]);
                    $transferResult = ($result['errcode'] ?? -1) === 0 ? ($result['customer'] ?? []) : [];
                }
                break;
        }
    } catch (\Throwable $e) { $error = $e->getMessage(); }
}

require __DIR__ . '/../../layout/header.php';
?>

<style>
.transfer-tabs { border-bottom: 2px solid #e0e0e0; margin-bottom: 20px; display: flex; }
.transfer-tabs a { display: inline-block; padding: 10px 20px; color: #666; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; font-size: 14px; }
.transfer-tabs a:hover { color: #2196f3; }
.transfer-tabs a.active { color: #1565c0; border-bottom-color: #1565c0; font-weight: 600; }
.transfer-tabs a i { margin-right: 6px; }
.result-table-wrap { max-height: 400px; overflow-y: auto; }
</style>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Tab 切换 -->
<div class="transfer-tabs">
    <a href="?tab=dimission" class="<?= $tab==='dimission'?'active':'' ?>">
        <i class="fa fa-sign-out"></i> 离职继承
    </a>
    <a href="?tab=active" class="<?= $tab==='active'?'active':'' ?>">
        <i class="fa fa-exchange"></i> 在职继承
    </a>
</div>

<?php if ($tab === 'dimission'): ?>
<!-- ===== 离职继承 ===== -->
<div class="row">
    <!-- 待分配列表 -->
    <div class="col-md-6">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-sign-out"></i> <span class="caption-subject bold uppercase">待分配离职客户</span></div>
                <div class="actions"><a class="btn btn-sm btn-default" href="/external/transfer?tab=dimission"><i class="fa fa-refresh"></i></a></div>
            </div>
            <div class="portlet-body">
                <?php if (empty($unassigned)): ?>
                <div class="text-center text-muted" style="padding:40px">
                    <i class="fa fa-check-circle fa-3x text-success"></i>
                    <p style="margin-top:10px">暂无待分配的离职客户</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead><tr><th>离职成员</th><th width="80" class="text-center">客户数</th><th width="100">操作</th></tr></thead>
                        <tbody>
                            <?php foreach ($unassigned as $u): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($u['handover_userid'] ?? '') ?></code></td>
                                <td class="text-center"><span class="badge"><?= count($u['external_userid'] ?? []) ?></span></td>
                                <td>
                                    <button class="btn btn-xs blue" onclick="fillDimission('<?= htmlspecialchars($u['handover_userid'] ?? '') ?>','<?= htmlspecialchars(implode('\n', $u['external_userid'] ?? [])) ?>')">
                                        <i class="fa fa-exchange"></i> 分配
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 分配表单 + 结果 -->
    <div class="col-md-6">
        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-exchange"></i> <span class="caption-subject bold uppercase">执行分配</span></div></div>
            <div class="portlet-body">
                <form method="post">
                    <input type="hidden" name="action" value="dimission_transfer">
                    <input type="hidden" name="tab" value="dimission">
                    <div class="form-group">
                        <label>离职成员 UserID <span class="text-danger">*</span></label>
                        <input type="text" name="handover_userid" id="d_handover" class="form-control" placeholder="zhangsan" required>
                    </div>
                    <div class="form-group">
                        <label>接替成员 <span class="text-danger">*</span></label>
                        <select name="takeover_userid" id="d_takeover" class="form-control" required>
                            <option value="">-- 选择接替成员 --</option>
                            <?php foreach ($members as $m): ?>
                            <option value="<?= htmlspecialchars($m['userid']) ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['userid']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>客户 External UserID（每行一个）<span class="text-danger">*</span></label>
                        <textarea name="external_userids" id="d_external" class="form-control" rows="5" 
                                  placeholder="wmxxxxx1&#10;wmxxxxx2" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-exchange"></i> 提交分配</button>
                </form>

                <!-- 查询结果 -->
                <hr>
                <h5><i class="fa fa-search"></i> 查询继承结果</h5>
                <form method="post" class="form-inline">
                    <input type="hidden" name="action" value="dimission_check">
                    <input type="text" name="handover_userid" class="form-control input-sm" placeholder="原成员" style="width:130px">
                    <input type="text" name="takeover_userid" class="form-control input-sm" placeholder="新成员" style="width:130px">
                    <button type="submit" class="btn btn-sm btn-info">查询</button>
                </form>

                <?php if (!empty($transferResult) && ($_POST['action'] ?? '') === 'dimission_check'): ?>
                <div class="result-table-wrap" style="margin-top:10px">
                    <table class="table table-striped table-bordered">
                        <thead><tr><th>客户ID</th><th>状态</th><th>时间</th></tr></thead>
                        <tbody>
                            <?php foreach ($transferResult as $r): ?>
                            <tr>
                                <td><code style="font-size:11px"><?= htmlspecialchars($r['external_userid'] ?? '') ?></code></td>
                                <td>
                                    <?php $sc = $r['errcode'] ?? -1; ?>
                                    <?php if ($sc === 0): ?><span class="label label-sm label-success">已成功</span>
                                    <?php elseif ($sc === 84062): ?><span class="label label-sm label-warning">等待确认</span>
                                    <?php else: ?><span class="label label-sm label-danger">失败(<?= $sc ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:11px"><?= $sc === 0 ? date('m-d H:i', $r['takeover_time'] ?? 0) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ===== 在职继承 ===== -->
<div class="row">
    <div class="col-md-6">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-exchange"></i> <span class="caption-subject bold uppercase">在职客户转接</span></div>
            </div>
            <div class="portlet-body">
                <div class="alert alert-info" style="font-size:13px">
                    <i class="fa fa-info-circle"></i> 
                    在职继承用于将一位在职成员的客户转交给另一位成员。<br>
                    客户将在24小时内确认，确认前仍由原成员服务。
                </div>

                <form method="post">
                    <input type="hidden" name="action" value="active_transfer">
                    <input type="hidden" name="tab" value="active">
                    
                    <div class="form-group">
                        <label>原跟进成员 <span class="text-danger">*</span></label>
                        <select name="handover_userid" class="form-control" required>
                            <option value="">-- 选择原跟进成员 --</option>
                            <?php foreach ($members as $m): ?>
                            <option value="<?= htmlspecialchars($m['userid']) ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['userid']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <p class="help-block">选择需要将其客户转出的成员</p>
                    </div>

                    <div class="form-group">
                        <label>新接替成员 <span class="text-danger">*</span></label>
                        <select name="takeover_userid" class="form-control" required>
                            <option value="">-- 选择新接替成员 --</option>
                            <?php foreach ($members as $m): ?>
                            <option value="<?= htmlspecialchars($m['userid']) ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['userid']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <p class="help-block">选择接收客户的成员</p>
                    </div>

                    <div class="form-group">
                        <label>待转接客户 External UserID <span class="text-danger">*</span></label>
                        <textarea name="external_userids" class="form-control" rows="6" 
                                  placeholder="每行一个 External UserID&#10;wmxxxxx1&#10;wmxxxxx2&#10;wmxxxxx3" required></textarea>
                        <p class="help-block">
                            每行一个客户 External UserID<br>
                            也可以从 <a href="/external/contacts" target="_blank">客户列表</a> 查看客户的 External UserID
                        </p>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                        <i class="fa fa-exchange"></i> 提交转接
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-search"></i> <span class="caption-subject bold uppercase">查询转接结果</span></div></div>
            <div class="portlet-body">
                <form method="post" class="form-horizontal">
                    <input type="hidden" name="action" value="active_check">
                    <div class="form-group">
                        <label class="col-md-4 control-label">原跟进成员</label>
                        <div class="col-md-8">
                            <input type="text" name="handover_userid" class="form-control input-sm" placeholder="UserID">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label">新接替成员</label>
                        <div class="col-md-8">
                            <input type="text" name="takeover_userid" class="form-control input-sm" placeholder="UserID">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-info btn-block">查询结果</button>
                </form>

                <?php if (!empty($transferResult) && ($_POST['action'] ?? '') === 'active_check'): ?>
                <hr>
                <h6><i class="fa fa-list"></i> 转接结果</h6>
                <div class="result-table-wrap">
                    <table class="table table-striped table-bordered">
                        <thead><tr><th>客户ID</th><th>状态</th><th>时间</th></tr></thead>
                        <tbody>
                            <?php foreach ($transferResult as $r): ?>
                            <tr>
                                <td><code style="font-size:10px"><?= htmlspecialchars($r['external_userid'] ?? '') ?></code></td>
                                <td>
                                    <?php $sc = $r['errcode'] ?? -1; ?>
                                    <?php if ($sc === 0): ?><span class="label label-sm label-success">成功</span>
                                    <?php elseif ($sc === 84062): ?><span class="label label-sm label-warning">等待确认</span>
                                    <?php else: ?><span class="label label-sm label-danger">失败(<?= $sc ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:11px"><?= $sc === 0 ? date('m-d H:i', $r['takeover_time'] ?? 0) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <hr>
                <div class="alert alert-default" style="font-size:13px;background:#f9f9f9">
                    <h6><i class="fa fa-lightbulb-o"></i> 在职继承说明</h6>
                    <ul style="margin-bottom:0;padding-left:16px">
                        <li>每个成员每天最多转接 <strong>100 个客户</strong></li>
                        <li>转接后 <strong>24小时内</strong> 客户未确认，系统将自动完成转接</li>
                        <li>转接成功后，原成员将无法再服务该客户</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function fillDimission(handover, externalIds) {
    var hEl = document.getElementById('d_handover');
    var eEl = document.getElementById('d_external');
    if (hEl) hEl.value = handover;
    if (eEl) eEl.value = externalIds.replace(/\\n/g, '\n');
    if (eEl) eEl.scrollIntoView({ behavior: 'smooth' });
}
</script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

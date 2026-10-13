<?php
/**
 * 管理后台 - 审批列表（细化版）
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '审批管理';
$error = ''; $success = '';
$approvals = [];
$templates = [];
$detail = null;

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'approve':
                $wx->approval->approve($_POST['sp_no'], 1, $_POST['comment'] ?? '');
                $success = '审批已通过';
                break;
            case 'reject':
                $wx->approval->approve($_POST['sp_no'], 2, $_POST['comment'] ?? '');
                $success = '审批已驳回';
                break;
        }
    } catch (\Throwable $e) { $error = $e->getMessage(); }
}

	// 获取审批详情
	if (isset($_GET['detail'])) {
	    try {
	        $result = $wx->approval->getApprovalDetail($_GET['detail']);
	        $detail = $result['info'] ?? [];
	    } catch (\Throwable $e) { $error = $e->getMessage(); }
	}

// 日期筛选参数
$filterStart = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$filterEnd = $_GET['end'] ?? date('Y-m-d');
$filterStatus = (int)($_GET['status'] ?? 0);

	// 获取审批列表
	if (!isset($_GET['detail'])) {
	    try {
	        $result = $wx->approval->getApprovalInfo([
	            'starttime' => strtotime($filterStart),
	            'endtime'   => strtotime($filterEnd . ' 23:59:59'),
	            'size'      => 100,
	        ]);
	        $spNoList = $result['sp_no_list'] ?? [];
	        // 获取每个审批详情（前30个）
	        foreach (array_slice($spNoList, 0, 30) as $sp) {
	            try {
	                $detailResult = $wx->approval->getApprovalDetail($sp['sp_no']);
	                $info = $detailResult['info'] ?? [];
	                // 状态筛选
	                if ($filterStatus > 0 && ($info['sp_status'] ?? 0) !== $filterStatus) continue;
	                $approvals[] = $info;
	            } catch (\Throwable $e) {}
            }

            // CSV 导出
            if (isset($_GET['export'])) {
                $statusMap = [1=>'审批中',2=>'已通过',3=>'已驳回',4=>'已撤销'];
                $headers = ['审批单号', '模板名称', '申请人', '部门', '状态', '申请时间', '审批意见'];
                $rows = array_map(function($a) use ($statusMap) {
                    return [
                        'sp_no' => $a['sp_no'] ?? '', '模板名称' => $a['template_name'] ?? '',
                        '申请人' => $a['applyer']['userid'] ?? '', '部门' => $a['applyer']['department'] ?? '',
                        '状态' => $statusMap[$a['sp_status']??0] ?? '未知',
                        '申请时间' => date('Y-m-d H:i', (int)($a['apply_time']??0)),
                        '审批意见' => implode('; ', array_column($a['sp_record']??[], 'sp_comment')),
                    ];
                }, $approvals);
                exportCsv($headers, $rows, '审批记录_' . date('Ymd'));
	        }
	    } catch (\Throwable $e) { $error = $e->getMessage(); }
}

require __DIR__ . '/../../layout/header.php';
?>

<?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (!$detail): ?>
<div class="row">
    <div class="col-md-12">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="row">
                    <div class="col-md-12">
                        <div class="caption">
                            <div class="form-inline">
                                <a class="btn btn-shy btn-default tooltips" href="/approval/list" data-original-title="重置"><i class="fa fa-refresh"></i></a>
                                
                                <!-- 日期预设 -->
                                <button type="button" class="btn btn-xs btn-default" onclick="setDatePreset('<?= date('Y-m-d', strtotime('-7 days')) ?>','<?= date('Y-m-d') ?>')">近7天</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="setDatePreset('<?= date('Y-m-d', strtotime('-30 days')) ?>','<?= date('Y-m-d') ?>')">近30天</button>
                                
                                <select class="form-control input-sm" onchange="location.href='?status='+this.value+'&start=<?= urlencode($filterStart) ?>&end=<?= urlencode($filterEnd) ?>'" style="width:110px;margin-left:5px">
                                    <option value="0" <?= $filterStatus===0?'selected':'' ?>>全部状态</option>
                                    <option value="1" <?= $filterStatus===1?'selected':'' ?>>审批中</option>
                                    <option value="2" <?= $filterStatus===2?'selected':'' ?>>已通过</option>
                                    <option value="3" <?= $filterStatus===3?'selected':'' ?>>已驳回</option>
                                    <option value="4" <?= $filterStatus===4?'selected':'' ?>>已撤销</option>
                                </select>
                                
                                <span class="text-muted" style="font-size:12px;margin-left:5px">
                                    <?= htmlspecialchars($filterStart) ?> ~ <?= htmlspecialchars($filterEnd) ?>
                                </span>
                                <span class="text-muted pull-right" style="margin-left:10px">共 <?= count($approvals) ?> 条</span>
                                <a href="/approval/list?status=<?= $filterStatus ?>&start=<?= urlencode($filterStart) ?>&end=<?= urlencode($filterEnd) ?>&export=1" class="btn btn-xs btn-success pull-right" style="margin-right:5px"><i class="fa fa-download"></i> 导出</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="portlet-body table-responsive">
                <table class="listTable table table-striped table-bordered table-hover dataTable no-footer">
                    <thead>
                        <tr>
                            <th width="150">审批单号</th>
                            <th>模板名称</th>
                            <th>申请人</th>
                            <th>部门</th>
                            <th width="120" class="text-center">状态</th>
                            <th width="120" class="text-center">申请时间</th>
                            <th width="150" class="text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approvals as $a): ?>
                        <tr>
                            <td><code style="font-size:10px"><?= htmlspecialchars($a['sp_no'] ?? '-') ?></code></td>
                            <td><?= htmlspecialchars($a['template_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($a['applyer']['userid'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($a['applyer']['department'] ?? '-') ?></td>
                            <td class="text-center">
                                <?php $st = $a['sp_status'] ?? 0; ?>
                                <?php if ($st === 1): ?><span class="label label-sm label-info">审批中</span>
                                <?php elseif ($st === 2): ?><span class="label label-sm label-success">已通过</span>
                                <?php elseif ($st === 3): ?><span class="label label-sm label-danger">已驳回</span>
                                <?php elseif ($st === 4): ?><span class="label label-sm label-default">已撤销</span>
                                <?php else: ?><span class="label label-sm label-default"><?= $st ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= date('Y-m-d H:i', (int)($a['apply_time'] ?? 0)) ?></td>
                            <td class="text-center">
                                <a href="/approval/list?detail=<?= urlencode($a['sp_no']) ?>" class="btn btn-xs blue"><i class="fa fa-eye"></i> 详情</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($approvals)): ?>
                        <tr><td colspan="7" class="text-center text-muted">暂无审批数据（近30天）</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="row">
    <div class="col-md-12">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-file-text-o"></i> <span class="caption-subject bold uppercase">审批详情</span>
                </div>
                <div class="actions">
                    <a href="/approval/list" class="btn btn-sm default"><i class="fa fa-arrow-left"></i> 返回列表</a>
                </div>
            </div>
            <div class="portlet-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4><i class="fa fa-file-text-o text-primary"></i> <?= htmlspecialchars($detail['template_name'] ?? '未知模板') ?></h4>
                        <hr>
                        <table class="table table-bordered">
                            <tr><td width="120"><strong>审批单号</strong></td><td><code><?= htmlspecialchars($detail['sp_no'] ?? '-') ?></code></td></tr>
                            <tr><td><strong>状态</strong></td><td>
                                <?php $st = $detail['sp_status'] ?? 0; ?>
                                <?php if ($st === 1): ?><span class="label label-sm label-info">审批中</span>
                                <?php elseif ($st === 2): ?><span class="label label-sm label-success">已通过</span>
                                <?php elseif ($st === 3): ?><span class="label label-sm label-danger">已驳回</span>
                                <?php elseif ($st === 4): ?><span class="label label-sm label-default">已撤销</span>
                                <?php endif; ?>
                            </td></tr>
                            <tr><td><strong>申请人</strong></td><td><?= htmlspecialchars($detail['applyer']['userid'] ?? '-') ?> (<?= htmlspecialchars($detail['applyer']['department'] ?? '') ?>)</td></tr>
                            <tr><td><strong>申请时间</strong></td><td><?= date('Y-m-d H:i:s', (int)($detail['apply_time'] ?? 0)) ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <?php if ($st === 1): ?>
                        <div class="well well-sm">
                            <h5><i class="fa fa-gavel"></i> 审批操作</h5>
                            <hr>
                            <form method="post" class="form-inline">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="sp_no" value="<?= htmlspecialchars($detail['sp_no']) ?>">
                                <input type="text" name="comment" class="form-control input-sm" placeholder="审批意见（可选）" style="width:200px">
                                <button type="submit" class="btn btn-sm green" onclick="return confirm('确定通过该审批？')"><i class="fa fa-check"></i> 通过</button>
                            </form>
                            <form method="post" class="form-inline" style="margin-top:10px">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="sp_no" value="<?= htmlspecialchars($detail['sp_no']) ?>">
                                <input type="text" name="comment" class="form-control input-sm" placeholder="驳回原因（可选）" style="width:200px">
                                <button type="submit" class="btn btn-sm red" onclick="return confirm('确定驳回该审批？')"><i class="fa fa-times"></i> 驳回</button>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($detail['apply_data']['contents'])): ?>
                        <h5><i class="fa fa-list"></i> 申请内容</h5>
                        <hr>
                        <table class="table table-striped">
                            <?php foreach ($detail['apply_data']['contents'] as $content): ?>
                            <tr>
                                <td width="150"><strong><?= htmlspecialchars($content['title'] ?? '') ?></strong></td>
                                <td>
                                    <?php if (!empty($content['value']['text'])): ?>
                                        <?= nl2br(htmlspecialchars($content['value']['text'])) ?>
                                    <?php elseif (!empty($content['value']['members'])): ?>
                                        <?php foreach ($content['value']['members'] as $m): ?>
                                            <span class="label label-sm label-default"><?= htmlspecialchars($m['name'] ?? '') ?></span>
                                        <?php endforeach; ?>
                                    <?php elseif (!empty($content['value']['files'])): ?>
                                        <?php foreach ($content['value']['files'] as $f): ?>
                                            <a href="<?= htmlspecialchars($f['file_url'] ?? '#') ?>" class="btn btn-xs default" target="_blank"><i class="fa fa-file"></i> <?= htmlspecialchars($f['file_name'] ?? '文件') ?></a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                        <?php endif; ?>
                        
                        <?php if (!empty($detail['sp_record'])): ?>
                        <h5><i class="fa fa-history"></i> 审批流程</h5>
                        <hr>
                        <ul class="list-unstyled">
                            <?php foreach ($detail['sp_record'] as $record): ?>
                            <li style="padding:8px 0;border-bottom:1px solid #eee">
                                <i class="fa fa-circle text-<?= $record['sp_status'] === 2 ? 'success' : ($record['sp_status'] === 3 ? 'danger' : 'info') ?>"></i>
                                <strong><?= htmlspecialchars($record['userid'] ?? '') ?></strong>
                                <span class="text-muted"><?= date('Y-m-d H:i', (int)($record['sptime'] ?? 0)) ?></span>
                                <span class="label label-sm label-<?= $record['sp_status'] === 2 ? 'success' : ($record['sp_status'] === 3 ? 'danger' : 'default') ?>">
                                    <?= $record['sp_status'] === 1 ? '待审批' : ($record['sp_status'] === 2 ? '已通过' : '已驳回') ?>
                                </span>
                                <?php if (!empty($record['sp_comment'])): ?>
                                    <br><small class="text-muted">意见：<?= htmlspecialchars($record['sp_comment']) ?></small>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../layout/footer.php'; ?>
<script>
function setDatePreset(start, end) {
    location.href = '?status=<?= $filterStatus ?>&start=' + start + '&end=' + end;
}
</script>

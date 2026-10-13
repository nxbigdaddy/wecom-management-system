<?php
/**
 * 管理后台 - 回调日志（细化版：搜索+筛选+详情）
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '回调日志';
$logs = [];
$error = '';
$page = max(1, (int)($_GET['page'] ?? 1));
$size = 30;
$filterEvent = $_GET['event'] ?? '';
$keyword = trim($_GET['keyword'] ?? '');

try {
    $db = $wx->getDatabase();
    $where = '1=1';
    $params = [];
    if ($filterEvent) { $where .= ' AND event_type = ?'; $params[] = $filterEvent; }
    if ($keyword) { $where .= ' AND (event_type LIKE ? OR change_type LIKE ? OR handler LIKE ?)'; $params[] = "%$keyword%"; $params[] = "%$keyword%"; $params[] = "%$keyword%"; }
    
    $total = (int)$db->selectValue('SELECT COUNT(*) FROM wxwork_callback_log WHERE ' . $where, $params);
    $logs = $db->select(
        'SELECT * FROM wxwork_callback_log WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . (($page-1)*$size) . ', ' . $size,
        $params
    );
    
    // CSV 导出
    if (isset($_GET['export'])) {
        $allLogs = $db->select("SELECT * FROM wxwork_callback_log WHERE {$where} ORDER BY id DESC", $params);
        $headers = ['ID', '时间', '事件类型', '变更类型', '处理器', '结果', '耗时(ms)', '错误信息'];
        $rows = array_map(function($log) {
            return [
                'id' => $log['id'], '时间' => $log['created_at'], '事件类型' => $log['event_type'],
                '变更类型' => $log['change_type'] ?? '', '处理器' => $log['handler'] ?? '',
                '结果' => $log['handle_result'] ?? '', '耗时' => $log['handle_time_ms'] ?? 0,
                '错误信息' => $log['error_msg'] ?? '',
            ];
        }, $allLogs);
        exportCsv($headers, $rows, '回调日志_' . date('Ymd'));
    }
} catch (\Throwable $e) { $error = $e->getMessage(); }

require __DIR__ . '/../../layout/header.php';
?>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row"><div class="col-md-12">
    <div class="portlet light">
        <div class="portlet-title">
            <div class="row">
                <div class="col-md-8 col-sm-8">
                    <div class="caption">
                        <div class="form-inline">
                            <a class="btn btn-shy btn-default tooltips" href="/callback/log" data-original-title="重置"><i class="fa fa-refresh"></i></a>
                            <select class="form-control input-sm" onchange="location.href='?event='+this.value+'&keyword=<?= urlencode($keyword) ?>'" style="width:160px">
                                <option value="">全部事件类型</option>
                                <option value="change_contact" <?= $filterEvent==='change_contact'?'selected':'' ?>>通讯录变更</option>
                                <option value="change_external_contact" <?= $filterEvent==='change_external_contact'?'selected':'' ?>>客户变更</option>
                                <option value="sys_approval_change" <?= $filterEvent==='sys_approval_change'?'selected':'' ?>>审批变更</option>
                            </select>
                            <form method="get" class="sch-form" style="display:inline">
                                <input type="hidden" name="event" value="<?= $filterEvent ?>">
                                <input type="text" name="keyword" class="form-control input-sm" placeholder="搜索事件/变更类型" value="<?= htmlspecialchars($keyword) ?>" style="width:200px">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-4">
                    <div class="pull-right">
                        <span class="text-muted">共 <?= $total ?> 条记录</span>
                        <a href="/callback/log?event=<?= urlencode($filterEvent) ?>&keyword=<?= urlencode($keyword) ?>&export=1" class="btn btn-xs btn-success"><i class="fa fa-download"></i> 导出</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="portlet-body table-responsive">
            <table class="listTable table table-striped table-bordered table-hover dataTable no-footer">
                <thead>
                    <tr><th width="60" class="text-center">ID</th><th width="160">时间</th><th>事件类型</th><th>变更类型</th><th>处理器</th><th width="80" class="text-center">结果</th><th width="80" class="text-center">耗时</th><th width="100" class="text-center">操作</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-center"><?= $log['id'] ?></td>
                        <td><?= $log['created_at'] ?></td>
                        <td><span class="label label-sm label-info"><?= htmlspecialchars($log['event_type']) ?></span></td>
                        <td><?= htmlspecialchars($log['change_type'] ?? '-') ?></td>
                        <td><code style="font-size:11px"><?= htmlspecialchars($log['handler'] ?? '-') ?></code></td>
                        <td class="text-center"><?= $log['handle_result']==='success'?'<span class="label label-sm label-success">成功</span>':'<span class="label label-sm label-danger">失败</span>' ?></td>
                        <td class="text-center"><?= $log['handle_time_ms'] ?? 0 ?>ms</td>
                        <td class="text-center">
                            <button class="btn btn-xs blue" onclick="showDetail(<?= $log['id'] ?>)"><i class="fa fa-eye"></i> 详情</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="8" class="text-center">暂无回调日志，配置回调URL后事件将记录在此</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total > $size): ?>
        <div class="portlet-footer">
            <div class="pull-right">
                <ul class="pagination" style="margin:0">
                    <?php $totalPages = ceil($total / $size); ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="<?= $i === $page ? 'active' : '' ?>">
                            <a href="?event=<?= $filterEvent ?>&keyword=<?= urlencode($keyword) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div></div>

<!-- 详情 Modal -->
<div class="modal fade" id="detailModal">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">回调详情</h4></div>
        <div class="modal-body">
            <div id="detailContent"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">关闭</button></div>
    </div></div>
</div>

<script>
function showDetail(id) {
    $.get('/callback/log/detail?id='+id, function(data) {
        $('#detailContent').html('<pre style="background:#f5f5f5;padding:15px;border-radius:4px;max-height:500px;overflow-y:auto">'+JSON.stringify(data, null, 2)+'</pre>');
        $('#detailModal').modal('show');
    });
}
</script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

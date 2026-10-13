<?php
/**
 * 管理后台 - 消息发送记录（细化版：搜索+筛选+详情）
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '消息记录';
$error = '';
$logs = [];
$page = max(1, (int)($_GET['page'] ?? 1));
$size = 20;
$filterType = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$keyword = trim($_GET['keyword'] ?? '');

try {
    $db = $wx->getDatabase();
    $where = '1=1';
    $params = [];
    if ($filterType) { $where .= ' AND msg_type = ?'; $params[] = $filterType; }
    if ($filterStatus !== '') { $where .= ' AND status = ?'; $params[] = (int)$filterStatus; }
    if ($keyword) { $where .= ' AND (to_users LIKE ? OR msg_id LIKE ?)'; $params[] = "%$keyword%"; $params[] = "%$keyword%"; }
    
    $total = (int)$db->selectValue('SELECT COUNT(*) FROM wxwork_message_log WHERE ' . $where, $params);
    $logs = $db->select(
        'SELECT * FROM wxwork_message_log WHERE ' . $where . ' ORDER BY id DESC LIMIT ' . (($page-1)*$size) . ', ' . $size,
        $params
    );
    
    // CSV 导出
    if (isset($_GET['export'])) {
        $allLogs = $db->select("SELECT * FROM wxwork_message_log WHERE {$where} ORDER BY id DESC", $params);
        $statusMap = [0=>'待发送',1=>'成功',2=>'失败'];
        $headers = ['ID', '时间', '类型', '接收人', 'MsgID', '状态', '错误信息', '发送人'];
        $rows = array_map(function($log) use ($statusMap) {
            return [
                'id' => $log['id'], '时间' => $log['created_at'], '类型' => $log['msg_type'],
                '接收人' => $log['to_users'] ?? '', 'MsgID' => $log['msg_id'] ?? '',
                '状态' => $statusMap[$log['status']??0] ?? '未知',
                '错误信息' => $log['errmsg'] ?? '', '发送人' => $log['sent_by'] ?? '',
            ];
        }, $allLogs);
        exportCsv($headers, $rows, '消息记录_' . date('Ymd'));
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
                            <a class="btn btn-shy btn-default tooltips" href="/messages/log" data-original-title="重置筛选"><i class="fa fa-refresh"></i></a>
                            <select class="form-control input-sm" onchange="location.href='?type='+this.value+'&status=<?= $filterStatus ?>&keyword=<?= urlencode($keyword) ?>'" style="width:120px">
                                <option value="">全部类型</option>
                                <option value="text" <?= $filterType==='text'?'selected':'' ?>>文本</option>
                                <option value="textcard" <?= $filterType==='textcard'?'selected':'' ?>>文本卡片</option>
                                <option value="news" <?= $filterType==='news'?'selected':'' ?>>图文</option>
                                <option value="image" <?= $filterType==='image'?'selected':'' ?>>图片</option>
                                <option value="file" <?= $filterType==='file'?'selected':'' ?>>文件</option>
                            </select>
                            <select class="form-control input-sm" onchange="location.href='?type=<?= $filterType ?>&status='+this.value+'&keyword=<?= urlencode($keyword) ?>'" style="width:100px">
                                <option value="">全部状态</option>
                                <option value="0" <?= $filterStatus==='0'?'selected':'' ?>>待发送</option>
                                <option value="1" <?= $filterStatus==='1'?'selected':'' ?>>成功</option>
                                <option value="2" <?= $filterStatus==='2'?'selected':'' ?>>失败</option>
                            </select>
                            <form method="get" class="sch-form" style="display:inline">
                                <input type="hidden" name="type" value="<?= $filterType ?>">
                                <input type="hidden" name="status" value="<?= $filterStatus ?>">
                                <input type="text" name="keyword" class="form-control input-sm" placeholder="搜索UserID/MsgID" value="<?= htmlspecialchars($keyword) ?>" style="width:200px">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-4">
                    <div class="pull-right">
                        <span class="text-muted">共 <?= $total ?> 条记录</span>
                        <a href="/messages/log?type=<?= urlencode($filterType) ?>&status=<?= $filterStatus ?>&keyword=<?= urlencode($keyword) ?>&export=1" class="btn btn-xs btn-success" style="margin-left:5px"><i class="fa fa-download"></i> 导出</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="portlet-body table-responsive">
            <table class="listTable table table-striped table-bordered table-hover dataTable no-footer">
                <thead>
                    <tr><th width="60" class="text-center">ID</th><th width="150">时间</th><th width="100" class="text-center">类型</th><th>接收人</th><th width="120" class="text-center">MsgID</th><th width="80" class="text-center">状态</th><th>错误信息</th><th width="80" class="text-center">耗时</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-center"><?= $log['id'] ?></td>
                        <td><?= $log['created_at'] ?></td>
                        <td class="text-center"><span class="label label-sm label-info"><?= htmlspecialchars($log['msg_type']) ?></span></td>
                        <td><code style="font-size:11px"><?= htmlspecialchars(mb_substr($log['to_users'] ?? '-', 0, 50)) ?></code></td>
                        <td class="text-center"><code><?= htmlspecialchars($log['msg_id'] ?? '-') ?></code></td>
                        <td class="text-center">
                            <?php if ($log['status'] == 1): ?><span class="label label-sm label-success">成功</span>
                            <?php elseif ($log['status'] == 2): ?><span class="label label-sm label-danger">失败</span>
                            <?php else: ?><span class="label label-sm label-default">待发送</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-danger"><?= htmlspecialchars($log['errmsg'] ?? '') ?></td>
                        <td class="text-center"><?= $log['elapsed_ms'] ?? 0 ?>ms</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="8" class="text-center">暂无发送记录</td></tr>
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
                            <a href="?type=<?= $filterType ?>&status=<?= $filterStatus ?>&keyword=<?= urlencode($keyword) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div></div>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

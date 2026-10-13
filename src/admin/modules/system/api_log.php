<?php
/**
 * 管理后台 - API 请求日志
 * 
 * 查看所有企业微信 API 调用记录。
 * 用于调试：排查 Token 过期、网络错误、API 限流等问题。
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = 'API 请求日志';
$error = '';
$logs = [];
$stats = ['total' => 0, 'success' => 0, 'fail' => 0, 'avg_ms' => 0];

$page = max(1, (int)($_GET['page'] ?? 1));
$size = 30;
$filterMethod = $_GET['method'] ?? '';
$filterCode = $_GET['errcode'] ?? '';
$keyword = trim($_GET['keyword'] ?? '');

try {
    $db = $wx->getDatabase();
    $where = '1=1';
    $params = [];
    if ($filterMethod) { $where .= ' AND method=?'; $params[] = $filterMethod; }
    if ($filterCode !== '') {
        if ($filterCode === '0') { $where .= ' AND response_errcode=0'; }
        else { $where .= ' AND response_errcode!=0 AND response_errcode IS NOT NULL'; }
    }
    if ($keyword) { $where .= ' AND (url LIKE ? OR response_errmsg LIKE ?)'; $params[] = "%$keyword%"; $params[] = "%$keyword%"; }

    $total = (int)($db->selectValue("SELECT COUNT(*) FROM wxwork_api_log WHERE {$where}", $params) ?? 0);
    $logs = $db->select(
        "SELECT * FROM wxwork_api_log WHERE {$where} ORDER BY id DESC LIMIT " . (($page-1)*$size) . ", {$size}",
        $params
    );

    // 统计
    $stats['total'] = $total;
    $stats['success'] = (int)($db->selectValue("SELECT COUNT(*) FROM wxwork_api_log WHERE response_errcode=0") ?? 0);
    $stats['fail'] = (int)($db->selectValue("SELECT COUNT(*) FROM wxwork_api_log WHERE response_errcode!=0 AND response_errcode IS NOT NULL") ?? 0);
    $stats['avg_ms'] = (int)($db->selectValue("SELECT AVG(elapsed_ms) FROM wxwork_api_log") ?? 0);
} catch (\Throwable $e) { $error = $e->getMessage(); }

require __DIR__ . '/../../layout/header.php';
?>

<style>
.api-url { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11px; }
.api-body { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 10px; }
.log-detail pre { font-size: 11px; max-height: 350px; overflow: auto; background: #f5f5f5; padding: 10px; border-radius: 4px; }
</style>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row">
    <!-- 统计卡片 -->
    <div class="col-md-12">
        <div class="row">
            <div class="col-md-3 col-xs-6">
                <div class="dashboard-stat blue"><div class="visual"><i class="fa fa-cloud"></i></div>
                    <div class="details"><div class="number"><?= number_format($stats['total']) ?></div><div class="desc">总请求数</div></div>
                </div>
            </div>
            <div class="col-md-3 col-xs-6">
                <div class="dashboard-stat green"><div class="visual"><i class="fa fa-check"></i></div>
                    <div class="details"><div class="number"><?= number_format($stats['success']) ?></div><div class="desc">成功请求</div></div>
                </div>
            </div>
            <div class="col-md-3 col-xs-6">
                <div class="dashboard-stat red"><div class="visual"><i class="fa fa-times"></i></div>
                    <div class="details"><div class="number"><?= number_format($stats['fail']) ?></div><div class="desc">失败请求</div></div>
                </div>
            </div>
            <div class="col-md-3 col-xs-6">
                <div class="dashboard-stat purple"><div class="visual"><i class="fa fa-clock-o"></i></div>
                    <div class="details"><div class="number"><?= $stats['avg_ms'] ?>ms</div><div class="desc">平均耗时</div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row"><div class="col-md-12">
    <div class="portlet light">
        <div class="portlet-title">
            <div class="row">
                <div class="col-md-10">
                    <div class="caption"><div class="form-inline">
                        <a class="btn btn-shy btn-default" href="/system/api_log"><i class="fa fa-refresh"></i></a>
                        <select class="form-control input-sm" onchange="location.href='?method='+this.value+'&errcode=<?= $filterCode ?>&keyword=<?= urlencode($keyword) ?>'" style="width:100px">
                            <option value="">全部方法</option>
                            <option value="GET" <?= $filterMethod==='GET'?'selected':'' ?>>GET</option>
                            <option value="POST" <?= $filterMethod==='POST'?'selected':'' ?>>POST</option>
                        </select>
                        <select class="form-control input-sm" onchange="location.href='?method=<?= $filterMethod ?>&errcode='+this.value+'&keyword=<?= urlencode($keyword) ?>'" style="width:100px">
                            <option value="">全部结果</option>
                            <option value="0" <?= $filterCode==='0'?'selected':'' ?>>成功</option>
                            <option value="1" <?= $filterCode==='1'?'selected':'' ?>>失败</option>
                        </select>
                        <form method="get" style="display:inline">
                            <input type="hidden" name="method" value="<?= $filterMethod ?>">
                            <input type="hidden" name="errcode" value="<?= $filterCode ?>">
                            <input type="text" name="keyword" class="form-control input-sm" placeholder="搜索 URL/错误信息" value="<?= htmlspecialchars($keyword) ?>" style="width:200px">
                            <button class="btn btn-primary btn-sm"><i class="fa fa-search"></i></button>
                        </form>
                    </div></div>
                </div>
                <div class="col-md-2"><div class="pull-right"><span class="text-muted">共 <?= $total ?> 条</span></div></div>
            </div>
        </div>
        <div class="portlet-body table-responsive">
            <table class="table table-striped table-hover" style="font-size:12px">
                <thead><tr>
                    <th width="50">ID</th><th width="55">方法</th><th>URL</th><th width="70">HTTP</th>
                    <th width="50">errcode</th><th width="70">耗时</th><th>错误信息</th><th width="90">时间</th><th width="60">操作</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $log['id'] ?></td>
                        <td><span class="label label-sm label-<?= $log['method']==='POST'?'warning':'info' ?>"><?= $log['method'] ?></span></td>
                        <td><div class="api-url" title="<?= htmlspecialchars($log['url'] ?? '') ?>"><?= htmlspecialchars($log['url'] ?? '') ?></div></td>
                        <td><span class="label label-sm label-<?= ($log['response_http_code']??200)===200?'success':'danger' ?>"><?= $log['response_http_code'] ?? 200 ?></span></td>
                        <td class="text-center">
                            <?php $ec = $log['response_errcode']; ?>
                            <?php if ($ec === null): ?><span class="text-muted">-</span>
                            <?php elseif ((int)$ec === 0): ?><span class="label label-sm label-success">0</span>
                            <?php else: ?><span class="label label-sm label-danger"><?= $ec ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= $log['elapsed_ms'] ?? 0 ?>ms</td>
                        <td style="color:#f44336;font-size:11px"><?= htmlspecialchars(mb_substr($log['response_errmsg'] ?? '', 0, 80)) ?></td>
                        <td style="font-size:10px"><?= date('m-d H:i', strtotime($log['created_at'] ?? '')) ?></td>
                        <td><button class="btn btn-xs blue" onclick="showApiDetail(<?= htmlspecialchars(json_encode($log, JSON_UNESCAPED_UNICODE)) ?>)"><i class="fa fa-eye"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="9" class="text-center text-muted">暂无 API 请求记录</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total > $size): ?>
        <div class="portlet-footer"><div class="pull-right">
            <ul class="pagination" style="margin:0">
                <?php $tp = ceil($total/$size); ?>
                <?php for ($i=1; $i<=min($tp, 20); $i++): ?>
                <li class="<?= $i===$page?'active':'' ?>"><a href="?method=<?= $filterMethod ?>&errcode=<?= $filterCode ?>&keyword=<?= urlencode($keyword) ?>&page=<?= $i ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <?php if ($tp>20): ?><li><span>...<?= $tp ?></span></li><?php endif; ?>
            </ul>
        </div></div>
        <?php endif; ?>
    </div>
</div></div>

<!-- 详情 Modal -->
<div class="modal fade" id="apiDetailModal"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4>API 请求详情</h4></div>
    <div class="modal-body log-detail"><div id="apiDetailContent"></div></div>
    <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">关闭</button></div>
</div></div></div>

<script>
function showApiDetail(log) {
    var html = '<table class="table table-bordered">';
    html += '<tr><td width="100"><strong>ID</strong></td><td>'+log.id+'</td></tr>';
    html += '<tr><td><strong>方法</strong></td><td>'+log.method+'</td></tr>';
    html += '<tr><td><strong>URL</strong></td><td><code>'+escapeHtml(log.url||'')+'</code></td></tr>';
    html += '<tr><td><strong>HTTP状态</strong></td><td>'+log.response_http_code+'</td></tr>';
    html += '<tr><td><strong>errcode</strong></td><td>'+log.response_errcode+'</td></tr>';
    html += '<tr><td><strong>errmsg</strong></td><td>'+escapeHtml(log.response_errmsg||'')+'</td></tr>';
    html += '<tr><td><strong>耗时</strong></td><td>'+log.elapsed_ms+'ms</td></tr>';
    html += '<tr><td><strong>重试次数</strong></td><td>'+log.retry_count+'</td></tr>';
    html += '<tr><td><strong>请求体</strong></td><td><pre>'+escapeHtml(JSON.stringify(JSON.parse(log.request_body||'{}'),null,2))+'</pre></td></tr>';
    html += '<tr><td><strong>响应体</strong></td><td><pre>'+escapeHtml(truncateJson(log.response_body||'',2000))+'</pre></td></tr>';
    html += '<tr><td><strong>时间</strong></td><td>'+log.created_at+'</td></tr>';
    html += '</table>';
    document.getElementById('apiDetailContent').innerHTML = html;
    if(typeof jQuery !== 'undefined') jQuery('#apiDetailModal').modal('show');
}

function escapeHtml(t) { var d=document.createElement('div');d.textContent=t;return d.innerHTML; }
function truncateJson(s, max) { return s.length > max ? s.substring(0,max)+'...[truncated]' : s; }
</script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

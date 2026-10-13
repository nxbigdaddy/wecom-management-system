<?php
/**
 * 管理后台 - 仪表盘（细化版：图表+最近操作）
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '仪表盘';
$config = $wx->getConfig();

$stats = ['members' => 0, 'departments' => 0, 'messages' => 0, 'tags' => 0];
$recentMessages = [];
$recentCallbacks = [];

try {
    $db = $wx->getDatabase();
    $stats['members'] = (int)($db->selectValue('SELECT COUNT(*) FROM wxwork_member WHERE is_deleted = 0') ?? 0);
    $stats['departments'] = (int)($db->selectValue('SELECT COUNT(*) FROM wxwork_department WHERE is_deleted = 0') ?? 0);
    $stats['messages'] = (int)($db->selectValue('SELECT COUNT(*) FROM wxwork_message_log WHERE DATE(created_at) = CURDATE()') ?? 0);
    $stats['tags'] = (int)($db->selectValue('SELECT COUNT(*) FROM wxwork_tag WHERE is_deleted = 0') ?? 0);
    
    // 最近 5 条消息
    $recentMessages = $db->select('SELECT * FROM wxwork_message_log ORDER BY id DESC LIMIT 5');
    // 最近 5 条回调
    $recentCallbacks = $db->select('SELECT * FROM wxwork_callback_log ORDER BY id DESC LIMIT 5');
} catch (\Throwable $e) {}

<?php require __DIR__ . '/../../layout/header.php'; ?>

<div class="page-head">
    <div class="page-title">
        <h1>后台首页<small>欢迎进入企业微信管理后台！</small></h1>
    </div>
</div>

<!-- 快捷操作 -->
<div class="row" style="margin-bottom:10px">
    <div class="col-md-12">
        <div class="portlet">
            <div class="portlet-body" style="padding:12px 15px">
                <span style="font-weight:600;margin-right:10px;color:#666"><i class="fa fa-bolt"></i> 快捷操作:</span>
                <a href="/messages/send" class="btn btn-xs blue"><i class="fa fa-paper-plane"></i> 发送消息</a>
                <a href="/contacts/members" class="btn btn-xs green"><i class="fa fa-users"></i> 成员管理</a>
                <a href="/external/contacts" class="btn btn-xs yellow"><i class="fa fa-address-book"></i> 客户管理</a>
                <a href="/smartsheet?tab=docs" class="btn btn-xs purple"><i class="fa fa-table"></i> 智能表格</a>
                <a href="/system/sync" class="btn btn-xs default"><i class="fa fa-refresh"></i> 数据同步</a>
                <a href="/system/api_log" class="btn btn-xs default"><i class="fa fa-cloud"></i> API日志</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="dashboard-stat blue">
            <div class="visual"><i class="fa fa-users"></i></div>
            <div class="details">
                <div class="number"><?= number_format($stats['members']) ?></div>
                <div class="desc">成员总数</div>
            </div>
            <a class="more" href="/contacts/members">查看详情 <i class="fa fa-angle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="dashboard-stat red">
            <div class="visual"><i class="fa fa-sitemap"></i></div>
            <div class="details">
                <div class="number"><?= number_format($stats['departments']) ?></div>
                <div class="desc">部门总数</div>
            </div>
            <a class="more" href="/contacts/departments">查看详情 <i class="fa fa-angle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="dashboard-stat green">
            <div class="visual"><i class="fa fa-envelope"></i></div>
            <div class="details">
                <div class="number"><?= number_format($stats['messages']) ?></div>
                <div class="desc">今日消息</div>
            </div>
            <a class="more" href="/messages/log">查看详情 <i class="fa fa-angle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="dashboard-stat purple">
            <div class="visual"><i class="fa fa-tags"></i></div>
            <div class="details">
                <div class="number"><?= number_format($stats['tags']) ?></div>
                <div class="desc">标签总数</div>
            </div>
            <a class="more" href="/contacts/tags">查看详情 <i class="fa fa-angle-right"></i></a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 col-sm-6">
        <div class="portlet">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-server font-blue"></i> <span class="caption-subject font-blue bold uppercase">系统状态</span></div>
            </div>
            <div class="portlet-body">
                <table class="table table-striped table-hover">
                    <tbody>
                        <tr><td style="width:120px"><strong>access_token</strong></td>
                            <td><?php try {$wx->getTokenManager()->getToken();echo '<span class="label label-sm label-success">正常</span>';}catch(\Throwable $e){echo '<span class="label label-sm label-danger">异常</span>';} ?></td></tr>
                        <tr><td><strong>Redis</strong></td>
                            <td><?= $wx->getRedis()->isAvailable() ? '<span class="label label-sm label-success">正常</span>' : '<span class="label label-sm label-warning">未连接</span>' ?></td></tr>
                        <tr><td><strong>MySQL</strong></td><td><span class="label label-sm label-success">正常</span></td></tr>
                        <tr><td><strong>PHP</strong></td><td><?= phpversion() ?></td></tr>
                        <tr><td><strong>企业ID</strong></td><td><code><?= htmlspecialchars($config->getCorpId() ?: '未配置') ?></code></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-sm-6">
        <div class="portlet">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-bolt font-red"></i> <span class="caption-subject font-red bold uppercase">最近消息</span></div>
            </div>
            <div class="portlet-body">
                <?php if (empty($recentMessages)): ?>
                    <p class="text-muted text-center">暂无消息记录</p>
                <?php else: ?>
                <ul class="list-unstyled">
                    <?php foreach ($recentMessages as $msg): ?>
                    <li style="padding:8px 0;border-bottom:1px solid #eee">
                        <span class="label label-sm label-info"><?= htmlspecialchars($msg['msg_type']) ?></span>
                        <span class="text-muted"><?= $msg['created_at'] ?></span>
                        <span class="<?= $msg['status']==1?'text-success':'text-danger' ?>"><?= $msg['status']==1?'成功':'失败' ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="/messages/log" class="btn btn-sm default pull-right">查看全部</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="portlet">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-bolt font-green"></i> <span class="caption-subject font-green bold uppercase">最近回调事件</span></div>
            </div>
            <div class="portlet-body">
                <?php if (empty($recentCallbacks)): ?>
                    <p class="text-muted text-center">暂无回调记录</p>
                <?php else: ?>
                <table class="table table-striped table-hover">
                    <thead><tr><th>时间</th><th>事件类型</th><th>变更类型</th><th>处理器</th><th>结果</th><th>耗时</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentCallbacks as $log): ?>
                        <tr>
                            <td><?= $log['created_at'] ?></td>
                            <td><span class="label label-sm label-info"><?= htmlspecialchars($log['event_type']) ?></span></td>
                            <td><?= htmlspecialchars($log['change_type'] ?? '-') ?></td>
                            <td><code style="font-size:11px"><?= htmlspecialchars($log['handler'] ?? '-') ?></code></td>
                            <td><?= $log['handle_result']==='success'?'<span class="label label-sm label-success">成功</span>':'<span class="label label-sm label-danger">失败</span>' ?></td>
                            <td><?= $log['handle_time_ms'] ?? 0 ?>ms</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="/callback/log" class="btn btn-sm default pull-right">查看全部</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

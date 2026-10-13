<?php
/**
 * 管理后台 - 手动数据同步
 * 
 * 手动触发从企业微信 API 同步数据到本地数据库。
 * 支持：通讯录、客户数据、标签
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '数据同步';
$error = ''; $success = '';
$syncResults = [];

// 处理同步
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $syncType = $_POST['sync_type'] ?? '';
    set_time_limit(300); // 5分钟超时

    try {
        switch ($syncType) {
            case 'contacts':
                ob_start();
                require __DIR__ . '/../../../sync/SyncContacts.php';
                $output = ob_get_clean();
                $success = "通讯录同步完成！详见下方输出";
                $syncResults = ['type' => '通讯录', 'output' => $output];
                break;

            case 'external':
                ob_start();
                require __DIR__ . '/../../../sync/SyncExternalContacts.php';
                $output = ob_get_clean();
                $success = "客户数据同步完成！详见下方输出";
                $syncResults = ['type' => '客户数据', 'output' => $output];
                break;

            case 'tags':
                ob_start();
                require __DIR__ . '/../../../sync/SyncTags.php';
                $output = ob_get_clean();
                $success = "标签同步完成！详见下方输出";
                $syncResults = ['type' => '标签', 'output' => $output];
                break;
        }
    } catch (\Throwable $e) {
        $error = "同步失败: " . $e->getMessage();
    }
}

// 获取同步状态统计
$stats = ['members' => 0, 'departments' => 0, 'external' => 0, 'tags' => 0, 'last_sync' => '-'];
try {
    $db = $wx->getDatabase();
    $stats['members'] = (int)($db->selectValue("SELECT COUNT(*) FROM wxwork_member WHERE is_deleted=0")??0);
    $stats['departments'] = (int)($db->selectValue("SELECT COUNT(*) FROM wxwork_department WHERE is_deleted=0")??0);
    $stats['external'] = (int)($db->selectValue("SELECT COUNT(*) FROM wxwork_external_contact WHERE is_deleted=0")??0);
    $stats['tags'] = (int)($db->selectValue("SELECT COUNT(*) FROM wxwork_tag WHERE is_deleted=0")??0);
    $last = $db->selectOne("SELECT MAX(updated_at) as t FROM wxwork_member");
    $stats['last_sync'] = $last['t'] ?? '-';
} catch (\Throwable $e) {}

require __DIR__ . '/../../layout/header.php';
?>

<style>
.sync-card { border: 2px solid #e5e5e5; border-radius: 6px; padding: 20px; text-align: center; transition: all 0.2s; cursor: pointer; }
.sync-card:hover { border-color: #2196f3; box-shadow: 0 2px 12px rgba(33,150,243,0.2); }
.sync-card .sync-icon { font-size: 36px; margin-bottom: 10px; }
.sync-output { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 12px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; }
.stat-mini { text-align: center; padding: 10px; }
.stat-mini .stat-num { font-size: 22px; font-weight: bold; }
</style>

<?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- 统计 -->
<div class="row" style="margin-bottom:15px">
    <div class="col-md-3 col-xs-6"><div class="stat-mini well well-sm"><div class="stat-num text-primary"><?= number_format($stats['members']) ?></div><small>已同步成员</small></div></div>
    <div class="col-md-3 col-xs-6"><div class="stat-mini well well-sm"><div class="stat-num text-success"><?= number_format($stats['departments']) ?></div><small>已同步部门</small></div></div>
    <div class="col-md-3 col-xs-6"><div class="stat-mini well well-sm"><div class="stat-num text-warning"><?= number_format($stats['external']) ?></div><small>已同步客户</small></div></div>
    <div class="col-md-3 col-xs-6"><div class="stat-mini well well-sm"><div class="stat-num text-purple"><?= number_format($stats['tags']) ?></div><small>已同步标签</small></div></div>
</div>
<p class="text-muted" style="font-size:12px">最近同步时间: <?= $stats['last_sync'] ?></p>

<!-- 同步触发 -->
<div class="row">
    <div class="col-md-4">
        <form method="post">
            <input type="hidden" name="sync_type" value="contacts">
            <div class="sync-card" onclick="this.closest('form').submit()">
                <div class="sync-icon">👥</div>
                <h4>同步通讯录</h4>
                <p class="text-muted">同步所有部门和成员信息</p>
                <span class="btn btn-primary btn-sm">开始同步</span>
            </div>
        </form>
    </div>
    <div class="col-md-4">
        <form method="post">
            <input type="hidden" name="sync_type" value="external">
            <div class="sync-card" onclick="this.closest('form').submit()">
                <div class="sync-icon">📇</div>
                <h4>同步客户数据</h4>
                <p class="text-muted">同步客户列表和跟进关系</p>
                <span class="btn btn-success btn-sm">开始同步</span>
            </div>
        </form>
    </div>
    <div class="col-md-4">
        <form method="post">
            <input type="hidden" name="sync_type" value="tags">
            <div class="sync-card" onclick="this.closest('form').submit()">
                <div class="sync-icon">🏷️</div>
                <h4>同步标签</h4>
                <p class="text-muted">同步企业标签及成员关联</p>
                <span class="btn btn-warning btn-sm">开始同步</span>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($syncResults)): ?>
<div style="margin-top:20px">
    <h5><i class="fa fa-terminal"></i> <?= $syncResults['type'] ?> — 同步输出</h5>
    <div class="sync-output"><?= htmlspecialchars($syncResults['output']) ?></div>
</div>
<?php endif; ?>

<div class="alert alert-info" style="margin-top:20px;font-size:13px">
    <i class="fa fa-clock-o"></i> 定时同步任务建议通过 crontab 自动运行（参考项目根目录的 <code>cron.example</code>）。<br>
    手动同步适用于：首次部署后初始化数据、定时任务异常后的手动补救。
</div>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

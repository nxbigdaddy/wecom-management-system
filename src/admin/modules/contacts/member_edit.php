<?php
/**
 * 管理后台 - 成员详情/编辑
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '成员详情';
$userid = $_GET['userid'] ?? $_GET['user_id'] ?? '';
$user = null;
$error = '';

if ($userid) {
    try {
        $result = $wx->get('/cgi-bin/user/get', ['userid' => $userid]);
        if (($result['errcode'] ?? -1) === 0) {
            $user = $result;
        } else {
            $error = $result['errmsg'] ?? '获取成员失败';
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

require __DIR__ . '/../../layout/header.php';
?>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($user): ?>
<div class="row">
    <div class="col-md-4">
        <div class="portlet light">
            <div class="portlet-body text-center">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?= htmlspecialchars($user['avatar']) ?>" class="img-circle" width="80">
                <?php else: ?>
                    <i class="fa fa-user-circle fa-5x text-muted"></i>
                <?php endif; ?>
                <h3><?= htmlspecialchars($user['name']) ?></h3>
                <p class="text-muted"><?= htmlspecialchars($user['position'] ?? '') ?></p>
                <span class="label label-<?= ($user['status'] ?? 1) === 1 ? 'success' : 'danger' ?>">
                    <?= [1=>'已激活',2=>'已禁用',4=>'未激活',5=>'已退出'][$user['status']??1] ?? '未知' ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-info-circle"></i> 基本信息</div></div>
            <div class="portlet-body">
                <table class="table table-striped">
                    <tr><td width="120"><strong>UserID</strong></td><td><code><?= htmlspecialchars($user['userid']) ?></code></td></tr>
                    <tr><td><strong>姓名</strong></td><td><?= htmlspecialchars($user['name']) ?></td></tr>
                    <tr><td><strong>别名</strong></td><td><?= htmlspecialchars($user['alias'] ?? '-') ?></td></tr>
                    <tr><td><strong>手机号</strong></td><td><?= htmlspecialchars($user['mobile'] ?? '-') ?></td></tr>
                    <tr><td><strong>邮箱</strong></td><td><?= htmlspecialchars($user['email'] ?? '-') ?></td></tr>
                    <tr><td><strong>性别</strong></td><td><?= ['1'=>'男','2'=>'女'][$user['gender']??''] ?? '-' ?></td></tr>
                    <tr><td><strong>座机</strong></td><td><?= htmlspecialchars($user['telephone'] ?? '-') ?></td></tr>
                    <tr><td><strong>部门</strong></td><td><?= json_encode($user['department'] ?? [], JSON_UNESCAPED_UNICODE) ?></td></tr>
                    <tr><td><strong>主部门</strong></td><td><?= (int)($user['main_department'] ?? 0) ?></td></tr>
                    <tr><td><strong>Open UserID</strong></td><td><code><?= htmlspecialchars($user['open_userid'] ?? '-') ?></code></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
<?php elseif (!$userid): ?>
    <div class="alert alert-info">请从成员列表点击查看详情</div>
<?php endif; ?>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

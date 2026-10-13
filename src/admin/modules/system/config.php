<?php
/**
 * 管理后台 - 系统设置
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn() || !Session::isSuperAdmin()) { header('Location: /login'); exit; }

$title = '系统设置';
$error = '';
$success = '';
$db = $wx->getDatabase();

// 处理保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $fields = ['corpid', 'corpsecret', 'token', 'encoding_aes_key', 'agent_id', 'callback_url', 'api_domain'];
        foreach ($fields as $f) {
            $value = $_POST[$f] ?? '';
            $exists = $db->selectValue('SELECT COUNT(*) FROM `wxwork_config` WHERE cfg_key = ?', [$f]);
            if ($exists) {
                $db->execute('UPDATE `wxwork_config` SET cfg_value = ? WHERE cfg_key = ?', [$value, $f]);
            } else {
                $db->insertRow('wxwork_config', ['cfg_key' => $f, 'cfg_value' => $value]);
            }
        }
        $success = '配置已保存！部分配置需重启 PHP 后生效。';
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

// 读取当前配置
$configs = [];
try {
    $rows = $db->select('SELECT cfg_key, cfg_value FROM `wxwork_config`');
    foreach ($rows as $row) {
        $configs[$row['cfg_key']] = $row['cfg_value'];
    }
} catch (\Throwable $e) {}

$cfg = function(string $key, string $default = '') use ($configs) {
    return htmlspecialchars($configs[$key] ?? $default);
};

// 系统状态
$tokenStatus = '未知';
try {
    $wx->getTokenManager()->getToken();
    $tokenStatus = '<span class="label label-success">正常</span>';
} catch (\Throwable $e) {
    $tokenStatus = '<span class="label label-danger">异常: '.htmlspecialchars($e->getMessage()).'</span>';
}

require __DIR__ . '/../../layout/header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-cog"></i> 企业微信配置</div></div>
            <div class="portlet-body">
                <form method="post" class="form-horizontal">
                    <div class="form-group">
                        <label class="col-md-3 control-label">企业ID (corpid)</label>
                        <div class="col-md-8"><input type="text" name="corpid" class="form-control" value="<?= $cfg('corpid') ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">应用Secret</label>
                        <div class="col-md-8"><input type="password" name="YOUR_PASSWORD" class="form-control" value="<?= $cfg('corpsecret') ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">Agent ID</label>
                        <div class="col-md-4"><input type="number" name="agent_id" class="form-control" value="<?= $cfg('agent_id') ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">回调 Token</label>
                        <div class="col-md-8"><input type="text" name="token" class="form-control" value="<?= $cfg('token') ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">Encoding AES Key</label>
                        <div class="col-md-8"><input type="text" name="encoding_aes_key" class="form-control" value="<?= $cfg('encoding_aes_key') ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">回调 URL</label>
                        <div class="col-md-8"><input type="text" name="callback_url" class="form-control" value="<?= $cfg('callback_url') ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">API 域名</label>
                        <div class="col-md-8"><input type="text" name="api_domain" class="form-control" value="<?= $cfg('api_domain', 'https://qyapi.weixin.qq.com') ?>"></div>
                    </div>
                    <div class="form-group">
                        <div class="col-md-offset-3 col-md-8">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> 保存配置</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-info-circle"></i> 系统信息</div></div>
            <div class="portlet-body">
                <table class="table table-striped">
                    <tr><td>PHP 版本</td><td><?= phpversion() ?></td></tr>
                    <tr><td>MySQL</td><td><span class="label label-success">连接正常</span></td></tr>
                    <tr><td>Redis</td><td><?= $wx->getRedis()->isAvailable() ? '<span class="label label-success">正常</span>' : '<span class="label label-warning">未连接</span>' ?></td></tr>
                    <tr><td>access_token</td><td><?= $tokenStatus ?></td></tr>
                    <tr><td>Session存储</td><td><?= $wx->getRedis()->isAvailable() ? 'Redis' : '文件' ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

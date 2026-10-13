<?php
/**
 * 系统安装/环境检查向导
 * 
 * 访问：http://your-domain.com/system/setup
 * 
 * 提供：
 *   1. 环境检查（PHP版本、扩展、目录权限）
 *   2. 数据库连接测试
 *   3. Redis 连接测试
 *   4. 数据库初始化（建表）
 *   5. 管理员账号创建
 */
use Wxwork\Core\Session;

// 安装向导不要求登录（首次使用时还没有管理员账号）
// 但如果已经安装过，需要登录才能访问
$isInstalled = false;
try {
    $db = $wx->getDatabase();
    $adminCount = (int)($db->selectValue('SELECT COUNT(*) FROM wxwork_admin_user WHERE is_enabled = 1') ?? 0);
    $isInstalled = $adminCount > 0;
} catch (\Throwable $e) {
    $isInstalled = false;
}

if ($isInstalled && !Session::isLoggedIn()) {
    header('Location: /login');
    exit;
}

$title = '系统安装向导';
$step = (int)($_GET['step'] ?? 1);
$results = [];
$error = '';
$success = '';

// ==================== Step 1: 环境检查 ====================
if ($step === 1 || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $requiredExtensions = ['curl', 'openssl', 'pdo', 'pdo_mysql', 'mbstring', 'xml', 'json'];
    $optionalExtensions = ['redis'];
    
    $checks = [];
    
    // PHP 版本
    $checks[] = [
        'name'   => 'PHP 版本 >= 8.0',
        'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'value'  => PHP_VERSION,
        'required' => true,
    ];
    
    // 必装扩展
    foreach ($requiredExtensions as $ext) {
        $loaded = extension_loaded($ext);
        $checks[] = [
            'name'     => "ext-{$ext}",
            'status'   => $loaded,
            'value'    => $loaded ? '已安装' : '未安装',
            'required' => true,
        ];
    }
    
    // 可选扩展
    foreach ($optionalExtensions as $ext) {
        $loaded = extension_loaded($ext);
        $checks[] = [
            'name'     => "ext-{$ext}（推荐）",
            'status'   => $loaded,
            'value'    => $loaded ? '已安装' : '未安装（不影响基本使用）',
            'required' => false,
        ];
    }
    
    // 目录权限
    $dirs = [
        __DIR__ . '/../../../../logs'   => '日志目录',
        __DIR__ . '/../../../../public' => 'Web 根目录',
    ];
    foreach ($dirs as $dir => $label) {
        $writable = is_writable($dir) || is_writable(dirname($dir));
        $checks[] = [
            'name'     => "目录可写: {$label}",
            'status'   => $writable,
            'value'    => $writable ? '可写' : '不可写（请执行 chmod -R 755）',
            'required' => true,
        ];
    }
    
    // 配置文件
    $localConfig = __DIR__ . '/../../../../config.local.php';
    $checks[] = [
        'name'     => 'config.local.php',
        'status'   => file_exists($localConfig),
        'value'    => file_exists($localConfig) ? '已创建' : '未创建（将使用 config.php 默认值）',
        'required' => false,
    ];
    
    // autoload
    $autoload = __DIR__ . '/../../../../vendor/autoload.php';
    $checks[] = [
        'name'     => '自动加载器',
        'status'   => file_exists($autoload),
        'value'    => file_exists($autoload) ? '已就绪' : '缺失（系统可能无法运行）',
        'required' => true,
    ];
    
    $allPassed = !in_array(false, array_map(fn($c) => $c['required'] ? $c['status'] : true, $checks));
    $results = $checks;
}

// ==================== Step 2: 数据库初始化 ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '2') {
    try {
        $db = $wx->getDatabase();
        
        // 测试连接
        $dbVersion = $db->selectValue('SELECT VERSION()');
        $success = "数据库连接成功！MySQL {$dbVersion}";
        
        // 检查是否已初始化
        $tableCount = (int)($db->selectValue(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = DATABASE() AND table_name LIKE 'wxwork_%'"
        ) ?? 0);
        
        if ($tableCount >= 15) {
            $success .= "（数据库已初始化，共 {$tableCount} 张表）";
        } else {
            // 尝试执行 schema.sql
            $schemaFile = __DIR__ . '/../../../../schema.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                // 分割 SQL（简单按分号分割）
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    fn($s) => !empty($s) && !str_starts_with($s, '--') && !str_starts_with($s, 'SELECT')
                );
                foreach ($statements as $stmt) {
                    try {
                        $db->execute($stmt);
                    } catch (\Throwable $e) {
                        // 忽略 DROP TABLE IF EXISTS 等可能失败但无害的语句
                        if (!str_contains($e->getMessage(), 'Base table') && 
                            !str_contains($e->getMessage(), 'already exists')) {
                            throw $e;
                        }
                    }
                }
                $success .= "；schema.sql 已执行，数据库表已创建";
            }
        }
    } catch (\Throwable $e) {
        $error = "数据库初始化失败: " . $e->getMessage();
    }
}

// ==================== Step 3: 创建管理员 ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === '3') {
    $username = trim($_POST['username'] ?? '');
    $password = YOUR_PASSWORD'password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '用户名和密码不能为空';
    } elseif ($password !== $password2) {
        $error = '两次输入的密码不一致';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少 6 位';
    } else {
        try {
            $db = $wx->getDatabase();
            
            // 检查是否已存在
            $exists = $db->selectValue('SELECT COUNT(*) FROM wxwork_admin_user WHERE username = ?', [$username]);
            if ($exists) {
                // 更新密码
                $db->execute(
                    'UPDATE wxwork_admin_user SET password = ?, updated_at = NOW() WHERE username = ?',
                    [password_hash($password, PASSWORD_BCRYPT), $username]
                );
                $success = "管理员 '{$username}' 密码已更新！";
            } else {
                $db->insertRow('wxwork_admin_user', [
                    'username'   => $username,
                    'password'   => password_hash($password, PASSWORD_BCRYPT),
                    'realname'   => $_POST['realname'] ?? '管理员',
                    'role'       => 'superadmin',
                    'is_enabled' => 1,
                ]);
                $success = "管理员 '{$username}' 创建成功！";
            }
            
            $success .= ' <a href="/login" class="btn btn-sm btn-primary">去登录</a>';
            
        } catch (\Throwable $e) {
            $error = "创建管理员失败: " . $e->getMessage();
        }
    }
}

require __DIR__ . '/../../layout/header.php';
?>

<style>
.setup-steps { display: flex; margin-bottom: 30px; }
.setup-step { flex: 1; text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin: 0 5px; position: relative; }
.setup-step.active { background: #2196f3; color: #fff; }
.setup-step.done { background: #4caf50; color: #fff; }
.setup-step .step-num { font-size: 24px; font-weight: bold; }
.setup-step .step-label { font-size: 12px; margin-top: 5px; }
.check-list { list-style: none; padding: 0; }
.check-list li { padding: 10px 15px; margin: 5px 0; background: #fafafa; border-left: 4px solid #ddd; border-radius: 4px; }
.check-list li.pass { border-left-color: #4caf50; background: #f1f8e9; }
.check-list li.fail { border-left-color: #f44336; background: #fce4ec; }
.check-list li .check-icon { margin-right: 10px; }
.check-list li .check-value { float: right; color: #999; font-size: 13px; }
</style>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>

<!-- 步骤指示器 -->
<div class="setup-steps">
    <div class="setup-step <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">
        <div class="step-num">1</div>
        <div class="step-label">环境检查</div>
    </div>
    <div class="setup-step <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">
        <div class="step-num">2</div>
        <div class="step-label">数据库初始化</div>
    </div>
    <div class="setup-step <?= $step >= 3 ? 'active' : '' ?>">
        <div class="step-num">3</div>
        <div class="step-label">创建管理员</div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 col-md-offset-2">

        <!-- Step 1: 环境检查 -->
        <?php if ($step === 1): ?>
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-check-circle"></i> 环境检查</div>
            </div>
            <div class="portlet-body">
                <ul class="check-list">
                    <?php foreach ($results as $check): ?>
                    <li class="<?= $check['status'] ? 'pass' : ($check['required'] ? 'fail' : 'pass') ?>">
                        <span class="check-icon">
                            <?= $check['status'] ? '<i class="fa fa-check-circle text-success"></i>' : 
                                ($check['required'] ? '<i class="fa fa-times-circle text-danger"></i>' : '<i class="fa fa-exclamation-triangle text-warning"></i>') ?>
                        </span>
                        <strong><?= htmlspecialchars($check['name']) ?></strong>
                        <span class="check-value"><?= htmlspecialchars($check['value']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if ($allPassed): ?>
                <div class="text-center" style="margin-top: 20px;">
                    <a href="?step=2" class="btn btn-lg btn-primary">
                        <i class="fa fa-arrow-right"></i> 下一步：初始化数据库
                    </a>
                </div>
                <?php else: ?>
                <div class="alert alert-warning" style="margin-top: 20px;">
                    <i class="fa fa-warning"></i> 请先解决以上红色标记的问题，然后刷新页面重试。
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Step 2: 数据库初始化 -->
        <?php if ($step === 2): ?>
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-database"></i> 数据库初始化</div>
            </div>
            <div class="portlet-body">
                <p>当前配置：</p>
                <table class="table table-bordered">
                    <tr><td width="120">数据库主机</td><td><?= htmlspecialchars($wx->getConfig()->get('db_host', '127.0.0.1')) ?></td></tr>
                    <tr><td>数据库端口</td><td><?= (int)$wx->getConfig()->get('db_port', 3306) ?></td></tr>
                    <tr><td>数据库名</td><td><?= htmlspecialchars($wx->getConfig()->get('db_name', 'wxwork_manager')) ?></td></tr>
                    <tr><td>表前缀</td><td><?= htmlspecialchars($wx->getConfig()->get('db_prefix', 'wxwork_')) ?></td></tr>
                </table>

                <form method="post">
                    <input type="hidden" name="step" value="2">
                    <button type="submit" class="btn btn-lg btn-primary">
                        <i class="fa fa-magic"></i> 初始化/检查数据库
                    </button>
                    <a href="?step=1" class="btn btn-lg btn-default">上一步</a>
                </form>
                
                <?php if ($success): ?>
                <div style="margin-top: 20px; text-align: center;">
                    <a href="?step=3" class="btn btn-lg btn-success">
                        <i class="fa fa-arrow-right"></i> 下一步：创建管理员
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Step 3: 创建管理员 -->
        <?php if ($step === 3): ?>
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-user-plus"></i> 创建/重置管理员账号</div>
            </div>
            <div class="portlet-body">
                <?php if (!$success): ?>
                <form method="post" class="form-horizontal">
                    <input type="hidden" name="step" value="3">
                    
                    <div class="form-group">
                        <label class="col-md-3 control-label">用户名 <span class="text-danger">*</span></label>
                        <div class="col-md-8">
                            <input type="text" name="username" class="form-control input-lg" 
                                   value="admin" placeholder="管理员登录用户名" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-3 control-label">姓名</label>
                        <div class="col-md-8">
                            <input type="text" name="realname" class="form-control" 
                                   value="超级管理员" placeholder="真实姓名">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-3 control-label">密码 <span class="text-danger">*</span></label>
                        <div class="col-md-8">
                            <input type="password" name="YOUR_PASSWORD" class="form-control input-lg" 
                                   placeholder="至少 6 位" required minlength="6">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-3 control-label">确认密码 <span class="text-danger">*</span></label>
                        <div class="col-md-8">
                            <input type="password" name="YOUR_PASSWORD" class="form-control input-lg" 
                                   placeholder="再次输入密码" required minlength="6">
                        </div>
                    </div>
                    
                    <hr>
                    <div class="form-group">
                        <div class="col-md-offset-3 col-md-8">
                            <button type="submit" class="btn btn-lg btn-primary">
                                <i class="fa fa-user-plus"></i> 创建管理员
                            </button>
                            <a href="?step=2" class="btn btn-lg btn-default">上一步</a>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="text-center" style="padding: 40px;">
                    <i class="fa fa-check-circle fa-5x text-success"></i>
                    <h3>安装完成！</h3>
                    <p style="margin-top: 20px;"><?= $success ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

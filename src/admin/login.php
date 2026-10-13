<?php
/**
 * 管理后台 - 登录页面（精确匹配 ui_demo login.html + login-theme.css）
 */
use Wxwork\Core\Session;

if (Session::isLoggedIn()) { header('Location: /'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF 检查
    $csrf = new \Wxwork\Core\Session();
    if (!\Wxwork\Core\Session::validateCsrf($_POST['_csrf_token'] ?? '')) {
        $error = '安全验证失败，请刷新页面重试';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = YOUR_PASSWORD'password'] ?? '';
        if (empty($username) || empty($password)) {
            $error = '请输入用户名和密码';
        } else {
            $db = $GLOBALS['wx']->getDatabase();
            $user = $db->selectOne('SELECT * FROM `wxwork_admin_user` WHERE username = ? AND is_enabled = 1', [$username]);
            if ($user && password_verify($password, $user['password'])) {
                Session::login($user['id'], $user);
                header('Location: /'); exit;
            } else { $error = '用户名或密码错误'; }
        }
    }
}
$csrfToken = \Wxwork\Core\Session::csrfToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8" />
<title>企业微信管理系统 v1.0</title>
<meta name="renderer" content="webkit">
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta http-equiv="Cache-Control" content="no-siteapp" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<meta content="" name="description" />
<meta content="" name="author" />
<link rel="shortcut icon" href="/assets/favicon.ico" />
<link href="/assets/themes/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" />
<link href="/assets/themes/fonts/font-awesome/css/font-awesome.min.css" rel="stylesheet" />
<link href="/assets/themes/css/login-theme.css" rel="stylesheet" />
</head>
<body>

<div class="loginWrap">
    <div class="bd">
        <div class="ad-left">
            <div class="brand">
                <h2>企业微信管理</h2>
                <p>高效、安全、智能的企业微信管理后台<br>让企业运营更轻松</p>
            </div>
            <div class="features">
                <div class="feature-item">
                    <i class="fa fa-shield"></i>
                    <span>安全可靠</span>
                </div>
                <div class="feature-item">
                    <i class="fa fa-bolt"></i>
                    <span>高效便捷</span>
                </div>
                <div class="feature-item">
                    <i class="fa fa-cogs"></i>
                    <span>智能管理</span>
                </div>
            </div>
        </div>
        <div class="cn-right">
            <div class="logo">
                <h1>欢迎登录</h1>
                <p>请输入您的账号信息以继续</p>
            </div>
            <div class="login">
                <form class="login-form" name="form_login" method="post" autocomplete="off">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="form-group form-group-name">
                        <label>用户名</label>
                        <input class="form-control" type="text" autocomplete="off" placeholder="请输入用户名" name="username" />
                    </div>
                    <div class="form-group form-group-password">
                        <label>密码</label>
                        <input class="form-control" type="password" id="YOUR_PASSWORD" autocomplete="off" placeholder="请输入密码" name="password" />
                    </div>
                    <?php if ($error): ?>
                    <div style="color:#e74c3c;font-size:13px;margin-bottom:16px;text-align:center"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <div class="form-actions">
                        <button type="submit" class="btn green uppercase">登 录</button>
                    </div>
                </form>
                <div class="login-footer">
                    <span>忘记密码？<a href="#">联系管理员</a></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/assets/themes/plugins/jquery.min.js"></script>
</body>
</html>

<?php
/**
 * 管理后台 - 退出登录
 */

use Wxwork\Core\Session;

Session::logout();
header('Location: /login');
exit;

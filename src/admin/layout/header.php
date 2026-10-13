<?php
/**
 * 管理后台 - 全局布局头部（精确匹配 ui_demo 风格）
 */
if (!isset($title)) $title = '仪表盘';
require_once __DIR__ . '/../helpers.php';
$currentUser = \Wxwork\Core\Session::getUser();
$csrfToken = \Wxwork\Core\Session::csrfToken();
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';

function is_nav_active(string $path): string {
    $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
    return str_starts_with($currentUri, $path) ? 'open active' : '';
}
function is_sub_active(string $path): string {
    $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
    return str_contains($currentUri, $path) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8" />
<title><?= htmlspecialchars($title) ?> - 企业微信管理系统</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<link rel="shortcut icon" href="/assets/favicon.ico" />
<link href="/assets/themes/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet" />
<link href="/assets/themes/fonts/font-awesome/css/font-awesome.min.css" rel="stylesheet" />
<link href="/assets/themes/plugins/uniform/css/uniform.default.css" rel="stylesheet" />
<link href="/assets/themes/css/theme.css" rel="stylesheet" />
<link href="/assets/css/custom.css" rel="stylesheet" />
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
</head>
<body>

<!-- 顶部导航 -->
<div class="page-header">
    <div class="page-header-inner clearfix">
        <div class="page-logo">
            <a href="/"><i class="fa fa-commenting" style="margin-right:8px;"></i>企业微信管理</a>
            <span class="menu-toggler sidebar-toggler"></span>
        </div>
        <a href="javascript:;" class="menu-toggler responsive-toggler" data-toggle="collapse" data-target=".navbar-collapse"></a>
        <ul class="navbar-nav pull-right">
            <li class="tooltips" data-placement="bottom" data-original-title="回调日志">
                <a href="/callback/log"><i class="fa fa-bell-o"></i></a>
            </li>
            <li class="separator"></li>
            <li class="dropdown dropdown-hover">
                <a href="javascript:;" class="dropdown-toggle" data-toggle="dropdown" data-hover="dropdown" data-close-others="true">
                    <span class="username"><?= htmlspecialchars($currentUser['realname'] ?? $currentUser['username'] ?? '管理员') ?></span>
                    <i class="fa fa-user-circle" style="font-size:22px;margin-left:8px;color:#fff;"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-default">
                    <li><a href="/system/config"><i class="fa fa-cogs"></i> 系统设置</a></li>
                    <li><a href="/logout"><i class="fa fa-key"></i> 退出登录</a></li>
                </ul>
            </li>
        </ul>
    </div>
</div>

<div class="clearfix"></div>
<div class="page-container clearfix">
    <div class="page-content-wrapper">

        <!-- 左侧菜单 -->
        <div class="page-sidebar-wrapper">
            <div class="page-sidebar navbar-collapse collapse">
                <ul class="page-sidebar-menu" data-slide-speed="200">

                    <li class="heading"><i class="fa fa-navicon"></i> 概览</li>
                    <li class="nav-item <?= $currentUri === '/' ? 'active' : '' ?>">
                        <a href="/"><i class="fa fa-dashboard"></i> <span class="title">数据统计面板</span></a>
                    </li>

                    <li class="heading"><i class="fa fa-navicon"></i> 通讯录</li>
                    <li class="nav-item <?= is_nav_active('/contacts') ?>">
                        <a href="javascript:;" class="nav-toggle"><i class="fa fa-users"></i> <span class="title">通讯录管理</span><span class="arrow <?= str_starts_with($currentUri, '/contacts') ? 'open' : '' ?>"></span></a>
                        <ul class="sub-menu" <?= str_starts_with($currentUri, '/contacts') ? 'style="display:block"' : '' ?>>
                            <li class="nav-item <?= is_sub_active('/contacts/departments') ?>"><a href="/contacts/departments"><i class="fa fa-caret-right"></i> <span class="title">部门管理</span></a></li>
                            <li class="nav-item <?= is_sub_active('/contacts/members') ?>"><a href="/contacts/members"><i class="fa fa-caret-right"></i> <span class="title">成员管理</span></a></li>
                            <li class="nav-item <?= is_sub_active('/contacts/tags') ?>"><a href="/contacts/tags"><i class="fa fa-caret-right"></i> <span class="title">标签管理</span></a></li>
                        </ul>
                    </li>

                    <li class="heading"><i class="fa fa-navicon"></i> 消息</li>
                    <li class="nav-item <?= is_nav_active('/messages') ?>">
                        <a href="javascript:;" class="nav-toggle"><i class="fa fa-envelope"></i> <span class="title">消息推送</span><span class="arrow <?= str_starts_with($currentUri, '/messages') ? 'open' : '' ?>"></span></a>
                        <ul class="sub-menu" <?= str_starts_with($currentUri, '/messages') ? 'style="display:block"' : '' ?>>
                            <li class="nav-item <?= is_sub_active('/messages/send') ?>"><a href="/messages/send"><i class="fa fa-caret-right"></i> <span class="title">发送消息</span></a></li>
                            <li class="nav-item <?= is_sub_active('/messages/log') ?>"><a href="/messages/log"><i class="fa fa-caret-right"></i> <span class="title">发送记录</span></a></li>
                        </ul>
                    </li>

                    <li class="heading"><i class="fa fa-navicon"></i> 客户</li>
                    <li class="nav-item <?= is_nav_active('/external') ?>">
                        <a href="javascript:;" class="nav-toggle"><i class="fa fa-address-book"></i> <span class="title">客户联系</span><span class="arrow <?= str_starts_with($currentUri, '/external') ? 'open' : '' ?>"></span></a>
                        <ul class="sub-menu" <?= str_starts_with($currentUri, '/external') ? 'style="display:block"' : '' ?>>
                            <li class="nav-item <?= is_sub_active('/external/contacts') ?>"><a href="/external/contacts"><i class="fa fa-caret-right"></i> <span class="title">客户列表</span></a></li>
                            <li class="nav-item <?= is_sub_active('/external/groups') ?>"><a href="/external/groups"><i class="fa fa-caret-right"></i> <span class="title">客户群</span></a></li>
                            <li class="nav-item <?= is_sub_active('/external/transfer') ?>"><a href="/external/transfer"><i class="fa fa-caret-right"></i> <span class="title">离职继承</span></a></li>
                        </ul>
                    </li>

                    <li class="heading"><i class="fa fa-navicon"></i> 系统管理</li>
                    <li class="nav-item <?= is_nav_active('/workflow') ?>">
                        <a href="/workflow"><i class="fa fa-cogs"></i> <span class="title">智能工作流</span></a>
                    </li>
                    <li class="nav-item <?= is_nav_active('/approval') ?>">
                        <a href="/approval/list"><i class="fa fa-check-square-o"></i> <span class="title">审批管理</span></a>
                    </li>
                    <li class="nav-item <?= is_nav_active('/checkin') ?>">
                        <a href="/checkin"><i class="fa fa-calendar-check-o"></i> <span class="title">打卡管理</span></a>
                    </li>
                    <li class="nav-item <?= is_nav_active('/meeting') ?>">
                        <a href="/meeting"><i class="fa fa-video-camera"></i> <span class="title">会议管理</span></a>
                    </li>
                    <li class="nav-item <?= is_nav_active('/smartsheet') ?>">
                        <a href="/smartsheet"><i class="fa fa-table"></i> <span class="title">智能表格</span></a>
                    </li>
                    <li class="nav-item <?= is_nav_active('/calendar') ?>">
                        <a href="/calendar"><i class="fa fa-calendar"></i> <span class="title">日程管理</span></a>
                    </li>
                    <li class="nav-item <?= is_nav_active('/wedrive') ?>">
                        <a href="/wedrive"><i class="fa fa-hdd-o"></i> <span class="title">微盘管理</span></a>
                    </li>
                    <li class="nav-item <?= is_nav_active('/wedoc') ?>">
                        <a href="/wedoc"><i class="fa fa-file-text-o"></i> <span class="title">文档管理</span></a>
                    </li>
                    <li class="nav-item <?= is_nav_active('/media') ?>">
                        <a href="/media"><i class="fa fa-folder-open"></i> <span class="title">素材管理</span></a>
                    </li>
                    <li class="nav-item">
                        <a href="javascript:;" class="nav-toggle"><i class="fa fa-cogs"></i><span class="title">系统功能</span><span class="arrow <?= str_starts_with($currentUri, '/system') || str_starts_with($currentUri, '/callback') ? 'open' : '' ?>"></span></a>
                        <ul class="sub-menu" <?= str_starts_with($currentUri, '/system') || str_starts_with($currentUri, '/callback') ? 'style="display:block"' : '' ?>>
                            <li class="nav-item <?= is_sub_active('/system/config') ?>"><a href="/system/config"><i class="fa fa-sun-o"></i> <span class="title">基本设置</span></a></li>
                            <li class="nav-item <?= is_sub_active('/system/users') ?>"><a href="/system/users"><i class="fa fa-users-cog"></i> <span class="title">管理员</span></a></li>
                            <li class="nav-item <?= is_sub_active('/system/setup') ?>"><a href="/system/setup"><i class="fa fa-wrench"></i> <span class="title">安装向导</span></a></li>
                            <li class="nav-item <?= is_sub_active('/callback/log') ?>"><a href="/callback/log"><i class="fa fa-bolt"></i> <span class="title">回调日志</span></a></li>
                            <li class="nav-item <?= is_sub_active('/system/sync') ?>"><a href="/system/sync"><i class="fa fa-refresh"></i> <span class="title">数据同步</span></a></li>
                            <li class="nav-item <?= is_sub_active('/system/ai_keys') ?>"><a href="/system/ai_keys"><i class="fa fa-key"></i> <span class="title">AI Keys</span></a></li>
                            <li class="nav-item <?= is_sub_active('/system/api_log') ?>"><a href="/system/api_log"><i class="fa fa-cloud"></i> <span class="title">API日志</span></a></li>
                        </ul>
                    </li>

                    <li class="heading"><i class="fa fa-navicon"></i> 个人管理</li>
                    <li class="nav-item"><a href="/logout"><i class="fa fa-sign-out"></i> <span class="title">退出系统</span></a></li>
                </ul>
            </div>
        </div>

        <!-- 主内容区域 -->
        <div class="page-content">
            <ul class="page-breadcrumb breadcrumb">
                <li><i class="fa fa-home"></i><a href="/">首页</a></li>
                <li><i class="fa fa-angle-right"></i><span class="active"><?= htmlspecialchars($title) ?></span></li>
            </ul>

            <?php
            // Flash messages（支持跨页面重定向后的提示）
            $flashTypes = ['flash_success' => ['alert-success', 'fa-check-circle'],
                           'flash_error'   => ['alert-danger', 'fa-exclamation-circle'],
                           'flash_warning' => ['alert-warning', 'fa-warning'],
                           'flash_info'    => ['alert-info', 'fa-info-circle']];
            foreach ($flashTypes as $key => [$cls, $icon]) {
                if (!empty($_SESSION[$key])) {
                    echo '<div class="alert ' . $cls . ' alert-dismissible" style="margin-bottom:10px">';
                    echo '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                    echo '<i class="fa ' . $icon . '"></i> ' . htmlspecialchars($_SESSION[$key]);
                    echo '</div>';
                    unset($_SESSION[$key]);
                }
            }
            ?>

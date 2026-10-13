<?php
/**
 * 管理后台入口
 * 
 * Nginx 配置：
 *   location / {
 *       try_files $uri $uri/ /index.php?$query_string;
 *   }
 * 
 * 所有 /admin/xxx 请求都会被路由到此文件处理。
 */

// ===================== 错误报告 =====================
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// ===================== 自动加载 =====================
require __DIR__ . '/../vendor/autoload.php';

use Wxwork\Wxwork;
use Wxwork\Core\Router;
use Wxwork\Core\Session;
use Wxwork\Core\Logger;

// ===================== 初始化 =====================
try {
    // 创建主实例（自动加载 config.php）
    $wx = Wxwork::create();

    $config = $wx->getConfig();
    $redis = $wx->getRedis();
    $db = $wx->getDatabase();
    $logger = $wx->getLogger();

    // 会话初始化
    Session::init($config, $redis);

} catch (\Throwable $e) {
    // 初始化失败降级：最起码显示错误页
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>系统错误</title></head>';
    echo '<body style="font-family:sans-serif;padding:40px;">';
    echo '<h1>系统初始化失败</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</body></html>';
    exit;
}

// ===================== 路由配置 =====================
$router = new Router();

// ===== 登录 =====
$router->get('/login', function() {
    require __DIR__ . '/../src/admin/login.php';
});
$router->post('/login', function() {
    require __DIR__ . '/../src/admin/login.php';
});
$router->get('/logout', function() {
    require __DIR__ . '/../src/admin/logout.php';
});

// ===== 仪表盘 =====
$router->get('/', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/dashboard/index.php';
});

// ===== 通讯录 - 部门 =====
$router->get('/contacts/departments', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/contacts/departments.php';
});
$router->get('/contacts/departments/create', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/contacts/department_edit.php';
});
$router->post('/contacts/departments/create', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/contacts/department_edit.php';
});
$router->get('/contacts/departments/{id}/edit', function($params) use ($wx) {
    require __DIR__ . '/../src/admin/modules/contacts/department_edit.php';
});
$router->post('/contacts/departments/{id}/edit', function($params) use ($wx) {
    require __DIR__ . '/../src/admin/modules/contacts/department_edit.php';
});
$router->post('/contacts/departments/{id}/delete', function($params) use ($wx) {
    require __DIR__ . '/../src/admin/modules/contacts/department_edit.php';
});

// ===== 通讯录 - 成员 =====
$router->get('/contacts/members', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/contacts/members.php';
});
$router->get('/contacts/members/create', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/contacts/member_edit.php';
});
$router->post('/contacts/members/create', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/contacts/member_edit.php';
});
$router->get('/contacts/members/{userid}', function($params) use ($wx) {
    require __DIR__ . '/../src/admin/modules/contacts/member_edit.php';
});
$router->post('/contacts/members/{userid}/edit', function($params) use ($wx) {
    require __DIR__ . '/../src/admin/modules/contacts/member_edit.php';
});

// ===== 通讯录 - 标签 =====
$router->get('/contacts/tags', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/contacts/tags.php';
});

// ===== 消息推送 =====
$router->get('/messages/send', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/messages/send.php';
});
$router->post('/messages/send', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/messages/send.php';
});
$router->get('/messages/log', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/messages/log.php';
});

// ===== 客户联系 =====
$router->get('/external/contacts', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/external/contacts.php';
});
$router->get('/external/contacts/detail', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/external/contact_detail.php';
});
$router->get('/external/groups', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/external/groups.php';
});
$router->get('/external/transfer', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/external/transfer.php';
});
$router->post('/external/transfer', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/external/transfer.php';
});

// ===== 审批 =====
$router->get('/approval/list', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/approval/list.php';
});
$router->post('/approval/list', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/approval/list.php';
});

// ===== 打卡 =====
$router->get('/checkin', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/checkin/index.php';
});
$router->post('/checkin', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/checkin/index.php';
});

// ===== 会议 =====
$router->get('/meeting', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/meeting/index.php';
});
$router->post('/meeting', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/meeting/index.php';
});

// ===== 智能表格 =====
$router->get('/smartsheet', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/smartsheet/index.php';
});
$router->post('/smartsheet', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/smartsheet/index.php';
});

// ===== 日程 =====
$router->get('/calendar', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/calendar/index.php';
});
$router->post('/calendar', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/calendar/index.php';
});

// ===== 微盘 =====
$router->get('/wedrive', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/wedrive/index.php';
});
$router->post('/wedrive', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/wedrive/index.php';
});

// ===== 文档 =====
$router->get('/wedoc', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/wedoc/index.php';
});
$router->post('/wedoc', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/wedoc/index.php';
});

// ===== 素材 =====
$router->get('/media', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/media/list.php';
});
$router->get('/media/download', function() use ($wx) {
    $id = (int)($_GET['id'] ?? 0);
    try {
        $db = $wx->getDatabase();
        $material = $db->selectOne('SELECT * FROM wxwork_media WHERE id = ?', [$id]);
        if ($material && $material['local_path'] && file_exists($material['local_path'])) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($material['filename'] ?? $material['media_id']) . '"');
            readfile($material['local_path']);
            exit;
        } else {
            echo '<div class="alert alert-danger">文件不存在</div>';
        }
    } catch (\Throwable $e) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
    }
});
$router->get('/media/delete', function() use ($wx) {
    $id = (int)($_GET['id'] ?? 0);
    try {
        $db = $wx->getDatabase();
        $db->execute('DELETE FROM wxwork_media WHERE id = ?', [$id]);
        header('Location: /media?success=deleted');
        exit;
    } catch (\Throwable $e) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage()) . '</div>';
    }
});

// ===== 回调日志 =====
$router->get('/callback/log', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/callback/log.php';
});
$router->get('/callback/log/detail', function() use ($wx) {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    try {
        $db = $wx->getDatabase();
        $log = $db->selectOne('SELECT * FROM wxwork_callback_log WHERE id = ?', [$id]);
        echo json_encode($log ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } catch (\Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
});

// ===== 系统设置 =====
$router->get('/system/config', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/system/config.php';
});
$router->post('/system/config', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/system/config.php';
});

// ===== 系统安装向导 =====
$router->get('/system/setup', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/system/setup.php';
});
$router->post('/system/setup', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/system/setup.php';
});

// ===== 管理员管理 =====
$router->get('/system/users', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/system/users.php';
});
$router->post('/system/users', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/system/users.php';
});

// ===== API 请求日志 =====
$router->get('/system/api_log', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/system/api_log.php';
});

// ===== 数据同步 =====
$router->get('/system/sync', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/system/sync.php';
});
$router->post('/system/sync', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/system/sync.php';
});

// ===== AI API Key 管理 =====
$router->get('/system/ai_keys', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/system/ai_keys.php';
});
$router->post('/system/ai_keys', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/system/ai_keys.php';
});

// ===== 智能工作流 =====
$router->get('/workflow', function() use ($wx) {
    require __DIR__ . '/../src/admin/modules/workflow/index.php';
});

// ===== 404 =====
$router->setNotFound(function() {
    http_response_code(404);
    $title = '404 - 页面不存在';
    require __DIR__ . '/../src/admin/layout/header.php';
    echo '<div style="text-align:center;padding:60px 20px;">';
    echo '<h1 style="font-size:72px;color:#ddd;">404</h1>';
    echo '<p>页面不存在</p>';
    echo '<a href="/">返回首页</a>';
    echo '</div>';
    require __DIR__ . '/../src/admin/layout/footer.php';
});

// ===================== 分发请求 =====================
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// 去除查询字符串
$uri = parse_url($uri, PHP_URL_PATH);

// Nginx index 指令会导致 /index.php 被当作 URI
// 需要将其转换为 /
if ($uri === '/index.php' || $uri === '/index.php/') {
    $uri = '/';
}

$router->dispatch($method, $uri);

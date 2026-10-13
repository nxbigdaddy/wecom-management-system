<?php
/**
 * Session 调试页面
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Wxwork\Core\Session;
use Wxwork\Core\Config;
use Wxwork\Core\RedisClient;

header('Content-Type: text/html; charset=utf-8');

// 加载配置
$configData = require __DIR__ . '/../config.php';
$config = new Config($configData);

// 初始化 Redis
$redisConfig = [
    'host' => $config->get('redis_host', '127.0.0.1'),
    'port' => $config->get('redis_port', 6379),
    'auth' => $config->get('redis_password', ''),
    'prefix' => $config->get('redis_prefix', 'wxwork:'),
    'db' => $config->get('redis_db', 0),
];
$redis = new RedisClient($redisConfig);

echo "<h2>Session 调试</h2>";

echo "<h3>0. 环境检查</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Redis Extension: " . (extension_loaded('redis') ? 'YES' : 'NO') . "<br>";
echo "Redis Connected: " . ($redis->isConnected() ? 'YES' : 'NO') . "<br>";

echo "<h3>1. Session 初始化</h3>";
Session::init($config, $redis);
echo "Session ID: " . session_id() . "<br>";
echo "Session name: " . session_name() . "<br>";
echo "Session status: " . session_status() . "<br>";
echo "Session save path: " . session_save_path() . "<br>";

echo "<h3>2. 登录检查</h3>";
echo "Is logged in: " . (Session::isLoggedIn() ? 'YES' : 'NO') . "<br>";
$user = Session::getUser();
echo "User: " . ($user ? json_encode($user) : 'null') . "<br>";

echo "<h3>3. 设置测试数据</h3>";
$_SESSION['test_key'] = 'test_value_' . time();
echo "Set test_key: " . $_SESSION['test_key'] . "<br>";

echo "<h3>4. 所有 Session 数据</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

echo "<h3>5. Redis 中的 Session 数据</h3>";
$redisKey = 'wxwork:session:' . session_id();
echo "Redis Key: $redisKey<br>";
$redisData = $redis->get($redisKey);
echo "Redis Data: " . ($redisData ? htmlspecialchars($redisData) : '<span style="color:red;">EMPTY!</span>') . "<br>";

echo "<h3>6. 操作</h3>";
echo "<a href='?login=1'>执行登录</a> | ";
echo "<a href='?logout=1'>退出登录</a> | ";
echo "<a href='?'>刷新页面</a>";

if (isset($_GET['login'])) {
    Session::login([
        'id' => 1,
        'username' => 'admin',
        'role' => 'superadmin'
    ]);
    echo "<p style='color:green;'>登录成功！<a href='?'>刷新查看</a></p>";
}

if (isset($_GET['logout'])) {
    Session::logout();
    echo "<p style='color:orange;'>已退出登录！<a href='?'>刷新查看</a></p>";
}

echo "<h3>7. Cookie 信息</h3>";
echo "Cookie sent: " . ($_COOKIE[session_name()] ?? 'none') . "<br>";

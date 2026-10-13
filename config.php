<?php
/**
 * 企业微信管理系统 - 主配置文件
 * 
 * 配置优先级：本文件默认值 → config.local.php 覆盖 → 数据库 wxwork_config 表
 * 
 * 使用方式：
 *   复制此文件为 config.local.php 并修改其中的值
 *   config.local.php 会被 .gitignore 忽略
 */

$config = [
    // ===================== 企业微信核心配置 =====================
    'corpid'              => 'ww1234567890abcdef',   // 企业ID（在企业管理后台 → 我的企业 → 企业信息）
    'corpsecret'          => '',                       // 应用Secret（应用管理 → 自建应用 → 查看Secret）
    'agent_id'            => 1000002,                  // 应用AgentId
    'token'               => '',                       // 回调Token（10位以内随机字符串，用于签名验证）
    'encoding_aes_key'    => '',                       // 回调加密Key（43位 Base64 编码的 AES Key）
    'api_domain'          => 'https://qyapi.weixin.qq.com', // API 域名

    // ===================== Token 管理 =====================
    'token_ahead_refresh' => 300,    // Token 提前刷新秒数（过期前 N 秒自动刷新）
    'token_lock_timeout'  => 10,     // Redis 刷新锁超时秒数

    // ===================== HTTP 请求 =====================
    'http_timeout'        => 10,     // HTTP 请求超时秒数
    'http_retry'          => 1,      // 失败重试次数
    'http_user_agent'     => 'WxworkManager/1.0',

    // ===================== 数据库 =====================
    'db_host'             => '127.0.0.1',
    'db_port'             => 3306,
    'db_name'             => 'wxwork_manager',
    'db_user'             => 'root',
    'db_password'         => '',
    'db_charset'          => 'utf8mb4',
    'db_prefix'           => 'wxwork_',  // 表前缀

    // ===================== Redis =====================
    'redis_host'          => '127.0.0.1',
    'redis_port'          => 6379,
    'redis_password'      => '',
    'redis_db'            => 0,
    'redis_prefix'        => 'wxwork:',  // Key 前缀

    // ===================== 日志 =====================
    'log_dir'             => __DIR__ . '/logs',
    'log_level'           => 'info',     // debug / info / warn / error
    'log_max_days'        => 30,         // 日志保留天数

    // ===================== 会话 =====================
    'session_lifetime'    => 7200,       // 后台登录有效期秒数（默认2小时）
    'session_name'        => 'WXWORK_SESS',

    // ===================== 管理后台 =====================
    'admin_title'         => '企业微信管理系统',
    'admin_copyright'     => '© 2025 WxWork Manager',
    'admin_page_size'     => 20,         // 默认每页条数

    // ===================== 安全 =====================
    'login_max_attempts'  => 5,          // 登录最大错误次数
    'login_lock_minutes'  => 15,         // 锁定分钟数

    // ===================== 加密盐（用于内部加密，请修改） =====================
    'app_secret_key'      => 'change_this_to_random_string_32_chars_min',
];

// 尝试加载本地覆盖配置
$localConfigFile = __DIR__ . '/config.local.php';
if (file_exists($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

return $config;

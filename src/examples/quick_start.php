<?php
/**
 * 使用示例：快速开始
 * 
 * 在命令行运行：
 *   php src/examples/quick_start.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use Wxwork\Wxwork;

echo "╔══════════════════════════════════════════╗\n";
echo "║   企业微信管理系统 - 快速开始示例         ║\n";
echo "╚══════════════════════════════════════════╝\n\n";

// ============ 1. 初始化 ============
echo "📦 1. 初始化系统...\n";

try {
    $wx = Wxwork::create();
    echo "   ✅ 系统初始化成功\n\n";
} catch (\Throwable $e) {
    echo "   ❌ 初始化失败: " . $e->getMessage() . "\n";
    echo "   请检查 config.php 配置是否正确（特别是数据库连接）\n";
    exit(1);
}

// ============ 2. 配置检查 ============
echo "📋 2. 配置检查...\n";
$config = $wx->getConfig();
echo "   企业ID: " . ($config->getCorpId() ?: '❌ 未配置') . "\n";
echo "   应用Secret: " . ($config->getCorpSecret() ? '✅ 已配置' : '❌ 未配置') . "\n";
echo "   AgentId: " . ($config->getAgentId() ?: '❌ 未配置') . "\n";
echo "   回调Token: " . ($config->getToken() ? '✅ 已配置' : '⚠️ 未配置（不影响API调用）') . "\n";
echo "   EncodingAESKey: " . ($config->getEncodingAesKey() ? '✅ 已配置' : '⚠️ 未配置（不影响API调用）') . "\n\n";

// ============ 3. 系统状态检查 ============
echo "🔧 3. 系统组件状态...\n";
echo "   MySQL: " . (isset($wx) ? '✅ 连接正常' : '❌ 连接失败') . "\n";
echo "   Redis: " . ($wx->getRedis()->isAvailable() ? '✅ 连接正常' : '⚠️ 未连接（将使用MySQL兜底）') . "\n";
echo "   Logger: ✅ 就绪（日志目录: " . $config->getLogDir() . "）\n\n";

// ============ 4. 测试 API 连接（需配置 corpid 和 corpsecret） ============
echo "🌐 4. 测试企业微信 API 连接...\n";

if ($config->getCorpId() && $config->getCorpSecret()) {
    try {
        // 尝试获取 access_token
        $token = YOUR_SECRET)->getToken();
        echo "   ✅ access_token 获取成功！\n";
        echo "   前16位: " . substr($token, 0, 16) . "...\n\n";

        // 尝试获取部门列表
        echo "📇 5. 获取部门列表...\n";
        try {
            $depts = $wx->get('/cgi-bin/department/list');
            if (($depts['errcode'] ?? -1) === 0) {
                $deptList = $depts['department'] ?? [];
                echo "   ✅ 获取到 " . count($deptList) . " 个部门\n";
                foreach (array_slice($deptList, 0, 5) as $dept) {
                    echo "      - [{$dept['id']}] {$dept['name']}\n";
                }
                if (count($deptList) > 5) {
                    echo "      ... 还有 " . (count($deptList) - 5) . " 个部门\n";
                }
            } else {
                echo "   ⚠️ 获取部门列表失败: " . ($depts['errmsg'] ?? '未知错误') . "\n";
            }
        } catch (\Throwable $e) {
            echo "   ❌ 获取部门列表异常: " . $e->getMessage() . "\n";
        }

    } catch (\Throwable $e) {
        echo "   ❌ access_token 获取失败: " . $e->getMessage() . "\n";
        echo "   请检查 corpid 和 corpsecret 是否正确。\n";
    }
} else {
    echo "   ⚠️ 未配置 corpid/corpsecret，跳过 API 测试。\n";
    echo "   请在 config.php 中填写正确的 corpid 和 corpsecret 后重试。\n";
}

echo "\n" . str_repeat("─", 44) . "\n";
echo "✅ 快速检查完成！\n";
echo "   管理后台入口: http://localhost:8080/\n";
echo "   默认用户名: admin  密码: admin123\n";
echo "   回调URL入口: http://localhost:8080/callback.php\n";

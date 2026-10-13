<?php
/**
 * 测试：access_token 获取
 * 
 * 验证 Config、TokenManager、Redis 基本功能是否正常。
 * 
 * 使用方式：
 *   php test/test_token.php
 */
require __DIR__ . '/../vendor/autoload.php';

use Wxwork\Wxwork;

echo "========================================\n";
echo "  企业微信管理系统 - Token 测试\n";
echo "========================================\n\n";

try {
    // 1. 初始化
    echo "[1] 初始化系统...\n";
    $wx = Wxwork::create();
    echo "    ✅ 系统初始化成功\n\n";

    // 2. 检查配置
    echo "[2] 检查配置...\n";
    $config = $wx->getConfig();
    $corpid = $config->getCorpId();
    $secret = $config->getCorpSecret();

    if (empty($corpid) || $corpid === 'ww1234567890abcdef') {
        echo "    ⚠️  corpid 未配置或使用默认值\n";
        echo "    请在 config.local.php 中填入你的企业微信 corpid 和 corpsecret\n";
        exit(1);
    }
    if (empty($secret)) {
        echo "    ⚠️  corpsecret 未配置\n";
        echo "    请在 config.local.php 中填入你的企业微信 corpsecret\n";
        exit(1);
    }
    echo "    ✅ corpid: {$corpid}\n";
    echo "    ✅ secret: " . substr($secret, 0, 4) . "****\n\n";

    // 3. 检查 Redis
    echo "[3] 检查 Redis 连接...\n";
    $redis = $wx->getRedis();
    if ($redis->isAvailable()) {
        echo "    ✅ Redis 已连接\n";
    } else {
        echo "    ⚠️  Redis 未连接（将使用数据库存储 token）\n";
    }
    echo "\n";

    // 4. 获取 Token
    echo "[4] 获取 access_token...\n";
    $start = microtime(true);
    $token = YOUR_SECRET)->getToken();
    $elapsed = round((microtime(true) - $start) * 1000, 2);

    echo "    ✅ Token 获取成功！\n";
    echo "    Token: " . substr($token, 0, 15) . "...\n";
    echo "    耗时: {$elapsed}ms\n\n";

    // 5. 测试 API 调用
    echo "[5] 测试 API 调用（获取部门列表）...\n";
    $start = microtime(true);
    $result = $wx->get('/cgi-bin/department/list');
    $elapsed = round((microtime(true) - $start) * 1000, 2);

    if (($result['errcode'] ?? -1) === 0) {
        $deptCount = count($result['department'] ?? []);
        echo "    ✅ API 调用成功！\n";
        echo "    获取到 {$deptCount} 个部门\n";
        echo "    耗时: {$elapsed}ms\n\n";

        // 列出部门
        if ($deptCount > 0) {
            echo "    部门列表:\n";
            foreach (array_slice($result['department'], 0, 10) as $dept) {
                echo "    - [{$dept['id']}] {$dept['name']} (parent: {$dept['parentid']})\n";
            }
            if ($deptCount > 10) {
                echo "    ... 还有 " . ($deptCount - 10) . " 个部门未显示\n";
            }
        }
    } else {
        echo "    ❌ API 调用失败: " . ($result['errmsg'] ?? '未知错误') . "\n";
    }

    echo "\n========================================\n";
    echo "  测试完成！\n";
    echo "========================================\n";

} catch (\Throwable $e) {
    echo "\n❌ 测试失败: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

<?php
/**
 * 测试：HTTP 请求与核心 API 模块
 * 
 * 验证各核心 API 模块的基本功能。
 * 
 * 使用方式：
 *   php test/test_http.php
 *   php test/test_http.php addressbook    # 仅测试通讯录
 *   php test/test_http.php message        # 仅测试消息
 */
require __DIR__ . '/../vendor/autoload.php';

use Wxwork\Wxwork;

$testModule = $argv[1] ?? 'all';

echo "========================================\n";
echo "  企业微信管理系统 - API 模块测试\n";
echo "========================================\n\n";

try {
    $wx = Wxwork::create();
    $config = $wx->getConfig();

    if (empty($config->getCorpId()) || $config->getCorpId() === 'ww1234567890abcdef') {
        echo "⚠️  corpid/corpsecret 未配置，退出\n";
        echo "请在 config.local.php 中填入真实信息\n";
        exit(1);
    }

    $passCount = 0;
    $failCount = 0;

    function test(string $name, callable $fn): void
    {
        global $passCount, $failCount;
        echo "  [测试] {$name}... ";
        try {
            $result = $fn();
            if ($result) {
                echo "✅ PASS\n";
                $passCount++;
            } else {
                echo "❌ FAIL\n";
                $failCount++;
            }
        } catch (\Throwable $e) {
            echo "❌ ERROR: {$e->getMessage()}\n";
            $failCount++;
        }
    }

    // ==================== 通讯录模块 ====================
    if (in_array($testModule, ['all', 'addressbook'])) {
        echo "\n--- 通讯录模块 (AddressBook) ---\n";

        test('获取部门列表', function () use ($wx) {
            $result = $wx->get('/cgi-bin/department/list');
            return ($result['errcode'] ?? -1) === 0 && isset($result['department']);
        });

        test('获取根部门详情', function () use ($wx) {
            $result = $wx->get('/cgi-bin/department/get', ['id' => 1]);
            return ($result['errcode'] ?? -1) === 0;
        });

        test('获取成员简单列表', function () use ($wx) {
            $result = $wx->get('/cgi-bin/user/simplelist', ['department_id' => 1, 'fetch_child' => 1]);
            return ($result['errcode'] ?? -1) === 0 && isset($result['userlist']);
        });

        test('获取标签列表', function () use ($wx) {
            $result = $wx->get('/cgi-bin/tag/list');
            return ($result['errcode'] ?? -1) === 0 && isset($result['taglist']);
        });
    }

    // ==================== 消息模块 ====================
    if (in_array($testModule, ['all', 'message'])) {
        echo "\n--- 消息模块 (Message) ---\n";
        // 消息模块不实际发送，仅验证方法可用
        test('Message 类实例化', function () use ($wx) {
            $msg = $wx->message;
            return $msg instanceof \Wxwork\Api\Message;
        });
    }

    // ==================== 客户联系模块 ====================
    if (in_array($testModule, ['all', 'external'])) {
        echo "\n--- 客户联系模块 (ExternalContact) ---\n";

        test('ExternalContact 类实例化', function () use ($wx) {
            $ec = $wx->externalContact;
            return $ec instanceof \Wxwork\Api\ExternalContact;
        });

        test('获取企业标签库', function () use ($wx) {
            $result = $wx->get('/cgi-bin/externalcontact/get_corp_tag_list');
            return ($result['errcode'] ?? -1) === 0;
        });
    }

    // ==================== 素材模块 ====================
    if (in_array($testModule, ['all', 'media'])) {
        echo "\n--- 素材模块 (Media) ---\n";

        test('Media 类实例化', function () use ($wx) {
            $media = $wx->media;
            return $media instanceof \Wxwork\Api\Media;
        });
    }

    // ==================== 认证模块 ====================
    if (in_array($testModule, ['all', 'auth'])) {
        echo "\n--- 认证模块 (Auth) ---\n";

        test('OAuth URL 构造', function () use ($wx) {
            $url = $wx->auth->buildOAuthUrl('https://example.com/callback');
            return str_contains($url, 'open.weixin.qq.com') && str_contains($url, 'appid=');
        });

        test('JS-SDK 签名生成', function () use ($wx) {
            $sign = $wx->auth->buildJsSign('https://example.com/page');
            return isset($sign['appId'], $sign['timestamp'], $sign['nonceStr'], $sign['signature']);
        });
    }

    // ==================== 回调模块 ====================
    if (in_array($testModule, ['all', 'callback'])) {
        echo "\n--- 回调模块 (Callback) ---\n";

        test('Crypt 类实例化', function () use ($wx) {
            $crypt = $wx->getCrypt();
            return $crypt instanceof \Wxwork\Core\Crypt;
        });

        test('Callback Server 实例化', function () use ($wx) {
            $callback = $wx->callback;
            return $callback instanceof \Wxwork\Callback\Server;
        });
    }

    // ==================== 数据库 ====================
    if (in_array($testModule, ['all', 'db'])) {
        echo "\n--- 数据库 (Database) ---\n";

        test('数据库连接', function () use ($wx) {
            $db = $wx->getDatabase();
            $version = $db->selectValue('SELECT VERSION()');
            return !empty($version);
        });

        test('配置表读取', function () use ($wx) {
            $db = $wx->getDatabase();
            $rows = $db->select('SELECT cfg_key FROM wxwork_config LIMIT 1');
            return is_array($rows);
        });
    }

    // ==================== 摘要 ====================
    echo "\n========================================\n";
    echo "  测试结果: ✅ {$passCount} 通过, ❌ {$failCount} 失败\n";
    echo "  总计: " . ($passCount + $failCount) . " 项\n";
    echo "========================================\n";

    exit($failCount > 0 ? 1 : 0);

} catch (\Throwable $e) {
    echo "\n❌ 测试初始化失败: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

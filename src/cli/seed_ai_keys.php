<?php
/**
 * AI API Key 初始化脚本
 * 
 * 将用户的 AI API Key 加密写入数据库。
 * 
 * 使用方式：
 *   php src/cli/seed_ai_keys.php
 */
require __DIR__ . '/../../vendor/autoload.php';

use Wxwork\Wxwork;

try {
    $wx = Wxwork::create();
    $db = $wx->getDatabase();
    $appSecretKey = $wx->getConfig()->get('app_secret_key', 'change_this_to_random_string_32_chars_min');

    // ===== 加密函数 =====
    function encKey(string $plaintext, string $secretKey): string {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', hash('sha256', $secretKey, true), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    echo "=== AI API Key 初始化 ===\n\n";

    // ===== Key 1: DashScope 兼容 (多模型) =====
    $key1 = [
        'provider' => 'custom',
        'alias' => 'DashScope 多模型',
        'api_key_enc' => encKey('YOUR_API_TOKEN', $appSecretKey),
        'base_url' => 'https://coding.dashscope.aliyuncs.com/v1',
        'model' => 'qwen3.7-plus, glm-5, kimi-k2.5, MiniMax-M2.5',
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $exists1 = $db->selectValue("SELECT COUNT(*) FROM wxwork_config WHERE cfg_key='ai_key_dashscope_multi'");
    if ($exists1) {
        $db->execute("UPDATE wxwork_config SET cfg_value=?, updated_at=NOW() WHERE cfg_key='ai_key_dashscope_multi'", [json_encode($key1, JSON_UNESCAPED_UNICODE)]);
        echo "✅ Key 1 (DashScope 多模型) 已更新\n";
    } else {
        $db->insertRow('wxwork_config', [
            'cfg_key' => 'ai_key_dashscope_multi',
            'cfg_value' => json_encode($key1, JSON_UNESCAPED_UNICODE),
            'cfg_desc' => 'AI API Key: DashScope 多模型 (qwen3.7/glm/kimi/MiniMax)',
        ]);
        echo "✅ Key 1 (DashScope 多模型) 已创建\n";
    }

    // ===== Key 2: DashScope 标准 (qwen3.7-plus) =====
    $key2 = [
        'provider' => 'custom',
        'alias' => 'DashScope 单模型',
        'api_key_enc' => encKey('YOUR_API_KEY', $appSecretKey),
        'base_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
        'model' => 'qwen3.7-plus',
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $exists2 = $db->selectValue("SELECT COUNT(*) FROM wxwork_config WHERE cfg_key='ai_key_dashscope_single'");
    if ($exists2) {
        $db->execute("UPDATE wxwork_config SET cfg_value=?, updated_at=NOW() WHERE cfg_key='ai_key_dashscope_single'", [json_encode($key2, JSON_UNESCAPED_UNICODE)]);
        echo "✅ Key 2 (DashScope 单模型) 已更新\n";
    } else {
        $db->insertRow('wxwork_config', [
            'cfg_key' => 'ai_key_dashscope_single',
            'cfg_value' => json_encode($key2, JSON_UNESCAPED_UNICODE),
            'cfg_desc' => 'AI API Key: DashScope qwen3.7-plus',
        ]);
        echo "✅ Key 2 (DashScope 单模型) 已创建\n";
    }

    echo "\n=== 完成！可在管理后台 /system/ai_keys 查看 ===\n";

} catch (\Throwable $e) {
    echo "❌ 初始化失败: " . $e->getMessage() . "\n";
    exit(1);
}

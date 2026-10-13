<?php
/**
 * 管理后台 - AI API Key 管理
 * 
 * 功能：多服务商 API Key 管理、加密存储、余额查询、测试连接
 * 支持：OpenAI / Claude / 文心一言 / 通义千问 / DeepSeek / 自定义
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = 'AI API Key 管理';
$error = ''; $success = '';
$keys = [];

// 服务商配置
$providers = [
    'openai'      => ['name' => 'OpenAI',       'icon' => '🟢', 'base_url' => 'https://api.openai.com/v1',        'test_endpoint' => '/models'],
    'claude'      => ['name' => 'Anthropic Claude', 'icon' => '🟣', 'base_url' => 'https://api.anthropic.com/v1',   'test_endpoint' => '/messages'],
    'wenxin'      => ['name' => '百度文心一言',   'icon' => '🔵', 'base_url' => 'https://aip.baidubce.com',       'test_endpoint' => '/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/completions'],
    'qwen'        => ['name' => '阿里通义千问',   'icon' => '🟠', 'base_url' => 'https://dashscope.aliyuncs.com',  'test_endpoint' => '/api/v1/services/aigc/text-generation/generation'],
    'deepseek'    => ['name' => 'DeepSeek',       'icon' => '🔴', 'base_url' => 'https://api.deepseek.com',       'test_endpoint' => '/v1/models'],
    'custom'      => ['name' => '自定义',          'icon' => '⚙️', 'base_url' => '',                              'test_endpoint' => ''],
];

// 加密/解密函数（使用 config 中的 app_secret_key）
function encryptKey(string $plaintext, string $secretKey): string {
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', hash('sha256', $secretKey, true), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}
function decryptKey(string $ciphertext, string $secretKey): string {
    $data = base64_decode($ciphertext);
    if ($data === false || strlen($data) < 16) return '';
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'aes-256-cbc', hash('sha256', $secretKey, true), OPENSSL_RAW_DATA, $iv) ?: '';
}

$appSecretKey = $wx->getConfig()->get('app_secret_key', 'change_this_to_random_string_32_chars_min');

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        $db = $wx->getDatabase();
        switch ($action) {
            case 'seed':
                // 预置用户提供的 AI Keys
                $seedKeys = [
                    [
                        'cfg_key' => 'ai_key_dashscope_multi',
                        'provider' => 'custom',
                        'alias' => 'DashScope 多模型',
                        'api_key' => 'YOUR_API_TOKEN',
                        'base_url' => 'https://coding.dashscope.aliyuncs.com/v1',
                        'model' => 'qwen3.7-plus, glm-5, kimi-k2.5, MiniMax-M2.5',
                    ],
                    [
                        'cfg_key' => 'ai_key_dashscope_single',
                        'provider' => 'custom',
                        'alias' => 'DashScope 单模型',
                        'api_key' => 'YOUR_API_KEY',
                        'base_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
                        'model' => 'qwen3.7-plus',
                    ],
                ];
                $inserted = 0;
                foreach ($seedKeys as $sk) {
                    $encrypted = encryptKey($sk['api_key'], $appSecretKey);
                    unset($sk['api_key']);
                    $value = array_merge($sk, ['api_key_enc' => $encrypted, 'updated_at' => date('Y-m-d H:i:s')]);
                    unset($value['cfg_key']);
                    $exists = $db->selectValue("SELECT COUNT(*) FROM wxwork_config WHERE cfg_key=?", [$sk['cfg_key']]);
                    if ($exists) {
                        $db->execute("UPDATE wxwork_config SET cfg_value=?, updated_at=NOW() WHERE cfg_key=?", [json_encode($value, JSON_UNESCAPED_UNICODE), $sk['cfg_key']]);
                    } else {
                        $db->insertRow('wxwork_config', [
                            'cfg_key' => $sk['cfg_key'],
                            'cfg_value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                            'cfg_desc' => 'AI API Key: '.$sk['alias'],
                        ]);
                    }
                    $inserted++;
                }
                $success = "✅ 已成功导入 {$inserted} 个 AI API Key（DashScope 多模型 + 单模型）";
                break;

            case 'save':
                $provider = $_POST['provider'] ?? '';
                $alias = trim($_POST['alias'] ?? '');
                $apiKey = YOUR_SECRET'api_key'] ?? '';
                $baseUrl = trim($_POST['base_url'] ?? '');
                $model = trim($_POST['model'] ?? '');
                if (empty($provider) || empty($apiKey)) throw new \RuntimeException('服务商和 API Key 不能为空');
                if (empty($alias)) $alias = $providers[$provider]['name'] ?? $provider;
                $encrypted = encryptKey($apiKey, $appSecretKey);
                $keyName = 'ai_key_' . $provider;
                $value = json_encode([
                    'provider' => $provider, 'alias' => $alias, 'api_key_enc' => $encrypted,
                    'base_url' => $baseUrl, 'model' => $model, 'updated_at' => date('Y-m-d H:i:s'),
                ], JSON_UNESCAPED_UNICODE);
                $exists = $db->selectValue("SELECT COUNT(*) FROM wxwork_config WHERE cfg_key=?", [$keyName]);
                if ($exists) {
                    $db->execute("UPDATE wxwork_config SET cfg_value=?, updated_at=NOW() WHERE cfg_key=?", [$value, $keyName]);
                } else {
                    $db->insertRow('wxwork_config', ['cfg_key' => $keyName, 'cfg_value' => $value, 'cfg_desc' => 'AI API Key: '.$alias]);
                }
                $success = "「{$alias}」API Key 已保存";
                break;

            case 'delete':
                $keyName = $_POST['cfg_key'] ?? '';
                if (empty($keyName)) {
                    $provider = $_POST['provider'] ?? '';
                    $keyName = $provider ? 'ai_key_'.$provider : '';
                }
                if ($keyName) {
                    $db->execute("DELETE FROM wxwork_config WHERE cfg_key=?", [$keyName]);
                    $success = "API Key 已删除";
                }
                break;

            case 'test':
                $provider = $_POST['provider'] ?? '';
                $testKey = $_POST['test_key'] ?? '';
                $testUrl = $_POST['test_url'] ?? '';
                $testModel = $_POST['test_model'] ?? '';
                if (empty($testKey)) throw new \RuntimeException('请提供 API Key 进行测试');

                // 如果是 __stored__，从数据库解密获取
                if ($testKey === '__stored__') {
                    $db = $wx->getDatabase();
                    $row = $db->selectOne("SELECT cfg_value FROM wxwork_config WHERE cfg_key=?", ['ai_key_'.$provider]);
                    if (!$row) throw new \RuntimeException('未找到存储的 API Key');
                    $val = json_decode($row['cfg_value'], true);
                    if (!$val || empty($val['api_key_enc'])) throw new \RuntimeException('API Key 数据异常');
                    $testKey = decryptKey($val['api_key_enc'], $appSecretKey);
                    if (empty($testKey)) throw new \RuntimeException('API Key 解密失败');
                    if (empty($testUrl) && !empty($val['base_url'])) $testUrl = $val['base_url'];
                    $provider = $val['provider'] ?? $provider;
                }

                $url = $testUrl ?: ($providers[$provider]['base_url'] ?? '');
                $endpoint = $providers[$provider]['test_endpoint'] ?? '/v1/models';
                
                $ch = curl_init();
                $fullUrl = rtrim($url, '/') . '/' . ltrim($endpoint, '/');
                curl_setopt_array($ch, [
                    CURLOPT_URL => $fullUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $testKey,
                        'Content-Type: application/json',
                    ],
                ]);
                $resp = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($curlError) {
                    throw new \RuntimeException("连接失败: {$curlError}");
                }
                if ($httpCode >= 200 && $httpCode < 300) {
                    $success = "✅ 测试成功！HTTP {$httpCode}，服务端响应正常";
                } else {
                    $respTrunc = mb_substr($resp, 0, 200);
                    throw new \RuntimeException("HTTP {$httpCode}: {$respTrunc}");
                }
                break;

            case 'test_chat':
                header('Content-Type: application/json; charset=utf-8');
                try {
                    $provider = $_POST['provider'] ?? '';
                    $testKey = $_POST['test_key'] ?? '';
                    $testUrl = $_POST['test_url'] ?? '';
                    $testModel = trim($_POST['test_model'] ?? '');
                    if (empty($testKey)) throw new \RuntimeException('请提供 API Key');

                    // 如果是 __stored__，从数据库解密获取
                    if ($testKey === '__stored__') {
                        $db = $wx->getDatabase();
                        $row = $db->selectOne("SELECT cfg_value FROM wxwork_config WHERE cfg_key=?", ['ai_key_'.$provider]);
                        if (!$row) throw new \RuntimeException('未找到存储的 API Key，请先保存');
                        $val = json_decode($row['cfg_value'], true);
                        if (!$val || empty($val['api_key_enc'])) throw new \RuntimeException('API Key 数据异常');
                        $testKey = decryptKey($val['api_key_enc'], $appSecretKey);
                        if (empty($testKey)) throw new \RuntimeException('API Key 解密失败');
                        if (empty($testUrl) && !empty($val['base_url'])) $testUrl = $val['base_url'];
                        if (empty($testModel) && !empty($val['model'])) $testModel = $val['model'];
                        $provider = $val['provider'] ?? $provider;
                    }

                    $url = $testUrl ?: ($providers[$provider]['base_url'] ?? '');
                    if (empty($url)) throw new \RuntimeException('请设置 Base URL');

                    // 构造聊天请求
                    $model = $testModel ?: 'gpt-4o-mini';
                    $messages = [
                        ['role' => 'user', 'content' => '你好，请用一句话回复"hello"'],
                    ];

                    // 判断是否 OpenAI 兼容格式（大多数现代服务商都支持）
                    $isOpenaiCompat = true;
                    // 检测 URL 是否 OpenAI 兼容（含 compatible-mode 或末尾是 /v1）
                    if (str_contains($url, 'compatible-mode') || preg_match('#/v1$#', rtrim($url, '/'))) {
                        $isOpenaiCompat = true;
                    }
                    // 文心一言使用自有格式
                    if ($provider === 'wenxin') $isOpenaiCompat = false;

                    if ($isOpenaiCompat) {
                        // base_url 末尾如果是 /v1，不加重复
                        $chatEndpoint = preg_match('#/v1$#', rtrim($url, '/')) ? '/chat/completions' : '/v1/chat/completions';
                        $requestBody = json_encode([
                            'model' => $model,
                            'messages' => $messages,
                            'max_tokens' => 100,
                            'temperature' => 0.7,
                        ], JSON_UNESCAPED_UNICODE);
                        $authHeader = 'Authorization: Bearer ' . $testKey;
                    } elseif ($provider === 'wenxin') {
                        $chatEndpoint = '/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/completions';
                        $requestBody = json_encode([
                            'messages' => $messages,
                        ], JSON_UNESCAPED_UNICODE);
                        $authHeader = 'Authorization: Bearer ' . $testKey;
                    } else {
                        throw new \RuntimeException('不支持的 API 格式');
                    }

                    $fullChatUrl = rtrim($url, '/') . '/' . ltrim($chatEndpoint, '/');
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $fullChatUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $requestBody,
                        CURLOPT_HTTPHEADER => [
                            $authHeader,
                            'Content-Type: application/json',
                        ],
                    ]);
                    $respBody = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    if ($curlError) throw new \RuntimeException("连接失败: {$curlError}");

                    // 尝试格式化 JSON
                    $respPretty = $respBody;
                    $decoded = json_decode($respBody, true);
                    if ($decoded !== null) {
                        $respPretty = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }
                    $reqPretty = json_encode(json_decode($requestBody, true), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                    echo json_encode([
                        'ok' => $httpCode >= 200 && $httpCode < 300,
                        'http_code' => $httpCode,
                        'request' => $reqPretty,
                        'response' => $respPretty,
                        'error' => $httpCode >= 200 && $httpCode < 300 ? '' : ($decoded['error']['message'] ?? "HTTP {$httpCode}"),
                    ], JSON_UNESCAPED_UNICODE);
                } catch (\Throwable $e) {
                    echo json_encode([
                        'ok' => false,
                        'http_code' => 0,
                        'request' => $requestBody ?? '',
                        'response' => '',
                        'error' => $e->getMessage(),
                    ], JSON_UNESCAPED_UNICODE);
                }
                exit;
        }
    } catch (\Throwable $e) { $error = $e->getMessage(); }
}

// 加载所有 AI Key
try {
    $db = $wx->getDatabase();
    $rows = $db->select("SELECT * FROM wxwork_config WHERE cfg_key LIKE 'ai_key_%' ORDER BY updated_at DESC");
    foreach ($rows as $r) {
        $val = json_decode($r['cfg_value'], true);
        if ($val) {
            $val['id'] = $r['id'];
            $val['cfg_key'] = $r['cfg_key'];
            $val['created_at'] = $r['created_at'];
            $keys[] = $val;
        }
    }
} catch (\Throwable $e) { $error = $e->getMessage(); }

require __DIR__ . '/../../layout/header.php';
?>

<style>
.provider-card { border: 1px solid #e5e5e5; border-radius: 6px; padding: 15px; margin-bottom: 10px; transition: box-shadow 0.2s; }
.provider-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.provider-card .provider-icon { font-size: 28px; }
.provider-card .provider-name { font-size: 15px; font-weight: 600; }
.provider-card .key-masked { font-family: 'Courier New', monospace; font-size: 12px; color: #888; }
.key-list-item { padding: 12px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between; }
.key-list-item:last-child { border-bottom: none; }
</style>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row">
    <!-- 已保存的 Key 列表 -->
    <div class="col-md-7">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-key"></i> <span class="caption-subject bold uppercase">已配置的 API Key</span></div>
                <div class="actions">
                    <span class="text-muted" style="font-size:12px;margin-right:8px">密钥已加密存储</span>
                </div>
            </div>
            <div class="portlet-body">
                <?php if (empty($keys)): ?>
                <div class="text-center text-muted" style="padding:50px">
                    <i class="fa fa-key fa-4x"></i>
                    <p style="margin-top:15px">尚未添加任何 AI API Key</p>
                    <p style="font-size:12px">在右侧添加一个服务商开始使用</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead><tr><th>服务商</th><th>别名</th><th>模型</th><th>Base URL</th><th>状态</th><th>更新时间</th><th width="120">操作</th></tr></thead>
                        <tbody>
                            <?php foreach ($keys as $k): ?>
                            <tr>
                                <td><span style="font-size:20px"><?= $providers[$k['provider']]['icon'] ?? '⚙️' ?></span></td>
                                <td><strong><?= htmlspecialchars($k['alias'] ?? $providers[$k['provider']]['name'] ?? $k['provider']) ?></strong></td>
                                <td><?= htmlspecialchars($k['model'] ?? '-') ?></td>
                                <td style="font-size:11px"><?= htmlspecialchars(mb_substr($k['base_url'] ?? '-', 0, 40)) ?></td>
                                <td><i class="fa fa-check-circle text-success"></i> 已配置</td>
                                <td style="font-size:11px"><?= date('m-d H:i', strtotime($k['updated_at'] ?? $k['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <button class="btn btn-xs btn-info" onclick="testKey('<?= $k['provider'] ?>')" title="测试连接"><i class="fa fa-plug"></i></button>
                                    <button class="btn btn-xs btn-success" onclick="testChat('<?= $k['provider'] ?>')" title="发送测试消息"><i class="fa fa-comment"></i></button>
                                    <form method="post" style="display:inline" onsubmit="return confirm('确定删除此 Key？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="cfg_key" value="<?= htmlspecialchars($k['cfg_key'] ?? 'ai_key_'.$k['provider']) ?>">
                                        <button class="btn btn-xs red"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 添加/编辑 Key -->
    <div class="col-md-5">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-plus-circle text-success"></i> <span class="caption-subject bold uppercase">添加 API Key</span></div>
                <div class="actions">
                    <form method="post" style="display:inline" onsubmit="return confirm('将导入 2 个 DashScope API Key，继续？')">
                        <input type="hidden" name="action" value="seed">
                        <button class="btn btn-sm btn-warning"><i class="fa fa-magic"></i> 快捷导入</button>
                    </form>
                </div>
            </div>
            <div class="portlet-body">
                <form method="post" id="keyForm">
                    <input type="hidden" name="action" value="save">

                    <div class="form-group">
                        <label>AI 服务商 <span class="text-danger">*</span></label>
                        <select name="provider" id="keyProvider" class="form-control" onchange="onProviderChange()">
                            <option value="">-- 选择服务商 --</option>
                            <?php foreach ($providers as $pk => $pv): ?>
                            <option value="<?= $pk ?>" data-baseurl="<?= htmlspecialchars($pv['base_url']) ?>">
                                <?= $pv['icon'] ?> <?= $pv['name'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>别名（方便识别）</label>
                        <input type="text" name="alias" class="form-control input-sm" placeholder="例如：主力 GPT-4、备用 Claude">
                    </div>

                    <div class="form-group">
                        <label>API Key <span class="text-danger">*</span></label>
                        <input type="password" name="YOUR_PASSWORD" class="form-control" placeholder="sk-..." required>
                        <span class="help-block" style="font-size:11px">
                            <i class="fa fa-lock"></i> 密钥将使用 AES-256 加密后存储
                        </span>
                    </div>

                    <div class="form-group">
                        <label>模型名称（可选）</label>
                        <input type="text" name="model" class="form-control input-sm" placeholder="例如：gpt-4o、claude-sonnet-4">
                    </div>

                    <div class="form-group">
                        <label>自定义 Base URL（可选）</label>
                        <input type="text" name="base_url" id="keyBaseUrl" class="form-control input-sm" placeholder="留空使用默认地址">
                        <span class="help-block" style="font-size:11px">适用于反向代理/中转站地址</span>
                    </div>

                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> 保存</button>
                    <button type="button" class="btn btn-info" id="testBtn" onclick="testCurrentKey()" style="display:none"><i class="fa fa-plug"></i> 测试连接</button>
                </form>

                <hr>
                <div class="alert alert-default" style="background:#f9f9f9;font-size:12px">
                    <h6 style="margin-top:0"><i class="fa fa-shield"></i> 安全说明</h6>
                    <ul style="margin-bottom:0;padding-left:16px">
                        <li>API Key 使用 AES-256 加密存储在数据库</li>
                        <li>管理后台不会明文显示密钥</li>
                        <li>建议为每个服务商配置独立 Key</li>
                        <li>测试连接时 Key 会临时用于验证</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 测试连接 Modal -->
<div class="modal fade" id="testModal"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header">
        <h4><i class="fa fa-plug"></i> 测试结果</h4>
    </div>
    <div class="modal-body">
        <div id="testStatus" style="text-align:center;padding:10px;margin-bottom:15px" class="alert"></div>

        <ul class="nav nav-tabs" id="testTabs" style="display:none">
            <li class="active"><a href="#tabRequest" data-toggle="tab"><i class="fa fa-arrow-up"></i> 发送内容</a></li>
            <li><a href="#tabResponse" data-toggle="tab"><i class="fa fa-arrow-down"></i> 返回内容</a></li>
        </ul>

        <div class="tab-content" style="margin-top:10px">
            <div class="tab-pane active" id="tabRequest">
                <pre id="testRequest" style="max-height:400px;overflow:auto;background:#f5f5f5;padding:12px;border:1px solid #ddd;border-radius:4px;font-size:12px;white-space:pre-wrap;word-break:break-all;"></pre>
            </div>
            <div class="tab-pane" id="tabResponse">
                <pre id="testResponse" style="max-height:400px;overflow:auto;background:#f5f5f5;padding:12px;border:1px solid #ddd;border-radius:4px;font-size:12px;white-space:pre-wrap;word-break:break-all;"></pre>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <span id="testHttpCode" class="pull-left text-muted" style="font-size:12px"></span>
        <button class="btn btn-default" data-dismiss="modal">关闭</button>
    </div>
</div></div></div>

<script>
function onProviderChange() {
    var sel = document.getElementById('keyProvider');
    var opt = sel ? sel.options[sel.selectedIndex] : null;
    var baseUrl = opt ? opt.dataset.baseurl : '';
    var baseUrlEl = document.getElementById('keyBaseUrl');
    if (baseUrlEl) baseUrlEl.placeholder = baseUrl || '输入自定义地址';
    var testBtn = document.getElementById('testBtn');
    if (testBtn) testBtn.style.display = sel && sel.value ? '' : 'none';
}

function testKey(provider) {
    var statusEl = document.getElementById('testStatus');
    var reqEl = document.getElementById('testRequest');
    var resEl = document.getElementById('testResponse');
    statusEl.className = 'alert alert-info';
    statusEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 正在测试连接，请稍候...';
    reqEl.textContent = '';
    resEl.textContent = '';
    document.getElementById('testTabs').style.display = 'none';
    document.getElementById('testHttpCode').textContent = '';
    if (typeof jQuery !== 'undefined') jQuery('#testModal').modal('show');

    var xhr = new XMLHttpRequest();
    var fd = new FormData();
    fd.append('action', 'test');
    fd.append('provider', provider);
    fd.append('test_key', '__stored__');
    fd.append('test_url', '');
    xhr.open('POST', '', true);
    xhr.onload = function() {
        if (xhr.responseText.includes('测试成功') || xhr.responseText.includes('✅')) {
            statusEl.className = 'alert alert-success';
            statusEl.innerHTML = '<i class="fa fa-check-circle"></i> ✅ 连接成功！API 服务端响应正常';
        } else {
            var msg = xhr.responseText.match(/alert-danger[^>]*>\s*([^<]+)/);
            statusEl.className = 'alert alert-danger';
            statusEl.innerHTML = '<i class="fa fa-times-circle"></i> ❌ ' + (msg ? msg[1] : '测试失败');
        }
    };
    xhr.onerror = function() {
        statusEl.className = 'alert alert-danger';
        statusEl.innerHTML = '<i class="fa fa-times-circle"></i> ❌ 网络请求失败';
    };
    xhr.send(fd);
}

function testChat(provider) {
    var statusEl = document.getElementById('testStatus');
    var reqEl = document.getElementById('testRequest');
    var resEl = document.getElementById('testResponse');
    var tabsEl = document.getElementById('testTabs');
    var httpCodeEl = document.getElementById('testHttpCode');

    statusEl.className = 'alert alert-info';
    statusEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 正在发送测试消息，请稍候...';
    reqEl.textContent = '';
    resEl.textContent = '';
    tabsEl.style.display = 'none';
    httpCodeEl.textContent = '';
    if (typeof jQuery !== 'undefined') jQuery('#testModal').modal('show');

    var xhr = new XMLHttpRequest();
    var fd = new FormData();
    fd.append('action', 'test_chat');
    fd.append('provider', provider);
    fd.append('test_key', '__stored__');
    fd.append('test_url', '');
    fd.append('test_model', '');
    xhr.open('POST', '', true);
    xhr.onload = function() {
        try {
            var data = JSON.parse(xhr.responseText);
            reqEl.textContent = data.request || '(无请求数据)';
            resEl.textContent = data.response || '(无响应数据)';
            httpCodeEl.textContent = 'HTTP ' + (data.http_code || '-');

            if (data.ok) {
                statusEl.className = 'alert alert-success';
                statusEl.innerHTML = '<i class="fa fa-check-circle"></i> ✅ 测试消息发送成功！HTTP ' + data.http_code;
            } else {
                statusEl.className = 'alert alert-warning';
                statusEl.innerHTML = '<i class="fa fa-exclamation-triangle"></i> ⚠️ 请求完成但有错误：' + (data.error || 'HTTP ' + data.http_code);
            }
            tabsEl.style.display = '';
            // 切换到响应 tab
            if (typeof jQuery !== 'undefined') jQuery('#testTabs a:last').tab('show');
        } catch(e) {
            statusEl.className = 'alert alert-danger';
            statusEl.innerHTML = '<i class="fa fa-times-circle"></i> ❌ 解析响应失败：' + e.message;
            httpCodeEl.textContent = '';
        }
    };
    xhr.onerror = function() {
        statusEl.className = 'alert alert-danger';
        statusEl.innerHTML = '<i class="fa fa-times-circle"></i> ❌ 网络请求失败';
    };
    xhr.send(fd);
}

document.addEventListener('DOMContentLoaded', onProviderChange);
</script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

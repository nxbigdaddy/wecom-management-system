<?php
/**
 * 管理后台 - 智能表格管理（深度优化版 v3）
 * 
 * 功能覆盖：
 *   1. 📄 文档管理 — 创建/查询/重命名/删除文档、微盘文件浏览
 *   2. 📊 子表管理 — 添加/查询/更新/删除子表、自动发现
 *   3. 📋 记录管理 — 查询(分页)/添加/更新/删除记录、字段感知编辑器
 *   4. 🔧 字段管理 — 添加/查询/更新/删除字段（17种类型）
 *   5. 👁️ 视图管理 — 添加/查询/删除视图（5种类型）
 *   6. 📦 编组管理 — 添加/查询/删除编组
 *   7. 🔐 权限管理 — 文档级权限设置
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '智能表格';
$error = ''; $success = '';
$tab = $_GET['tab'] ?? $_POST['tab'] ?? 'docs';

// 共用状态
$records = $sheetList = $fieldList = $viewList = $groupList = [];
$fileList = $docInfo = null;
$members = $departments = $tags = [];
$currentDocid = $_POST['docid'] ?? $_GET['docid'] ?? '';
$currentSheetId = $_POST['sheet_id'] ?? $_GET['sheet_id'] ?? '';
$recordTotal = 0; $recordOffset = 0;

// 获取成员、部门、标签列表
try {
    $memberResult = $wx->get('/cgi-bin/user/simplelist', ['department_id' => 1, 'fetch_child' => 1]);
    $members = ($memberResult['errcode'] ?? -1) === 0 ? ($memberResult['userlist'] ?? []) : [];
    $deptResult = $wx->get('/cgi-bin/department/list');
    $departments = ($deptResult['errcode'] ?? -1) === 0 ? ($deptResult['department'] ?? []) : [];
    $tagResult = $wx->get('/cgi-bin/tag/list');
    $tags = ($tagResult['errcode'] ?? -1) === 0 ? ($tagResult['taglist'] ?? []) : [];
} catch (\Throwable $e) {}

// 如果提供了 docid，尝试自动获取文档信息和子表列表
if ($currentDocid && in_array($tab, ['docs', 'sheets', 'records', 'fields', 'views', 'groups'])) {
    try {
        $docInfo = $wx->wedoc->getDocument($currentDocid);
        $docInfo = $docInfo['document'] ?? $docInfo;
    } catch (\Throwable $e) {}

    // 自动获取子表列表
    if (in_array($tab, ['sheets', 'records', 'fields', 'views', 'groups'])) {
        try {
            $sheetResult = $wx->smartsheet->getSheet($currentDocid, '');
            $sheetList = $sheetResult['sheet_list'] ?? ($sheetResult['sheet_id'] ? [$sheetResult] : []);
        } catch (\Throwable $e) {}
    }
}

// 加载已缓存的文档列表
$cachedDocs = [];
try {
    $db = $wx->getDatabase();
    $cachedDocs = $db->select("SELECT cfg_value FROM wxwork_config WHERE cfg_key LIKE 'doc_cache_%' ORDER BY updated_at DESC LIMIT 50");
    $cachedDocs = array_map(function($r) { return json_decode($r['cfg_value'], true); }, $cachedDocs);
    $cachedDocs = array_filter($cachedDocs, fn($d) => !empty($d['docid']));
} catch (\Throwable $e) {}

// 加载智能表格模板预设
$smartsheetTemplates = [];
$tplFile = __DIR__ . '/../../data/smartsheet_templates.php';
if (file_exists($tplFile)) {
    $smartsheetTemplates = require $tplFile;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $docid = $_POST['docid'] ?? $currentDocid;
    $sheetId = $_POST['sheet_id'] ?? $currentSheetId;

    try {
        switch ($action) {
            // ===== 文档管理 =====
            case 'create_doc':
                $docName = $_POST['doc_name'] ?? '';
                $spaceId = $_POST['space_id'] ?? '';
                $fatherId = $_POST['father_id'] ?? '';
                $templateId = $_POST['template_id'] ?? '';
                $adminUsersStr = $_POST['admin_users'] ?? '';
                $adminUsers = [];
                if (!empty($adminUsersStr)) {
                    $selections = array_filter(explode(',', $adminUsersStr));
                    $userIds = $deptIds = $tagIds = [];
                    foreach ($selections as $sel) {
                        if (str_starts_with($sel, 'dept:')) $deptIds[] = substr($sel, 5);
                        elseif (str_starts_with($sel, 'tag:')) $tagIds[] = substr($sel, 4);
                        elseif ($sel) $userIds[] = $sel;
                    }
                    foreach ($deptIds as $did) {
                        try { $dm = $wx->get('/cgi-bin/user/simplelist', ['department_id'=>(int)$did,'fetch_child'=>true]);
                        if (($dm['errcode']??-1)===0) foreach ($dm['userlist']??[] as $m) $userIds[]=$m['userid']; } catch(\Throwable $e){}
                    }
                    foreach ($tagIds as $tid) {
                        try { $tm = $wx->get('/cgi-bin/tag/get', ['tagid'=>(int)$tid]);
                        if (($tm['errcode']??-1)===0) foreach ($tm['userlist']??[] as $m) $userIds[]=$m['userid']; } catch(\Throwable $e){}
                    }
                    $adminUsers = array_unique($userIds);
                }
                if ($docName) {
                    $result = $wx->wedoc->createSmartsheet($docName, $spaceId, $fatherId, $adminUsers);
                    $currentDocid = $result['docid'] ?? '';
                    $success = "文档创建成功！docid: <code>{$currentDocid}</code>";
                    if ($result['url'] ?? '') $success .= "，<a href=\"{$result['url']}\" target=\"_blank\">打开文档</a>";
                    // 缓存文档信息
                    if ($currentDocid) {
                        try { $wx->getDatabase()->execute(
                            "INSERT INTO wxwork_config (cfg_key, cfg_value, cfg_desc) VALUES (?,?,?) ON DUPLICATE KEY UPDATE cfg_value=VALUES(cfg_value), updated_at=NOW()",
                            ['doc_cache_'.$currentDocid, json_encode(['docid'=>$currentDocid,'name'=>$docName,'url'=>$result['url']??'','space_id'=>$spaceId,'created_at'=>date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE), '缓存的文档: '.$docName]
                        ); } catch(\Throwable $e){}
                    }
                    // 如果选择了模板，自动创建子表和字段
                    $templateId = $_POST['template_id'] ?? '';
                    if ($templateId && isset($smartsheetTemplates[$templateId]) && $currentDocid) {
                        $tpl = $smartsheetTemplates[$templateId];
                        $createdSheets = 0; $createdFields = 0;
                        foreach ($tpl['sheets'] as $sheetDef) {
                            try {
                                $sr = $wx->smartsheet->addSheet($currentDocid, $sheetDef['title']);
                                $nsid = $sr['sheet_id'] ?? '';
                                if ($nsid && !empty($sheetDef['fields'])) {
                                    $fields = array_map(fn($f) => ['field_title'=>$f['field_title'], 'field_type'=>$f['field_type']], $sheetDef['fields']);
                                    foreach (array_chunk($fields, 50) as $batch) {
                                        $wx->smartsheet->addFields($currentDocid, $nsid, $batch);
                                        $createdFields += count($batch);
                                    }
                                    $createdSheets++;
                                }
                            } catch (\Throwable $e) { error_log("[模板] ".$e->getMessage()); }
                        }
                        $success .= "<br>📋 已按「{$tpl['name']}」模板自动创建 {$createdSheets} 个子表、{$createdFields} 个字段";
                    }
                }
                break;

            case 'get_doc_info':
                if ($docid) {
                    $docInfo = $wx->wedoc->getDocument($docid);
                    $docInfo = $docInfo['document'] ?? $docInfo;
                    $success = "文档信息已加载：{$docInfo['doc_name']}";
                    // 缓存查询过的文档
                    if ($docid && !empty($docInfo['doc_name'])) {
                        try { $wx->getDatabase()->execute(
                            "INSERT INTO wxwork_config (cfg_key, cfg_value, cfg_desc) VALUES (?,?,?) ON DUPLICATE KEY UPDATE cfg_value=VALUES(cfg_value), updated_at=NOW()",
                            ['doc_cache_'.$docid, json_encode(['docid'=>$docid,'name'=>$docInfo['doc_name'],'creator'=>$docInfo['creator']??'','created_at'=>date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE), '文档: '.$docInfo['doc_name']]
                        ); } catch(\Throwable $e){}
                    }
                }
                break;

            case 'rename_doc':
                if ($docid && ($_POST['new_name'] ?? '')) {
                    $wx->wedoc->renameDocument($docid, $_POST['new_name']);
                    $success = "文档已重命名为：{$_POST['new_name']}";
                }
                break;

            case 'delete_doc':
                if ($docid && ($_POST['confirm_name'] ?? '') === ($_POST['doc_name_confirm'] ?? '--')) {
                    $wx->wedoc->deleteDocument($docid);
                    $success = "文档已删除";
                    $currentDocid = '';
                } else {
                    $error = '请输入正确的文档名称以确认删除';
                }
                break;

            case 'get_files':
                $spaceId = $_POST['space_id'] ?? '';
                $fatherId = $_POST['father_id'] ?? $spaceId;
                if ($spaceId && $fatherId) {
                    $result = $wx->wedrive->getFileList($spaceId, $fatherId, 6, (int)($_POST['start'] ?? 0), 100);
                    $fileList = $result['file_list']['item'] ?? [];
                    $hasMore = !($result['file_list']['has_more'] ?? true);
                    $fileStart = (int)($_POST['start'] ?? 0) + 100;
                    $success = "获取到 " . count($fileList) . " 个文件";
                }
                break;

            // ===== 子表管理 =====
            case 'add_sheet':
                if ($docid) {
                    $result = $wx->smartsheet->addSheet($docid, $_POST['sheet_title'] ?? '新子表');
                    $currentSheetId = $result['sheet_id'] ?? '';
                    $success = "子表创建成功！sheet_id: <code>{$currentSheetId}</code>";
                }
                break;
            case 'get_sheets':
                if ($docid) {
                    $result = $wx->smartsheet->getSheet($docid, $sheetId ?: '');
                    $sheetList = $result['sheet_list'] ?? ($result['sheet_id'] ? [$result] : []);
                    $success = "获取到 " . count($sheetList) . " 个子表";
                }
                break;
            case 'delete_sheet':
                if ($docid && $sheetId) { $wx->smartsheet->deleteSheet($docid, $sheetId); $success = "子表已删除"; }
                break;
            case 'update_sheet':
                if ($docid && $sheetId) {
                    $wx->smartsheet->updateSheet($docid, $sheetId, ['title' => $_POST['sheet_title'] ?? '']);
                    $success = "子表已更新";
                }
                break;

            // ===== 记录管理 =====
            case 'query_records':
                if ($docid && $sheetId) {
                    $offset = (int)($_POST['offset'] ?? 0);
                    $limit = min((int)($_POST['limit'] ?? 100), 500);
                    $result = $wx->smartsheet->getRecords($docid, $sheetId, compact('offset', 'limit'));
                    $records = $result['records'] ?? [];
                    $recordTotal = $result['total'] ?? count($records);
                    $recordOffset = $offset;
                    $success = "查询成功，共 {$recordTotal} 条（当前 " . count($records) . " 条）";
                }
                break;
            case 'add_record':
                if ($docid && $sheetId) {
                    $rj = $_POST['record_json'] ?? '';
                    $rd = json_decode($rj, true);
                    if (json_last_error() !== JSON_ERROR_NONE) throw new \RuntimeException('JSON 格式错误: ' . json_last_error_msg());
                    $result = $wx->smartsheet->addRecords($docid, $sheetId, [$rd]);
                    $success = "记录添加成功";
                }
                break;
            case 'update_record':
                if ($docid && $sheetId) {
                    $rj = $_POST['record_json'] ?? '';
                    $rd = json_decode($rj, true);
                    if (json_last_error() !== JSON_ERROR_NONE) throw new \RuntimeException('JSON 格式错误');
                    $wx->smartsheet->updateRecords($docid, $sheetId, [$rd]);
                    $success = "记录更新成功";
                }
                break;
            case 'delete_record':
                if ($docid && $sheetId) {
                    $rids = array_filter(explode(',', $_POST['record_ids'] ?? ''));
                    if (empty($rids)) throw new \RuntimeException('请提供记录 ID');
                    $wx->smartsheet->deleteRecords($docid, $sheetId, $rids);
                    $success = "已删除 " . count($rids) . " 条记录";
                }
                break;

            // ===== 字段管理 =====
            case 'add_field':
                if ($docid && $sheetId) {
                    $result = $wx->smartsheet->addFields($docid, $sheetId, [[
                        'field_title' => $_POST['field_title'] ?? '新字段',
                        'field_type'  => $_POST['field_type'] ?? 'FIELD_TYPE_TEXT',
                    ]]);
                    $success = "字段创建成功！field_id: " . ($result['fields'][0]['field_id'] ?? '--');
                }
                break;
            case 'get_fields':
                if ($docid && $sheetId) {
                    $result = $wx->smartsheet->getFields($docid, $sheetId);
                    $fieldList = $result['fields'] ?? [];
                    $success = "获取到 " . count($fieldList) . " 个字段";
                }
                break;
            case 'update_field':
                if ($docid && $sheetId) {
                    $ud = ['field_id' => $_POST['field_id'] ?? ''];
                    if ($_POST['field_title'] ?? '') $ud['field_title'] = $_POST['field_title'];
                    if ($_POST['field_type'] ?? '') $ud['field_type'] = $_POST['field_type'];
                    $wx->smartsheet->updateFields($docid, $sheetId, [$ud]);
                    $success = "字段已更新";
                }
                break;
            case 'delete_field':
                if ($docid && $sheetId) {
                    $fids = array_filter(explode(',', $_POST['field_ids'] ?? ''));
                    if (empty($fids)) throw new \RuntimeException('请提供字段 ID');
                    $wx->smartsheet->deleteFields($docid, $sheetId, $fids);
                    $success = "已删除 " . count($fids) . " 个字段";
                }
                break;

            // ===== 视图/编组/权限 =====
            case 'add_view':
                if ($docid && $sheetId) {
                    $result = $wx->smartsheet->addView($docid, $sheetId, [
                        'view_title' => $_POST['view_title'] ?? '新视图',
                        'view_type'  => $_POST['view_type'] ?? 'VIEW_TYPE_GRID',
                    ]);
                    $success = "视图创建成功";
                }
                break;
            case 'get_views':
                if ($docid && $sheetId) {
                    $result = $wx->smartsheet->getView($docid, $sheetId);
                    $viewList = $result['views'] ?? [];
                    $success = "获取到 " . count($viewList) . " 个视图";
                }
                break;
            case 'delete_view':
                if ($docid && $sheetId && ($_POST['view_id'] ?? '')) {
                    $wx->smartsheet->deleteView($docid, $sheetId, $_POST['view_id']);
                    $success = "视图已删除";
                }
                break;
            case 'add_group':
                if ($docid && $sheetId) {
                    $wx->smartsheet->addGroup($docid, $sheetId, ['group_title' => $_POST['group_title'] ?? '新编组']);
                    $success = "编组创建成功";
                }
                break;
            case 'get_groups':
                if ($docid && $sheetId) {
                    $result = $wx->smartsheet->getGroup($docid, $sheetId);
                    $groupList = $result['groups'] ?? [];
                    $success = "获取到 " . count($groupList) . " 个编组";
                }
                break;
            case 'delete_group':
                if ($docid && $sheetId && ($_POST['group_id'] ?? '')) {
                    $wx->smartsheet->deleteGroup($docid, $sheetId, $_POST['group_id']);
                    $success = "编组已删除";
                }
                break;
            case 'modify_auth':
                if ($docid) {
                    $wx->smartsheet->modifyDocAuth($docid, ['type' => $_POST['auth_type'] ?? 'read_only']);
                    $success = "权限已更新";
                }
                break;
        }
        $tab = $_POST['tab'] ?? $tab;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

// ==================== 静态数据 ====================
$fieldTypes = [
    'FIELD_TYPE_TEXT' => '📝 文本', 'FIELD_TYPE_NUMBER' => '🔢 数字', 'FIELD_TYPE_CHECKBOX' => '☑ 复选框',
    'FIELD_TYPE_DATE_TIME' => '📅 日期', 'FIELD_TYPE_IMAGE' => '🖼 图片', 'FIELD_TYPE_ATTACHMENT' => '📎 附件',
    'FIELD_TYPE_USER' => '👤 成员', 'FIELD_TYPE_URL' => '🔗 链接', 'FIELD_TYPE_SELECT' => '🔘 单选',
    'FIELD_TYPE_MULTI_SELECT' => '☑ 多选', 'FIELD_TYPE_PROGRESS' => '📊 进度', 'FIELD_TYPE_PHONE_NUMBER' => '📞 电话',
    'FIELD_TYPE_EMAIL' => '📧 邮箱', 'FIELD_TYPE_CURRENCY' => '💰 货币', 'FIELD_TYPE_PERCENTAGE' => '📈 百分比',
    'FIELD_TYPE_LOCATION' => '📍 位置', 'FIELD_TYPE_AUTONUMBER' => '🔢 自动编号',
];
$viewTypes = [
    'VIEW_TYPE_GRID' => '📋 表格', 'VIEW_TYPE_KANBAN' => '📌 看板', 'VIEW_TYPE_GALLERY' => '🖼 画廊',
    'VIEW_TYPE_GANTT' => '📊 甘特', 'VIEW_TYPE_CALENDAR' => '📅 日历',
];
$tabs = [
    'docs' => ['fa-file-text-o', '文档管理'], 'sheets' => ['fa-table', '子表管理'],
    'records' => ['fa-list-alt', '记录管理'], 'fields' => ['fa-columns', '字段管理'],
    'views' => ['fa-eye', '视图管理'], 'groups' => ['fa-object-group', '编组管理'],
    'auth' => ['fa-lock', '权限管理'],
];
$activeTab = isset($tabs[$tab]) ? $tab : 'docs';

require __DIR__ . '/../../layout/header.php';
?>

<style>
.smart-tabs { border-bottom: 2px solid #e0e0e0; margin-bottom: 5px; display: flex; flex-wrap: wrap; }
.smart-tabs a { display: inline-block; padding: 10px 16px; color: #666; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; font-size: 13px; transition: all 0.2s; }
.smart-tabs a:hover { color: #2196f3; background: #f5f5f5; }
.smart-tabs a.active { color: #1565c0; border-bottom-color: #1565c0; font-weight: 600; }
.smart-tabs a i { margin-right: 6px; }
.context-bar { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 1px solid #dee2e6; border-radius: 6px; padding: 12px 16px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.context-bar label { margin: 0; font-weight: 600; font-size: 12px; color: #555; white-space: nowrap; }
.context-bar .form-control { width: 220px; display: inline-block; font-size: 12px; }
.context-bar .badge { font-size: 11px; }
.doc-info-card { background: #fff; border: 1px solid #e5e5e5; border-radius: 6px; padding: 12px 16px; margin-bottom: 10px; }
.doc-info-card .doc-name { font-size: 16px; font-weight: 600; }
.json-hint { font-family: 'Courier New', monospace; font-size: 11px; color: #888; background: #f5f5f5; padding: 8px 10px; border-radius: 3px; margin-top: 5px; max-height: 150px; overflow-y: auto; }
.data-table-wrap { max-height: 500px; overflow: auto; border: 1px solid #e5e5e5; border-radius: 4px; }
.data-table-wrap table { margin-bottom: 0; font-size: 12px; }
.record-card { border: 1px solid #e5e5e5; border-radius: 6px; padding: 12px; margin-bottom: 8px; background: #fafafa; }
.record-card .record-id { font-size: 10px; color: #999; }
.record-card .record-value { margin: 3px 8px; display: inline-block; }
.sheet-selector { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 10px; }
.sheet-selector .sheet-chip { padding: 5px 12px; border: 1px solid #ddd; border-radius: 16px; cursor: pointer; font-size: 12px; transition: all 0.15s; background: #fff; }
.sheet-selector .sheet-chip:hover { border-color: #2196f3; }
.sheet-selector .sheet-chip.active { background: #2196f3; color: #fff; border-color: #2196f3; }
</style>

<!-- Messages -->
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Tab 导航 -->
<div class="smart-tabs">
    <?php foreach ($tabs as $tKey => [$icon, $label]): ?>
    <a href="?tab=<?= $tKey ?>&docid=<?= urlencode($currentDocid) ?>&sheet_id=<?= urlencode($currentSheetId) ?>"
       class="<?= $activeTab === $tKey ? 'active' : '' ?>"><i class="fa <?= $icon ?>"></i><?= $label ?></a>
    <?php endforeach; ?>
</div>

<!-- 全局上下文栏 -->
<?php if (in_array($activeTab, ['sheets', 'records', 'fields', 'views', 'groups', 'auth'])): ?>
<div class="context-bar">
    <label><i class="fa fa-file-text-o"></i> 文档:</label>
    <input type="text" class="form-control input-sm" id="globalDocid" value="<?= htmlspecialchars($currentDocid) ?>" placeholder="docid" onchange="updateCtx()">
    <button class="btn btn-xs btn-default" onclick="copyText(getEl('globalDocid').value)" title="复制"><i class="fa fa-copy"></i></button>
    
    <?php if (in_array($activeTab, ['records', 'fields', 'views', 'groups'])): ?>
    <label style="margin-left:10px"><i class="fa fa-table"></i> 子表:</label>
    <input type="text" class="form-control input-sm" id="globalSheetId" value="<?= htmlspecialchars($currentSheetId) ?>" placeholder="sheet_id" onchange="updateCtx()" style="width:180px">
    <button class="btn btn-xs btn-default" onclick="copyText(getEl('globalSheetId').value)" title="复制"><i class="fa fa-copy"></i></button>
    <?php endif; ?>

    <?php if (!empty($sheetList)): ?>
    <span style="margin-left:10px;font-size:11px;color:#888">可用子表:</span>
    <select class="form-control input-sm" style="width:160px" onchange="getEl('globalSheetId').value=this.value;updateCtx()">
        <option value="">-- 选择 --</option>
        <?php foreach ($sheetList as $s): ?>
        <option value="<?= htmlspecialchars($s['sheet_id'] ?? '') ?>" <?= $currentSheetId===($s['sheet_id']??'')?'selected':'' ?>>
            <?= htmlspecialchars($s['title'] ?? $s['sheet_id']) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <?php if ($docInfo): ?>
    <span class="badge badge-info" style="margin-left:auto">📄 <?= htmlspecialchars($docInfo['doc_name'] ?? '') ?></span>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="portlet light"><div class="portlet-body">

<?php // ============================ TAB: 文档管理 ============================
if ($activeTab === 'docs'): ?>
    <?php if ($docInfo): ?>
    <div class="doc-info-card">
        <div class="row">
            <div class="col-md-8">
                <div class="doc-name"><i class="fa fa-file-text-o text-primary"></i> <?= htmlspecialchars($docInfo['doc_name'] ?? '') ?></div>
                <div style="font-size:12px;color:#888;margin-top:4px">
                    ID: <code><?= htmlspecialchars($currentDocid) ?></code> |
                    创建者: <?= htmlspecialchars($docInfo['creator'] ?? '-') ?> |
                    时间: <?= isset($docInfo['create_time']) ? date('Y-m-d H:i', (int)$docInfo['create_time']) : '-' ?>
                </div>
            </div>
            <div class="col-md-4 text-right">
                <button class="btn btn-xs btn-warning" onclick="renameDoc()"><i class="fa fa-edit"></i> 重命名</button>
                <button class="btn btn-xs btn-danger" onclick="deleteDoc()"><i class="fa fa-trash"></i> 删除</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 已缓存的文档（可选择，无需输入docid） -->
    <?php if (!empty($cachedDocs)): ?>
    <div class="portlet light">
        <div class="portlet-title">
            <div class="caption"><i class="fa fa-history"></i> <span class="caption-subject bold uppercase">已有文档（点击选用）</span></div>
            <div class="actions"><span class="text-muted" style="font-size:11px">共 <?= count($cachedDocs) ?> 个文档</span></div>
        </div>
        <div class="portlet-body" style="padding:10px 15px">
            <div style="display:flex;flex-wrap:wrap;gap:8px">
                <?php foreach ($cachedDocs as $doc): ?>
                <div class="cached-doc-chip" style="display:inline-flex;align-items:center;padding:6px 12px;background:#fff;border:1px solid #ddd;border-radius:20px;cursor:pointer;font-size:13px;transition:all 0.15s"
                     onclick="selectCachedDoc('<?= htmlspecialchars($doc['docid']) ?>','<?= htmlspecialchars($doc['name'] ?? '') ?>')"
                     title="点击选用此文档">
                    <i class="fa fa-file-text-o text-primary" style="margin-right:6px"></i>
                    <span><?= htmlspecialchars(mb_strlen($doc['name']??'')>20 ? mb_substr($doc['name'],0,18).'..' : ($doc['name']??'未知')) ?></span>
                    <code style="font-size:9px;color:#999;margin-left:6px"><?= htmlspecialchars(substr($doc['docid'],-8)) ?></code>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- 查询/输入文档ID -->
        <div class="col-md-6">
            <div class="portlet" style="border:1px solid #e5e5e5;border-radius:4px;padding:15px;">
                <h4><i class="fa fa-search text-primary"></i> 查询文档（输入 docid）</h4>
                <form method="post" onsubmit="syncInputs(this)">
                    <input type="hidden" name="action" value="get_doc_info">
                    <input type="hidden" name="tab" value="docs">
                    <input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>">
                    <input type="text" name="docid_display" id="docidDisplay" class="form-control input-sm" placeholder="输入文档 docid" 
                           value="<?= htmlspecialchars($currentDocid) ?>" 
                           onchange="this.form.querySelector('.ctx-docid').value=this.value"
                           style="margin-bottom:8px">
                    <button class="btn btn-sm btn-primary"><i class="fa fa-search"></i> 查询文档信息</button>
                    <span class="text-muted" style="font-size:11px;margin-left:5px">也可直接粘贴 docid</span>
                </form>
            </div>
        </div>

        <!-- 新建智能表格 -->
        <div class="col-md-6">
            <div class="portlet" style="border:1px solid #e5e5e5;border-radius:4px;padding:15px;">
                <h4><i class="fa fa-plus-circle text-success"></i> 新建智能表格</h4>
                <form method="post">
                    <input type="hidden" name="action" value="create_doc">
                    <input type="hidden" name="tab" value="docs">
                    <div class="row">
                        <div class="col-md-7">
                            <input type="text" name="doc_name" class="form-control input-sm" placeholder="文档名称" required>
                        </div>
                        <div class="col-md-5">
                            <button class="btn btn-sm btn-success btn-block"><i class="fa fa-plus"></i> 创建</button>
                        </div>
                    </div>
                    <?php if (!empty($smartsheetTemplates)): ?>
                    <div style="margin-top:6px">
                        <select name="template_id" class="form-control input-sm" onchange="onTplSelect(this)">
                            <option value="">--- 从模板创建（可选） ---</option>
                            <?php foreach ($smartsheetTemplates as $tid => $tpl): ?>
                            <option value="<?= $tid ?>" data-desc="<?= htmlspecialchars($tpl['desc']) ?>"
                                data-sheets="<?= count($tpl['sheets']) ?>"
                                data-fields="<?= array_sum(array_map(fn($s) => count($s['fields']), $tpl['sheets'])) ?>">
                                <?= $tpl['icon'] ?> <?= htmlspecialchars($tpl['name']) ?> — <?= htmlspecialchars($tpl['desc']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="tplHint" style="font-size:11px;color:#888;margin-top:3px"></div>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top:6px">
                        <a href="#" onclick="$j(this).next().toggle();return false" style="font-size:11px"><i class="fa fa-caret-down"></i> 高级选项</a>
                        <div style="display:none;margin-top:6px">
                            <div class="row">
                                <div class="col-md-6"><input type="text" name="space_id" class="form-control input-sm" placeholder="空间ID"></div>
                                <div class="col-md-6"><input type="text" name="father_id" class="form-control input-sm" placeholder="父目录ID"></div>
                            </div>
                            <div style="margin-top:6px">
                                <label style="font-size:11px">文档管理员:</label>
                                <ul class="nav nav-tabs" style="margin-bottom:4px;font-size:11px">
                                    <li class="active"><a href="#du-user" data-toggle="tab">成员</a></li>
                                    <li><a href="#du-dept" data-toggle="tab">部门</a></li>
                                    <li><a href="#du-tag" data-toggle="tab">标签</a></li>
                                </ul>
                                <div class="tab-content">
                                    <div class="tab-pane active" id="du-user">
                                        <input type="text" class="form-control input-sm" id="auSearch" placeholder="🔍 搜索..." style="margin-bottom:4px">
                                        <div id="auList" style="max-height:120px;overflow-y:auto;border:1px solid #ddd;padding:6px;font-size:11px">
                                            <?php foreach (array_slice($members,0,50) as $m): ?>
                                            <label style="display:block;cursor:pointer"><input type="checkbox" class="au-check" value="<?= htmlspecialchars($m['userid']) ?>" data-n="<?= htmlspecialchars($m['name']) ?>"> <?= htmlspecialchars($m['name']) ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="tab-pane" id="du-dept" style="max-height:120px;overflow-y:auto;font-size:11px">
                                        <?php foreach ($departments as $d): ?>
                                        <label style="display:block"><input type="checkbox" class="au-dept-check" value="<?= $d['id'] ?>" data-dn="<?= htmlspecialchars($d['name']) ?>"> <?= htmlspecialchars($d['name']) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="tab-pane" id="du-tag" style="max-height:120px;overflow-y:auto;font-size:11px">
                                        <?php foreach ($tags as $t): ?>
                                        <label style="display:block"><input type="checkbox" class="au-tag-check" value="<?= $t['tagid'] ?>" data-tn="<?= htmlspecialchars($t['tagname']) ?>"> <?= htmlspecialchars($t['tagname']) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div id="auSel" style="margin-top:4px;min-height:28px;padding:6px 8px;background:#f8f9fa;border:1px dashed #dee2e6;border-radius:6px;font-size:13px;line-height:2"></div>
                                <div style="margin-top:4px;display:flex;gap:6px;align-items:center">
                                    <span style="font-size:11px;color:#888" id="auCount"></span>
                                    <button type="button" class="btn btn-xs btn-danger" onclick="clearAllAu()" style="display:none" id="auClearBtn"><i class="fa fa-trash"></i> 清除全部</button>
                                </div>
                                <input type="hidden" name="admin_users" id="auInput" value="">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 从微盘浏览（可按成员辅助填写 space_id） -->
    <div class="row" style="margin-top:10px">
        <div class="col-md-12">
            <div class="portlet" style="border:1px solid #e5e5e5;padding:15px;">
                <h4><i class="fa fa-folder-open text-info"></i> 从微盘浏览文档
                    <small class="text-muted">（输入空间ID和目录ID）</small>
                </h4>
                <div class="alert alert-default" style="background:#f9f9f9;font-size:12px;margin-bottom:10px">
                    <i class="fa fa-lightbulb-o"></i> 
                    <strong>提示：</strong>如果不知道空间ID，可以先选择一位成员，系统会尝试使用该成员的 userid 作为空间标识进行查询。
                    或者先在「已有文档」中选择文档，然后在其他 Tab 操作。
                </div>
                <form method="post" class="form-inline" style="margin-bottom:10px">
                    <input type="hidden" name="action" value="get_files"><input type="hidden" name="tab" value="docs">
                    <select class="form-control input-sm" id="memberSpaceHelper" onchange="fillSpaceFromMember(this.value)" style="width:180px;margin-right:6px">
                        <option value="">-- 按成员辅助填写 --</option>
                        <?php foreach ($members as $m): ?>
                        <option value="<?= htmlspecialchars($m['userid']) ?>"><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="space_id" id="weworkSpaceId" class="form-control input-sm" placeholder="空间 ID" required style="width:180px">
                    <input type="text" name="father_id" class="form-control input-sm" placeholder="目录ID (可选)" style="width:180px">
                    <input type="hidden" name="start" value="0">
                    <button class="btn btn-sm btn-info"><i class="fa fa-search"></i> 浏览</button>
                </form>
                    <button class="btn btn-sm btn-info"><i class="fa fa-search"></i> 浏览</button>
                </form>
                <?php if (!empty($fileList)): ?>
                <div class="data-table-wrap">
                    <table class="table table-striped table-hover">
                        <thead><tr><th>文件名</th><th>类型</th><th>大小</th><th>修改时间</th><th>操作</th></tr></thead>
                        <tbody>
                            <?php foreach ($fileList as $f): ?>
                            <tr>
                                <td><i class="fa <?= ($f['file_type']??0)==1?'fa-folder text-warning':'fa-file-text-o text-info' ?>"></i>
                                    <?= htmlspecialchars($f['file_name'] ?? '') ?></td>
                                <td><?= [1=>'📁文件夹',2=>'📄文件',3=>'📝文档',4=>'📊表格',5=>'📋收集表',10=>'🧮智能表'][$f['file_type']??0] ?? '?' ?></td>
                                <td><?= number_format(($f['file_size']??0)/1024,1) ?>KB</td>
                                <td style="font-size:11px"><?= date('m-d H:i', $f['mtime']??0) ?></td>
                                <td>
                                    <?php if (in_array($f['file_type']??0, [3,4,10])): ?>
                                    <a href="?tab=sheets&docid=<?= urlencode($f['fileid']??'') ?>" class="btn btn-xs btn-primary">选择</a>
                                    <?php endif; ?>
                                    <?php if (($f['file_type']??0)==1): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="action" value="get_files"><input type="hidden" name="tab" value="docs">
                                        <input type="hidden" name="space_id" value="<?= htmlspecialchars($_POST['space_id']??'') ?>">
                                        <input type="hidden" name="father_id" value="<?= htmlspecialchars($f['fileid']??'') ?>">
                                        <button class="btn btn-xs btn-default">📂 进入</button>
                                    </form>
                                    <?php endif; ?>
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

    <!-- 重命名/删除 Modals -->
    <div class="modal fade" id="renameModal"><div class="modal-dialog modal-sm"><div class="modal-content">
        <form method="post"><div class="modal-header"><h4>重命名文档</h4></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="rename_doc"><input type="hidden" name="tab" value="docs">
                <input type="hidden" name="docid" value="<?= htmlspecialchars($currentDocid) ?>">
                <input type="text" name="new_name" class="form-control" value="<?= htmlspecialchars($docInfo['doc_name']??'') ?>" required>
            </div>
            <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">取消</button><button class="btn btn-primary">确认</button></div>
    </form></div></div></div>

    <div class="modal fade" id="deleteModal"><div class="modal-dialog modal-sm"><div class="modal-content">
        <form method="post" onsubmit="return this.confirm_name.value===this.doc_name_confirm.value">
            <div class="modal-header"><h4 class="text-danger">⚠ 删除文档</h4></div>
            <div class="modal-body">
                <input type="hidden" name="action" value="delete_doc"><input type="hidden" name="tab" value="docs">
                <input type="hidden" name="docid" value="<?= htmlspecialchars($currentDocid) ?>">
                <p>此操作不可撤销！请输入文档名称确认：</p>
                <p><strong><?= htmlspecialchars($docInfo['doc_name']??$currentDocid) ?></strong></p>
                <input type="hidden" name="doc_name_confirm" value="<?= htmlspecialchars($docInfo['doc_name']??$currentDocid) ?>">
                <input type="text" name="confirm_name" class="form-control" placeholder="输入上方名称确认" required>
            </div>
            <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">取消</button><button class="btn btn-danger">确认删除</button></div>
    </form></div></div></div>

<?php // ============================ TAB: 子表管理 ============================
elseif ($activeTab === 'sheets'): ?>
    <h4><i class="fa fa-table"></i> 子表管理</h4>
    <?php if (!empty($sheetList)): ?>
    <div class="sheet-selector">
        <?php foreach ($sheetList as $s): ?>
        <div class="sheet-chip <?= $currentSheetId===($s['sheet_id']??'')?'active':'' ?>" 
             onclick="getEl('globalSheetId').value='<?= htmlspecialchars($s['sheet_id']??'') ?>';updateCtx();location.href='?tab=records&docid=<?= urlencode($currentDocid) ?>&sheet_id=<?= urlencode($s['sheet_id']??'') ?>'">
            📊 <?= htmlspecialchars($s['title'] ?? $s['sheet_id']) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-plus-circle text-success"></i> 添加子表</h5>
            <form method="post" onsubmit="syncInputs(this)"><input type="hidden" name="action" value="add_sheet"><input type="hidden" name="tab" value="sheets"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>">
                <input type="text" name="sheet_title" class="form-control input-sm" placeholder="子表名称" required>
                <button class="btn btn-sm btn-success" style="margin-top:6px"><i class="fa fa-plus"></i> 创建</button>
        </form></div></div>
        <div class="col-md-4"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-search text-primary"></i> 查询子表</h5>
            <form method="post" onsubmit="syncInputs(this)"><input type="hidden" name="action" value="get_sheets"><input type="hidden" name="tab" value="sheets"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>">
                <button class="btn btn-sm btn-primary btn-block"><i class="fa fa-search"></i> 查询所有子表</button>
        </form></div></div>
        <div class="col-md-4"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-edit text-warning"></i> 重命名/删除</h5>
            <form method="post" onsubmit="syncInputs(this)" style="margin-bottom:8px"><input type="hidden" name="action" value="update_sheet"><input type="hidden" name="tab" value="sheets"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>">
                <input type="text" name="sheet_id" class="form-control input-sm" placeholder="sheet_id" required style="margin-bottom:4px">
                <input type="text" name="sheet_title" class="form-control input-sm" placeholder="新名称" required>
                <button class="btn btn-sm btn-warning" style="margin-top:4px"><i class="fa fa-save"></i> 重命名</button>
            </form>
            <form method="post" onsubmit="syncInputs(this);return confirm('确定删除？')"><input type="hidden" name="action" value="delete_sheet"><input type="hidden" name="tab" value="sheets"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>">
                <input type="text" name="sheet_id" class="form-control input-sm" placeholder="sheet_id" required>
                <button class="btn btn-sm btn-danger" style="margin-top:4px"><i class="fa fa-trash"></i> 删除</button>
        </form></div></div>
    </div>

    <?php if (!empty($sheetList)): ?>
    <div class="data-table-wrap"><table class="table table-striped table-hover">
        <thead><tr><th>Sheet ID</th><th>标题</th><th>类型</th><th>可见</th><th>操作</th></tr></thead>
        <tbody><?php foreach ($sheetList as $s): ?>
            <tr><td><code><?= htmlspecialchars($s['sheet_id']??'') ?></code></td>
                <td><strong><?= htmlspecialchars($s['title']??'') ?></strong></td>
                <td><?= $s['type']??'-' ?></td><td><?= ($s['is_visible']??true)?'✅':'❌' ?></td>
                <td><a href="?tab=records&docid=<?= urlencode($currentDocid) ?>&sheet_id=<?= urlencode($s['sheet_id']??'') ?>" class="btn btn-xs btn-info">查看记录</a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>

<?php // ============================ TAB: 记录管理 ============================
elseif ($activeTab === 'records'): ?>
    <h4><i class="fa fa-list-alt"></i> 记录管理 
        <?php if ($recordTotal>0): ?><small class="text-muted">(共 <?= $recordTotal ?> 条)</small><?php endif ?></h4>

    <div class="row">
        <!-- 查询 -->
        <div class="col-md-3"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-search text-primary"></i> 查询记录</h5>
            <form method="post" onsubmit="syncInputs(this)"><input type="hidden" name="action" value="query_records"><input type="hidden" name="tab" value="records"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <label>偏移</label><input type="number" name="offset" class="form-control input-sm" value="<?= $recordOffset ?>" min="0">
                <label>条数</label><input type="number" name="limit" class="form-control input-sm" value="100" min="1" max="500">
                <button class="btn btn-sm btn-primary btn-block" style="margin-top:8px"><i class="fa fa-search"></i> 查询</button>
                <?php if ($recordTotal > 100): ?>
                <div style="margin-top:6px;font-size:11px">
                    <?php if ($recordOffset >= 100): ?><a href="#" onclick="document.querySelector('[name=offset]').value=<?= max(0,$recordOffset-100) ?>;this.closest('form').submit();return false">← 上一页</a><?php endif ?>
                    <?php if ($recordOffset+100 < $recordTotal): ?><a href="#" onclick="document.querySelector('[name=offset]').value=<?= $recordOffset+100 ?>;this.closest('form').submit();return false" style="float:right">下一页 →</a><?php endif ?>
                </div>
                <?php endif ?>
        </form></div></div>

        <!-- 添加 -->
        <div class="col-md-5"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-plus-circle text-success"></i> 添加记录</h5>
            <form method="post" onsubmit="syncInputs(this)"><input type="hidden" name="action" value="add_record"><input type="hidden" name="tab" value="records"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <textarea name="record_json" class="form-control" rows="3" required
                    placeholder='{"values": {"姓名": [{"type":"text","text":"张三"}]}}'></textarea>
                <div class="json-hint">
<b>JSON 格式参考：</b><br>
文本: <code>{"type":"text","text":"内容"}</code><br>
数字: <code>{"type":"number","number":123}</code><br>
日期: <code>{"type":"date_time","date_time":"2026-06-27"}</code><br>
链接: <code>{"type":"url","link":"https://...","text":"显示文字"}</code><br>
成员: <code>{"type":"user","user_id":"userid"}</code><br>
图片: <code>{"type":"image","image_url":"https://..."}</code><br>
附件: <code>{"type":"attachment","file_id":"xxx","name":"文件.pdf"}</code>
                </div>
                <button class="btn btn-sm btn-success" style="margin-top:6px"><i class="fa fa-plus"></i> 添加</button>
                <button type="button" class="btn btn-sm btn-default" style="margin-top:6px" onclick="fillRecordTemplate()"><i class="fa fa-magic"></i> 填入模板</button>
        </form></div></div>

        <!-- 更新/删除 -->
        <div class="col-md-4"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-edit text-warning"></i> 更新记录</h5>
            <form method="post" onsubmit="syncInputs(this)"><input type="hidden" name="action" value="update_record"><input type="hidden" name="tab" value="records"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <textarea name="record_json" class="form-control" rows="2" required
                    placeholder='{"record_id":"rpxxx","values":{"字段":[{"type":"text","text":"新值"}]}}'></textarea>
                <button class="btn btn-sm btn-warning" style="margin-top:4px"><i class="fa fa-save"></i> 更新</button>
            </form>
            <hr style="margin:8px 0">
            <h5><i class="fa fa-trash text-danger"></i> 删除记录</h5>
            <form method="post" onsubmit="syncInputs(this);return confirm('确定删除？')"><input type="hidden" name="action" value="delete_record"><input type="hidden" name="tab" value="records"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <input type="text" name="record_ids" class="form-control input-sm" placeholder="record_id,逗号分隔" required>
                <button class="btn btn-sm btn-danger" style="margin-top:4px"><i class="fa fa-trash"></i> 删除</button>
        </form></div></div>
    </div>

    <?php if (!empty($records)): ?>
    <div class="data-table-wrap"><table class="table table-striped table-hover">
        <thead><tr><th>Record ID</th><th>字段内容</th><th>创建/更新</th><th>创建者</th><th width="60">操作</th></tr></thead>
        <tbody><?php foreach ($records as $r): ?>
            <tr><td><code style="font-size:10px"><?= htmlspecialchars($r['record_id']??'') ?></code></td>
                <td><?php foreach ($r['values']??[] as $key=>$vals):
                    if(is_array($vals)) foreach($vals as $v) echo '<span class="label label-default" style="margin:1px;font-size:10px">'.htmlspecialchars($key).': '.htmlspecialchars($v['text']??$v['number']??'').'</span> ';
                endforeach; ?></td>
                <td style="font-size:10px"><?= isset($r['create_time'])?date('m-d H:i',strtotime($r['create_time'])):'-' ?><br><?= isset($r['update_time'])?date('m-d H:i',strtotime($r['update_time'])):'-' ?></td>
                <td style="font-size:11px"><?= htmlspecialchars($r['creator_name']??'-') ?></td>
                <td><a href="#" onclick="copyText('<?= htmlspecialchars($r['record_id']??'') ?>');return false" class="btn btn-xs btn-default"><i class="fa fa-copy"></i></a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>

<?php // ============================ TAB: 字段管理 ============================
elseif ($activeTab === 'fields'): ?>
    <h4><i class="fa fa-columns"></i> 字段管理</h4>
    <div class="row">
        <div class="col-md-4"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-plus-circle text-success"></i> 添加字段</h5>
            <form method="post" onsubmit="syncInputs(this)"><input type="hidden" name="action" value="add_field"><input type="hidden" name="tab" value="fields"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <input type="text" name="field_title" class="form-control input-sm" placeholder="字段标题" required>
                <select name="field_type" class="form-control input-sm" style="margin-top:4px">
                    <?php foreach ($fieldTypes as $val=>$label): ?>
                    <option value="<?= $val ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-success" style="margin-top:4px"><i class="fa fa-plus"></i> 添加</button>
        </form></div></div>
        <div class="col-md-3"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-search text-primary"></i> 查询字段</h5>
            <form method="post" onsubmit="syncInputs(this)"><input type="hidden" name="action" value="get_fields"><input type="hidden" name="tab" value="fields"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <button class="btn btn-sm btn-primary btn-block"><i class="fa fa-search"></i> 查询所有字段</button>
        </form></div></div>
        <div class="col-md-5"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-edit text-warning"></i> 更新/删除字段</h5>
            <form method="post" onsubmit="syncInputs(this)" style="margin-bottom:6px"><input type="hidden" name="action" value="update_field"><input type="hidden" name="tab" value="fields"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <div class="row"><div class="col-md-5"><input type="text" name="field_id" class="form-control input-sm" placeholder="field_id" required></div>
                <div class="col-md-4"><input type="text" name="field_title" class="form-control input-sm" placeholder="新标题"></div>
                <div class="col-md-3"><button class="btn btn-sm btn-warning btn-block">更新</button></div></div>
            </form>
            <form method="post" onsubmit="syncInputs(this);return confirm('确定删除？')"><input type="hidden" name="action" value="delete_field"><input type="hidden" name="tab" value="fields"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <input type="text" name="field_ids" class="form-control input-sm" placeholder="field_id,逗号分隔" required>
                <button class="btn btn-sm btn-danger" style="margin-top:3px"><i class="fa fa-trash"></i> 删除</button>
        </form></div></div>
    </div>
    <?php if (!empty($fieldList)): ?>
    <div class="data-table-wrap"><table class="table table-striped table-hover">
        <thead><tr><th>Field ID</th><th>标题</th><th>类型</th><th>属性</th><th>操作</th></tr></thead>
        <tbody><?php foreach ($fieldList as $f): ?>
            <tr><td><code style="font-size:10px"><?= htmlspecialchars($f['field_id']??'') ?></code></td>
                <td><strong><?= htmlspecialchars($f['field_title']??'') ?></strong></td>
                <td><?= $fieldTypes[$f['field_type']??''] ?? $f['field_type']??'-' ?></td>
                <td style="font-size:10px"><?= ($f['is_primary']??false)?'🔑主键 ':'' ?><?= ($f['is_required']??false)?'⚠必填 ':'' ?><?= isset($f['is_visible'])&&!$f['is_visible']?'👻隐藏':'' ?></td>
                <td><a href="#" onclick="copyText('<?= htmlspecialchars($f['field_id']??'') ?>');return false" class="btn btn-xs btn-default"><i class="fa fa-copy"></i></a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>

<?php // ============================ TAB: 视图管理 ============================
elseif ($activeTab === 'views'): ?>
    <h4><i class="fa fa-eye"></i> 视图管理</h4>
    <div class="row">
        <div class="col-md-4"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-plus-circle text-success"></i> 添加视图</h5>
            <form method="post" onsubmit="syncInputs(this)"><input type="hidden" name="action" value="add_view"><input type="hidden" name="tab" value="views"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <input type="text" name="view_title" class="form-control input-sm" value="新视图" required>
                <select name="view_type" class="form-control input-sm" style="margin-top:4px">
                    <?php foreach ($viewTypes as $val=>$label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-success" style="margin-top:4px"><i class="fa fa-plus"></i> 添加</button>
        </form></div></div>
        <div class="col-md-4"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-search text-primary"></i> 查询视图</h5>
            <form method="post" onsubmit="syncInputs(this)"><input type="hidden" name="action" value="get_views"><input type="hidden" name="tab" value="views"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <button class="btn btn-sm btn-primary btn-block"><i class="fa fa-search"></i> 查询</button>
        </form></div></div>
        <div class="col-md-4"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-trash text-danger"></i> 删除视图</h5>
            <form method="post" onsubmit="syncInputs(this);return confirm('确定删除？')"><input type="hidden" name="action" value="delete_view"><input type="hidden" name="tab" value="views"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <input type="text" name="view_id" class="form-control input-sm" placeholder="view_id" required>
                <button class="btn btn-sm btn-danger" style="margin-top:4px"><i class="fa fa-trash"></i> 删除</button>
        </form></div></div>
    </div>
    <?php if (!empty($viewList)): ?>
    <div class="data-table-wrap"><table class="table table-striped table-hover">
        <thead><tr><th>View ID</th><th>标题</th><th>类型</th><th>公开等级</th><th>操作</th></tr></thead>
        <tbody><?php foreach ($viewList as $v): ?>
            <tr><td><code style="font-size:10px"><?= htmlspecialchars($v['view_id']??'') ?></code></td>
                <td><strong><?= htmlspecialchars($v['view_title']??'') ?></strong></td>
                <td><?= $viewTypes[$v['view_type']??''] ?? '-' ?></td>
                <td><?= ['PUBLIC'=>'公开','LOCK'=>'锁定','PRIVATE'=>'私有'][$v['public_level']??'']??'-' ?></td>
                <td><a href="#" onclick="copyText('<?= htmlspecialchars($v['view_id']??'') ?>');return false" class="btn btn-xs btn-default"><i class="fa fa-copy"></i></a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>

<?php // ============================ TAB: 编组/权限 ============================
elseif ($activeTab === 'groups'): ?>
    <h4><i class="fa fa-object-group"></i> 编组管理</h4>
    <div class="row">
        <div class="col-md-4"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-plus-circle text-success"></i> 添加编组</h5>
            <form method="post" onsubmit="syncInputs(this)"><input type="hidden" name="action" value="add_group"><input type="hidden" name="tab" value="groups"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <input type="text" name="group_title" class="form-control input-sm" value="新编组" required>
                <button class="btn btn-sm btn-success" style="margin-top:4px"><i class="fa fa-plus"></i> 添加</button>
        </form></div></div>
        <div class="col-md-4"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-search text-primary"></i> 查询编组</h5>
            <form method="post" onsubmit="syncInputs(this)"><input type="hidden" name="action" value="get_groups"><input type="hidden" name="tab" value="groups"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <button class="btn btn-sm btn-primary btn-block"><i class="fa fa-search"></i> 查询</button>
        </form></div></div>
        <div class="col-md-4"><div class="portlet" style="border:1px solid #e5e5e5;padding:12px">
            <h5><i class="fa fa-trash text-danger"></i> 删除编组</h5>
            <form method="post" onsubmit="syncInputs(this);return confirm('确定删除？')"><input type="hidden" name="action" value="delete_group"><input type="hidden" name="tab" value="groups"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>"><input type="hidden" name="sheet_id" class="ctx-sheetid" value="<?= htmlspecialchars($currentSheetId) ?>">
                <input type="text" name="group_id" class="form-control input-sm" placeholder="group_id" required>
                <button class="btn btn-sm btn-danger" style="margin-top:4px"><i class="fa fa-trash"></i> 删除</button>
        </form></div></div>
    </div>
    <?php if (!empty($groupList)): ?>
    <div class="data-table-wrap"><table class="table table-striped table-hover">
        <thead><tr><th>Group ID</th><th>标题</th><th>字段ID</th><th>操作</th></tr></thead>
        <tbody><?php foreach ($groupList as $g): ?>
            <tr><td><code><?= htmlspecialchars($g['group_id']??'') ?></code></td>
                <td><strong><?= htmlspecialchars($g['group_title']??'') ?></strong></td>
                <td><?= htmlspecialchars($g['field_id']??'-') ?></td>
                <td><a href="#" onclick="copyText('<?= htmlspecialchars($g['group_id']??'') ?>');return false" class="btn btn-xs btn-default"><i class="fa fa-copy"></i></a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>

<?php elseif ($activeTab === 'auth'): ?>
    <h4><i class="fa fa-lock"></i> 权限管理</h4>
    <div class="row">
        <div class="col-md-6"><div class="portlet" style="border:1px solid #e5e5e5;padding:15px">
            <h5>设置文档权限</h5>
            <form method="post" onsubmit="syncInputs(this)"><input type="hidden" name="action" value="modify_auth"><input type="hidden" name="tab" value="auth"><input type="hidden" name="docid" class="ctx-docid" value="<?= htmlspecialchars($currentDocid) ?>">
                <select name="auth_type" class="form-control"><option value="read_only">🔒 只读 (read_only)</option><option value="read_write">✏ 可读写 (read_write)</option></select>
                <button class="btn btn-primary" style="margin-top:8px"><i class="fa fa-save"></i> 应用权限</button>
        </form></div></div>
        <div class="col-md-6"><div class="portlet" style="border:1px solid #e5e5e5;padding:15px;font-size:13px">
            <h5>权限说明</h5>
            <ul><li><strong>只读</strong>：成员只能查看，不能编辑</li><li><strong>可读写</strong>：成员可自由编辑</li><li>更细粒度权限请在企业微信客户端设置</li></ul>
        </div></div>
    </div>
<?php endif; ?>

    </div></div><!-- /portlet -->

<script>
var $j = typeof jQuery !== 'undefined' ? jQuery : null;
function getEl(id) { return document.getElementById(id); }
function copyText(t) {
    if (!t) return;
    if (navigator.clipboard) navigator.clipboard.writeText(t);
    else { var ta=document.createElement('textarea');ta.value=t;ta.style.cssText='position:fixed;left:-9999px';document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta); }
}

// 同步全局上下文
function syncInputs(f) {
    var d = getEl('globalDocid'), s = getEl('globalSheetId');
    f.querySelectorAll('.ctx-docid').forEach(function(h){ if(d) h.value = d.value; });
    f.querySelectorAll('.ctx-sheetid').forEach(function(h){ if(s) h.value = s.value; });
}
function updateCtx() {
    var d = getEl('globalDocid')?.value || '', s = getEl('globalSheetId')?.value || '';
    document.querySelectorAll('.smart-tabs a').forEach(function(a){
        var u = new URL(a.href, location.origin);
        if(d) u.searchParams.set('docid', d); else u.searchParams.delete('docid');
        if(s) u.searchParams.set('sheet_id', s); else u.searchParams.delete('sheet_id');
        a.href = u.toString();
    });
}

// 重命名/删除弹窗
function renameDoc() { if($j) $j('#renameModal').modal('show'); }
function deleteDoc() { if($j) $j('#deleteModal').modal('show'); }

// 选择已缓存的文档
function selectCachedDoc(docid, docName) {
    var el = getEl('globalDocid'); if(el) el.value = docid;
    var display = getEl('docidDisplay'); if(display) display.value = docid;
    updateCtx();
    // 跳转到子表管理
    location.href = '?tab=sheets&docid=' + encodeURIComponent(docid);
}

// 按成员辅助填写空间ID
function fillSpaceFromMember(userid) {
    if (!userid) return;
    var spaceEl = getEl('weworkSpaceId');
    // 提示：微盘空间ID通常需要从微盘管理后台获取
    // 这里填入 userid 作为一个常见模式的值
    if (spaceEl && !spaceEl.value) {
        // 企业微信中，用户级微盘空间的 spaceid 有特定格式
        // 尝试用 userid 作为 space_id 的候选值
        spaceEl.value = userid;
    }
}

// 记录模板
function fillRecordTemplate() {
    var ta = document.querySelector('textarea[name=record_json]');
    if (ta) ta.value = '{"values": {\n  "示例字段": [{"type": "text", "text": "示例内容"}]\n}}';
}

// 管理员选择器
(function(){
    var s = getEl('auSearch'); if(!s) return;
    s.addEventListener('input', function(){
        var kw = this.value.toLowerCase();
        document.querySelectorAll('#auList label').forEach(function(l){
            l.style.display = (l.textContent.toLowerCase().includes(kw)) ? '' : 'none';
        });
    });
    document.querySelectorAll('.au-check,.au-dept-check,.au-tag-check').forEach(function(cb){
        cb.addEventListener('change', updateAuSel);
    });
    function updateAuSel() {
        var items=[];
        document.querySelectorAll('.au-check:checked').forEach(function(c){ items.push({v:c.value, n:c.dataset.n, cls:'au-check'}); });
        document.querySelectorAll('.au-dept-check:checked').forEach(function(c){ items.push({v:c.value, n:'🏢 '+c.dataset.dn, cls:'au-dept-check'}); });
        document.querySelectorAll('.au-tag-check:checked').forEach(function(c){ items.push({v:c.value, n:'🏷 '+c.dataset.tn, cls:'au-tag-check'}); });
        var d = getEl('auSel'), inp = getEl('auInput'), clearBtn = document.getElementById('auClearBtn'), cntEl = document.getElementById('auCount');
        if (d) {
            if (items.length === 0) {
                d.innerHTML = '<span style="color:#bbb">尚未选择管理员 — 在上方列表中勾选</span>';
                if (clearBtn) clearBtn.style.display = 'none';
                if (cntEl) cntEl.textContent = '';
            } else {
                var h = '<div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">';
                items.forEach(function(it){
                    h += '<span class="au-tag" data-cls="'+it.cls+'" data-val="'+it.v+'" ';
                    h += 'style="display:inline-flex;align-items:center;gap:4px;padding:3px 4px 3px 10px;background:#1976d2;color:#fff;border-radius:14px;font-size:12px">';
                    h += it.n;
                    h += '<b class="au-tag-del" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:rgba(255,255,255,0.2);color:#fff;font-size:13px;cursor:pointer;line-height:1;transition:background 0.15s" '; 
                    h += 'title="移除" onmouseover="this.style.background=\'rgba(255,255,255,0.4)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.2)\'"';
                    h += 'onclick="removeAuItem(this)">✕</b>';
                    h += '</span>';
                });
                h += '</div>';
                d.innerHTML = h;
                if (clearBtn) clearBtn.style.display = '';
                if (cntEl) cntEl.textContent = '已选 ' + items.length + ' 项';
            }
        }
        if (inp) inp.value = items.map(function(it){ return it.v; }).join(',');
    }
    // 删除单个
    window.removeAuItem = function(el) {
        var tag = el.closest('.au-tag');
        if (!tag) return;
        var val = tag.dataset.val, cls = tag.dataset.cls;
        var cb = document.querySelector('.' + cls + '[value="' + val + '"]');
        if (cb) { cb.checked = false; updateAuSel(); }
    };
    // 清除全部
    window.clearAllAu = function() {
        var checked = document.querySelectorAll('.au-check:checked,.au-dept-check:checked,.au-tag-check:checked');
        if (checked.length === 0) return;
        if (!confirm('确定清除所有已选管理员？')) return;
        checked.forEach(function(cb){ cb.checked = false; });
        updateAuSel();
    };
    // 模板选择提示
    window.onTplSelect = function(sel) {
        var opt = sel.options[sel.selectedIndex];
        var hint = document.getElementById('tplHint');
        if (!hint) return;
        if (opt.value && opt.dataset.sheets) {
            var docName = sel.closest('form').querySelector('[name=doc_name]');
            if (docName && !docName.value) {
                docName.value = opt.text.split('—')[0].trim() || (opt.text.split('–')[0].trim());
            }
            hint.innerHTML = '📋 ' + opt.dataset.sheets + ' 个子表，' + opt.dataset.fields + ' 个字段 — ' + opt.dataset.desc;
        } else {
            hint.innerHTML = '选择模板后，创建文档时将自动建立子表和字段';
        }
    };
})();
</script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

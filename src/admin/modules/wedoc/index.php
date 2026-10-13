<?php
/**
 * 管理后台 - 文档管理
 * 
 * 功能：
 *   1. 📄 文档查询 — 按 docid 查询文档基本信息，查看文档列表
 *   2. ✏️ 文档创建 — 创建文档/智能表格
 *   3. 🔐 权限管理 — 查看/设置文档权限
 *   4. 🔗 分享管理 — 创建/查询分享链接
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '文档管理';
$error = ''; $success = '';
$tab = $_GET['tab'] ?? $_POST['tab'] ?? 'query';

$docInfo = null;
$docList = [];
$docAuth = null;
$shareResult = null;

// ==================== 文档查询 ====================
if ($tab === 'query') {
    // 按 docid 查询
    if (isset($_GET['docid'])) {
        try {
            $docInfo = $wx->wedoc->getDocument($_GET['docid']);
        } catch (\Throwable $e) { $error = '查询文档失败: ' . $e->getMessage(); }
    }

    // 文档列表
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'list_docs') {
        try {
            $spaceId = $_POST['space_id'] ?? '';
            $fatherId = $_POST['father_id'] ?? '';
            $offset = (int)($_POST['offset'] ?? 0);
            $limit = min((int)($_POST['limit'] ?? 50), 100);
            $result = $wx->wedoc->getDocumentList($spaceId, $fatherId, $offset, $limit);
            $docList = $result['doc_list'] ?? [];
            $success = "查询到 " . count($docList) . " 个文档";
        } catch (\Throwable $e) { $error = '查询文档列表失败: ' . $e->getMessage(); }
    }

    // 重命名
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rename') {
        try {
            $docid = $_POST['docid'] ?? '';
            $newName = trim($_POST['new_name'] ?? '');
            if (empty($docid) || empty($newName)) throw new \RuntimeException('参数不完整');
            $wx->wedoc->renameDocument($docid, $newName);
            $success = "文档已重命名";
            // 刷新文档信息
            $docInfo = $wx->wedoc->getDocument($docid);
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }

    // 删除
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
        try {
            $docid = $_POST['docid'] ?? '';
            if (empty($docid)) throw new \RuntimeException('请指定文档');
            $confirmName = trim($_POST['confirm_name'] ?? '');
            $currentName = $wx->wedoc->getDocument($docid);
            $currentName = $currentName['doc_name'] ?? '';
            // 简单确认
            $wx->wedoc->deleteDocument($docid);
            $success = "文档已删除";
            $docInfo = null;
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }
}

// ==================== 文档创建 ====================
if ($tab === 'create') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $docName = trim($_POST['doc_name'] ?? '');
            if (empty($docName)) throw new \RuntimeException('请输入文档名称');

            $docType = $_POST['doc_type'] ?? 'doc';
            $fatherId = $_POST['father_id'] ?? '';
            $adminUsers = array_filter(array_map('trim', explode(',', $_POST['admin_users'] ?? '')));

            $result = $wx->wedoc->createDoc($docName, $docType, $fatherId, $adminUsers);
            $url = $result['url'] ?? '';
            $success = "文档创建成功！docid: <code>" . htmlspecialchars($result['docid'] ?? '') . "</code>";
            if ($url) $success .= "，<a href=\"{$url}\" target=\"_blank\">打开文档</a>";
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }
}

// ==================== 权限管理 ====================
if ($tab === 'auth') {
    // 查询权限
    if (isset($_GET['docid_auth']) || isset($_POST['docid_auth'])) {
        try {
            $docidAuth = $_GET['docid_auth'] ?? $_POST['docid_auth'] ?? '';
            $docAuth = $wx->wedoc->getDocAuth($docidAuth);
            $success = "权限查询成功";
        } catch (\Throwable $e) { $error = '查询权限失败: ' . $e->getMessage(); }
    }
}

// ==================== 分享管理 ====================
if ($tab === 'share') {
    // 创建分享
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_share') {
        try {
            $docid = $_POST['docid'] ?? '';
            if (empty($docid)) throw new \RuntimeException('请输入文档 ID');
            $shareSetting = [];
            if (!empty($_POST['share_perm'])) $shareSetting['share_perm'] = (int)$_POST['share_perm'];
            if (!empty($_POST['open_range'])) $shareSetting['open_range'] = (int)$_POST['open_range'];
            if (!empty($_POST['expire_time'])) $shareSetting['expire_time'] = strtotime($_POST['expire_time']);
            $shareResult = $wx->wedoc->shareDoc($docid, $shareSetting);
            $success = "分享已创建";
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }

    // 查询分享
    if (isset($_POST['share_id'])) {
        try {
            $shareResult = $wx->wedoc->getShareInfo($_POST['share_id']);
        } catch (\Throwable $e) { $error = '查询分享失败: ' . $e->getMessage(); }
    }
}

require __DIR__ . '/../../layout/header.php';
?>

<style>
.wedoc-tabs { border-bottom: 2px solid #e0e0e0; margin-bottom: 15px; }
.wedoc-tabs a { display: inline-block; padding: 10px 18px; color: #666; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; font-size: 14px; }
.wedoc-tabs a:hover { color: #2196f3; }
.wedoc-tabs a.active { color: #1565c0; border-bottom-color: #1565c0; font-weight: 600; }
</style>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="wedoc-tabs">
    <a href="?tab=query" class="<?= $tab==='query'?'active':'' ?>"><i class="fa fa-search"></i> 文档查询</a>
    <a href="?tab=create" class="<?= $tab==='create'?'active':'' ?>"><i class="fa fa-plus-circle"></i> 创建文档</a>
    <a href="?tab=auth" class="<?= $tab==='auth'?'active':'' ?>"><i class="fa fa-shield"></i> 权限管理</a>
    <a href="?tab=share" class="<?= $tab==='share'?'active':'' ?>"><i class="fa fa-share-alt"></i> 分享管理</a>
</div>

<?php if ($tab === 'query'): ?>
<!-- ==================== 文档查询 ==================== -->
<div class="row">
    <div class="col-md-4">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-search"></i> <span class="caption-subject bold uppercase">按 docid 查询</span></div>
            </div>
            <div class="portlet-body">
                <form method="get" class="form-inline">
                    <input type="hidden" name="tab" value="query">
                    <div class="form-group" style="width:100%">
                        <input type="text" name="docid" class="form-control" style="width:200px" 
                               value="<?= htmlspecialchars($_GET['docid'] ?? '') ?>" placeholder="输入文档 docid" required>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-search"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-list"></i> <span class="caption-subject bold uppercase">文档列表</span></div>
            </div>
            <div class="portlet-body">
                <form method="post">
                    <input type="hidden" name="action" value="list_docs">
                    <input type="hidden" name="tab" value="query">
                    <div class="form-group">
                        <label class="text-muted">空间 ID（可选）</label>
                        <input type="text" name="space_id" class="form-control input-sm" placeholder="spaceid">
                    </div>
                    <div class="form-group">
                        <label class="text-muted">父目录 ID（可选）</label>
                        <input type="text" name="father_id" class="form-control input-sm" placeholder="fatherid">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary btn-block"><i class="fa fa-search"></i> 查询列表</button>
                </form>
                
                <?php if (!empty($docList)): ?>
                <hr>
                <h6>文档列表（<?= count($docList) ?> 个）</h6>
                <ul class="list-unstyled">
                    <?php foreach ($docList as $d): ?>
                    <li style="padding:6px 0;border-bottom:1px solid #f0f0f0">
                        <a href="?tab=query&docid=<?= urlencode($d['docid'] ?? '') ?>">
                            <i class="fa fa-file-text-o text-primary"></i> 
                            <?= htmlspecialchars($d['doc_name'] ?? '') ?>
                        </a>
                        <br><code style="font-size:9px"><?= htmlspecialchars($d['docid'] ?? '') ?></code>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <?php if (!empty($docInfo)): ?>
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-file-text-o"></i> <span class="caption-subject bold uppercase">文档信息</span></div>
                <div class="actions">
                    <a href="?tab=share&docid=<?= urlencode($docInfo['docid'] ?? '') ?>" class="btn btn-xs green"><i class="fa fa-share-alt"></i> 分享</a>
                    <a href="?tab=auth&docid_auth=<?= urlencode($docInfo['docid'] ?? '') ?>" class="btn btn-xs purple"><i class="fa fa-shield"></i> 权限</a>
                </div>
            </div>
            <div class="portlet-body">
                <table class="table table-bordered">
                    <tr><td width="120"><strong>文档名称</strong></td><td><?= htmlspecialchars($docInfo['doc_name'] ?? '') ?></td></tr>
                    <tr><td><strong>文档 ID</strong></td><td><code><?= htmlspecialchars($docInfo['docid'] ?? '') ?></code></td></tr>
                    <tr><td><strong>文档类型</strong></td>
                        <td><?php $types=['doc'=>'📄 文档','sheet'=>'📊 表格','smartsheet'=>'🧮 智能表格']; echo $types[$docInfo['doc_type']??''] ?? htmlspecialchars($docInfo['doc_type']??'') ?></td>
                    </tr>
                    <tr><td><strong>创建者</strong></td><td><?= htmlspecialchars($docInfo['creator'] ?? '-') ?></td></tr>
                    <tr><td><strong>创建时间</strong></td><td><?= date('Y-m-d H:i:s', (int)($docInfo['create_time'] ?? 0)) ?></td></tr>
                    <tr><td><strong>编辑时间</strong></td><td><?= empty($docInfo['edit_time'])?'-':date('Y-m-d H:i:s', (int)$docInfo['edit_time']) ?></td></tr>
                    <?php if (!empty($docInfo['url'])): ?>
                    <tr><td><strong>链接</strong></td><td><a href="<?= htmlspecialchars($docInfo['url']) ?>" target="_blank">打开文档 <i class="fa fa-external-link"></i></a></td></tr>
                    <?php endif; ?>
                </table>

                <div class="row" style="margin-top:15px">
                    <div class="col-md-6">
                        <form method="post" onsubmit="var n=prompt('新名称','<?= htmlspecialchars(addslashes($docInfo['doc_name']??'')) ?>');if(n){this.querySelector('[name=new_name]').value=n;return true;}return false">
                            <input type="hidden" name="action" value="rename">
                            <input type="hidden" name="tab" value="query">
                            <input type="hidden" name="docid" value="<?= htmlspecialchars($docInfo['docid'] ?? '') ?>">
                            <input type="hidden" name="new_name" value="">
                            <button type="submit" class="btn btn-sm yellow"><i class="fa fa-pencil"></i> 重命名</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="post" onsubmit="var n=prompt('输入文档名称确认删除：');if(n){this.querySelector('[name=confirm_name]').value=n;return true;}return false">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="tab" value="query">
                            <input type="hidden" name="docid" value="<?= htmlspecialchars($docInfo['docid'] ?? '') ?>">
                            <input type="hidden" name="confirm_name" value="">
                            <button type="submit" class="btn btn-sm red" onclick="return confirm('确定删除此文档？此操作不可恢复！')"><i class="fa fa-trash"></i> 删除</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
            <?= renderEmpty('fa-file-text-o', '输入 docid 查询文档', '查询后可以查看详情、重命名、删除、分享和设置权限') ?>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($tab === 'create'): ?>
<!-- ==================== 文档创建 ==================== -->
<div class="row">
    <div class="col-md-6 col-md-offset-3">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-plus-circle"></i> <span class="caption-subject bold uppercase">创建新文档</span></div>
            </div>
            <div class="portlet-body">
                <form method="post">
                    <input type="hidden" name="tab" value="create">

                    <div class="form-group">
                        <label>文档名称 <span class="text-danger">*</span></label>
                        <input type="text" name="doc_name" class="form-control" placeholder="例如：项目计划书" required>
                    </div>

                    <div class="form-group">
                        <label>文档类型</label>
                        <select name="doc_type" class="form-control">
                            <option value="doc">📄 文档</option>
                            <option value="sheet">📊 表格</option>
                            <option value="smartsheet">🧮 智能表格</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>父目录 ID（可选）</label>
                        <input type="text" name="father_id" class="form-control" placeholder="留空=根目录">
                    </div>

                    <div class="form-group">
                        <label>管理员（多个 userid 用逗号分隔，可选）</label>
                        <input type="text" name="admin_users" class="form-control" placeholder="userid1, userid2">
                    </div>

                    <button type="submit" class="btn btn-lg btn-primary btn-block"><i class="fa fa-plus-circle"></i> 创建文档</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'auth'): ?>
<!-- ==================== 权限管理 ==================== -->
<div class="row">
    <div class="col-md-12">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-shield"></i> <span class="caption-subject bold uppercase">文档权限查询</span></div>
            </div>
            <div class="portlet-body">
                <form method="get" class="form-inline">
                    <input type="hidden" name="tab" value="auth">
                    <div class="form-group">
                        <label>文档 ID：</label>
                        <input type="text" name="docid_auth" class="form-control" style="width:350px" 
                               value="<?= htmlspecialchars($_GET['docid_auth'] ?? $_POST['docid_auth'] ?? '') ?>"
                               placeholder="输入文档 docid" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> 查询权限</button>
                </form>

                <?php if (!empty($docAuth)): ?>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h5>文档基本信息</h5>
                        <table class="table table-bordered">
                            <tr><td>文档名称</td><td><?= htmlspecialchars($docAuth['doc_name'] ?? '') ?></td></tr>
                            <tr><td>文档 ID</td><td><code><?= htmlspecialchars($docAuth['docid'] ?? '') ?></code></td></tr>
                            <tr><td>加入规则</td><td><?= htmlspecialchars($docAuth['join_rule'] ?? '') ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>成员权限（<?= count($docAuth['members'] ?? []) ?> 人）</h5>
                        <?php if (!empty($docAuth['members'])): ?>
                        <table class="table table-striped table-bordered">
                            <thead><tr><th>成员</th><th>类型</th><th>权限</th></tr></thead>
                            <tbody>
                                <?php foreach ($docAuth['members'] as $m): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($m['userid'] ?? '') ?></code></td>
                                    <td><?= ($m['type']??1)===1?'成员':'部门' ?></td>
                                    <td><span class="label label-<?= ($m['auth']??1)===2?'success':'info' ?>"><?= ($m['auth']??1)===2?'编辑':'只读' ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p class="text-muted">暂无成员权限信息</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'share'): ?>
<!-- ==================== 分享管理 ==================== -->
<div class="row">
    <div class="col-md-6">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-link"></i> <span class="caption-subject bold uppercase">创建分享链接</span></div>
            </div>
            <div class="portlet-body">
                <form method="post">
                    <input type="hidden" name="action" value="create_share">
                    <input type="hidden" name="tab" value="share">
                    <div class="form-group">
                        <label>文档 ID <span class="text-danger">*</span></label>
                        <input type="text" name="docid" class="form-control" value="<?= htmlspecialchars($_GET['docid'] ?? '') ?>" placeholder="输入文档 docid" required>
                    </div>
                    <div class="form-group">
                        <label>分享权限</label>
                        <select name="share_perm" class="form-control">
                            <option value="1">仅查看</option>
                            <option value="2">可编辑</option>
                            <option value="3">只读（禁止下载）</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>开放范围</label>
                        <select name="open_range" class="form-control">
                            <option value="1">企业内</option>
                            <option value="2">所有人</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>过期时间（可选）</label>
                        <input type="datetime-local" name="expire_time" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-link"></i> 创建分享</button>
                </form>
            </div>
        </div>

        <?php if (!empty($shareResult)): ?>
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-check-circle text-success"></i> 分享信息</div>
            </div>
            <div class="portlet-body">
                <table class="table table-bordered">
                    <?php foreach (['share_id' => '分享 ID', 'share_url' => '分享链接', 'share_pwd' => '访问密码', 'expire_time' => '过期时间'] as $k => $label): ?>
                    <?php if (!empty($shareResult[$k])): ?>
                    <tr>
                        <td width="100"><strong><?= $label ?></strong></td>
                        <td>
                            <?php if ($k === 'share_url'): ?>
                                <a href="<?= htmlspecialchars($shareResult[$k]) ?>" target="_blank"><?= htmlspecialchars(mb_substr($shareResult[$k], 0, 60)) ?>...</a>
                            <?php elseif ($k === 'expire_time'): ?>
                                <?= date('Y-m-d H:i', (int)$shareResult[$k]) ?>
                            <?php else: ?>
                                <code><?= htmlspecialchars($shareResult[$k]) ?></code>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-6">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-search"></i> <span class="caption-subject bold uppercase">查询分享链接</span></div>
            </div>
            <div class="portlet-body">
                <form method="post" class="form-inline">
                    <input type="hidden" name="tab" value="share">
                    <div class="form-group">
                        <label>分享 ID：</label>
                        <input type="text" name="share_id" class="form-control" style="width:300px" placeholder="输入分享 share_id" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-search"></i> 查询</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

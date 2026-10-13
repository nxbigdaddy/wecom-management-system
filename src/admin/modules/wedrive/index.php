<?php
/**
 * 管理后台 - 微盘管理
 * 
 * 功能：
 *   1. 📁 空间管理 — 创建/重命名/解散/查询空间
 *   2. 📂 文件浏览 — 浏览空间文件、新建文件夹、重命名、移动、删除
 *   3. 👥 权限管理 — 空间/文件级 ACL 查询与设置
 *   4. 🔗 分享管理 — 创建/查看分享链接
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '微盘管理';
$error = ''; $success = '';
$tab = $_GET['tab'] ?? $_POST['tab'] ?? 'spaces';

$spaces = [];
$fileList = [];
$fileInfo = null;
$aclList = [];
$shareInfo = null;

// ==================== 空间管理 ====================
if ($tab === 'spaces') {
    // 创建空间
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_space') {
        try {
            $name = trim($_POST['space_name'] ?? '');
            if (empty($name)) throw new \RuntimeException('请输入空间名称');
            $admins = array_filter(array_map('trim', explode(',', $_POST['admin_users'] ?? '')));
            $result = $wx->wedrive->spaceCreate($name, $admins);
            $success = "空间创建成功！spaceid: <code>" . htmlspecialchars($result['spaceid'] ?? '') . "</code>";
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }

    // 重命名空间
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rename_space') {
        try {
            $spId = $_POST['space_id'] ?? '';
            $newName = trim($_POST['new_name'] ?? '');
            if (empty($spId) || empty($newName)) throw new \RuntimeException('参数不完整');
            $wx->wedrive->spaceRename($spId, $newName);
            $success = '空间已重命名';
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }

    // 解散空间
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dismiss_space') {
        try {
            $spId = $_POST['space_id'] ?? '';
            if (empty($spId)) throw new \RuntimeException('请指定空间');
            $wx->wedrive->spaceDismiss($spId);
            $success = '空间已解散';
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }

    // 查询空间列表
    try {
        $result = $wx->wedrive->spaceList();
        $spaces = $result['space_list'] ?? [];
    } catch (\Throwable $e) { $error = '获取空间列表失败: ' . $e->getMessage(); }
}

// ==================== 文件浏览 ====================
if ($tab === 'files') {
    $currentSpaceId = $_GET['space_id'] ?? $_POST['space_id'] ?? '';
    $currentDirId = $_GET['dir_id'] ?? $_POST['dir_id'] ?? '';

    // 新建文件夹
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mkdir') {
        try {
            $folderName = trim($_POST['folder_name'] ?? '');
            if (empty($folderName)) throw new \RuntimeException('请输入文件夹名称');
            $wx->wedrive->fileCreate($currentSpaceId, $folderName, 1, $currentDirId);
            $success = "文件夹「{$folderName}」已创建";
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }

    // 重命名
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rename_file') {
        try {
            $fileId = $_POST['file_id'] ?? '';
            $newName = trim($_POST['new_name'] ?? '');
            if (empty($fileId) || empty($newName)) throw new \RuntimeException('参数不完整');
            $wx->wedrive->fileRename($fileId, $newName);
            $success = '文件已重命名';
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }

    // 移动文件
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'move_file') {
        try {
            $fileIds = array_filter(array_map('trim', explode(',', $_POST['file_ids'] ?? '')));
            $targetSpaceId = $_POST['target_space_id'] ?? $currentSpaceId;
            $targetDirId = $_POST['target_dir_id'] ?? '';
            if (empty($fileIds)) throw new \RuntimeException('请选择要移动的文件');
            $wx->wedrive->fileMove($fileIds, $targetSpaceId, $targetDirId);
            $success = '文件已移动';
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }

    // 删除文件
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_file') {
        try {
            $fileIds = array_filter(array_map('trim', explode(',', $_POST['file_ids'] ?? '')));
            if (empty($fileIds)) throw new \RuntimeException('请选择要删除的文件');
            $wx->wedrive->fileDelete($fileIds);
            $success = '文件已删除';
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }

    // 获取文件列表
    if (!empty($currentSpaceId)) {
        try {
            $fileType = (int)($_GET['file_type'] ?? 6); // 6=全部
            $res = $wx->wedrive->getFileList($currentSpaceId, $currentDirId, $fileType);
            $fileList = $res['file_list'] ?? [];
        } catch (\Throwable $e) { $error = '获取文件列表失败: ' . $e->getMessage(); }
    }
}

// ==================== 权限管理 ====================
if ($tab === 'acl') {
    // 查询空间权限
    if (isset($_POST['space_id_acl']) || isset($_GET['space_id_acl'])) {
        $aclSpaceId = $_POST['space_id_acl'] ?? $_GET['space_id_acl'] ?? '';
        try {
            $result = $wx->wedrive->spaceAclList($aclSpaceId);
            $aclList = $result['auth_list'] ?? [];
            $success = "查询到 " . count($aclList) . " 个空间成员";
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }
}

// ==================== 分享管理 ====================
if ($tab === 'share') {
    // 创建分享链接
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_share') {
        try {
            $fileId = $_POST['file_id'] ?? '';
            if (empty($fileId)) throw new \RuntimeException('请输入文件 fileid');
            $settings = [];
            if (!empty($_POST['share_perm'])) $settings['share_perm'] = (int)$_POST['share_perm'];
            if (!empty($_POST['expire_time'])) $settings['expire_time'] = strtotime($_POST['expire_time']);
            if (!empty($_POST['enable_pwd'])) $settings['enable_pwd'] = true;
            $result = $wx->wedrive->fileShareCreate($fileId, $settings);
            $shareInfo = $result;
            $success = "分享链接已创建";
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }

    // 查询分享链接
    if (isset($_POST['share_id_query'])) {
        try {
            $shareId = $_POST['share_id_query'];
            $shareInfo = $wx->wedrive->fileShareGet($shareId);
        } catch (\Throwable $e) { $error = $e->getMessage(); }
    }
}

require __DIR__ . '/../../layout/header.php';
?>

<style>
.wedrive-tabs { border-bottom: 2px solid #e0e0e0; margin-bottom: 15px; }
.wedrive-tabs a { display: inline-block; padding: 10px 18px; color: #666; text-decoration: none; border-bottom: 2px solid transparent; margin-bottom: -2px; font-size: 14px; }
.wedrive-tabs a:hover { color: #2196f3; }
.wedrive-tabs a.active { color: #1565c0; border-bottom-color: #1565c0; font-weight: 600; }
.file-icon { width: 32px; text-align: center; font-size: 18px; }
</style>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="wedrive-tabs">
    <a href="?tab=spaces" class="<?= $tab==='spaces'?'active':'' ?>"><i class="fa fa-folder-open-o"></i> 空间管理</a>
    <a href="?tab=files" class="<?= $tab==='files'?'active':'' ?>"><i class="fa fa-file-o"></i> 文件浏览</a>
    <a href="?tab=acl" class="<?= $tab==='acl'?'active':'' ?>"><i class="fa fa-shield"></i> 权限管理</a>
    <a href="?tab=share" class="<?= $tab==='share'?'active':'' ?>"><i class="fa fa-share-alt"></i> 分享管理</a>
</div>

<?php if ($tab === 'spaces'): ?>
<!-- ==================== 空间管理 ==================== -->
<div class="row">
    <div class="col-md-4">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-plus-circle"></i> <span class="caption-subject bold uppercase">创建空间</span></div>
            </div>
            <div class="portlet-body">
                <form method="post">
                    <input type="hidden" name="action" value="create_space">
                    <input type="hidden" name="tab" value="spaces">
                    <div class="form-group">
                        <label>空间名称 <span class="text-danger">*</span></label>
                        <input type="text" name="space_name" class="form-control" placeholder="例如：项目文档" required>
                    </div>
                    <div class="form-group">
                        <label>管理员（多个 userid 用逗号分隔）</label>
                        <input type="text" name="admin_users" class="form-control" placeholder="userid1, userid2">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> 创建空间</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-list"></i> <span class="caption-subject bold uppercase">空间列表</span></div>
            </div>
            <div class="portlet-body">
                <?php if (!empty($spaces)): ?>
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>空间名称</th>
                            <th>空间 ID</th>
                            <th>创建者</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($spaces as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['space_name'] ?? $s['name'] ?? '') ?></strong></td>
                            <td><code style="font-size:10px"><?= htmlspecialchars($s['spaceid'] ?? '') ?></code></td>
                            <td><?= htmlspecialchars($s['creator'] ?? '-') ?></td>
                            <td class="text-center" style="width:200px">
                                <a href="?tab=files&space_id=<?= urlencode($s['spaceid'] ?? '') ?>" class="btn btn-xs blue"><i class="fa fa-folder-open"></i> 浏览</a>
                                <a href="?tab=acl&space_id_acl=<?= urlencode($s['spaceid'] ?? '') ?>" class="btn btn-xs purple"><i class="fa fa-shield"></i> 权限</a>
                                <form method="post" style="display:inline" onsubmit="var n=prompt('请输入新名称');if(n){this.querySelector('[name=new_name]').value=n;return true;}return false">
                                    <input type="hidden" name="action" value="rename_space">
                                    <input type="hidden" name="tab" value="spaces">
                                    <input type="hidden" name="space_id" value="<?= htmlspecialchars($s['spaceid'] ?? '') ?>">
                                    <input type="hidden" name="new_name" value="">
                                    <button type="submit" class="btn btn-xs yellow"><i class="fa fa-pencil"></i></button>
                                </form>
                                <form method="post" style="display:inline" onsubmit="return confirm('确定解散该空间？文件将被删除！')">
                                    <input type="hidden" name="action" value="dismiss_space">
                                    <input type="hidden" name="tab" value="spaces">
                                    <input type="hidden" name="space_id" value="<?= htmlspecialchars($s['spaceid'] ?? '') ?>">
                                    <button type="submit" class="btn btn-xs red"><i class="fa fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <?= renderEmpty('fa-folder-open-o', '暂无空间', '点击左侧创建新空间') ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'files'): ?>
<!-- ==================== 文件浏览 ==================== -->
<div class="row">
    <div class="col-md-3">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-folder-open"></i> <span class="caption-subject bold uppercase">操作</span></div>
            </div>
            <div class="portlet-body">
                <form method="get" class="form-group" style="margin-bottom:15px">
                    <label>空间 ID</label>
                    <input type="hidden" name="tab" value="files">
                    <input type="text" name="space_id" class="form-control input-sm" value="<?= htmlspecialchars($currentSpaceId) ?>" placeholder="输入空间 ID">
                    <button type="submit" class="btn btn-sm btn-primary btn-block" style="margin-top:6px"><i class="fa fa-search"></i> 浏览</button>
                </form>

                <?php if (!empty($currentSpaceId)): ?>
                <hr>
                <form method="post" style="margin-bottom:15px">
                    <input type="hidden" name="action" value="mkdir">
                    <input type="hidden" name="tab" value="files">
                    <input type="hidden" name="space_id" value="<?= htmlspecialchars($currentSpaceId) ?>">
                    <input type="hidden" name="dir_id" value="<?= htmlspecialchars($currentDirId) ?>">
                    <div class="input-group">
                        <input type="text" name="folder_name" class="form-control input-sm" placeholder="新文件夹名" required>
                        <span class="input-group-btn">
                            <button class="btn btn-sm btn-success" type="submit"><i class="fa fa-plus"></i> 新建</button>
                        </span>
                    </div>
                </form>

                <form method="post" style="margin-bottom:15px">
                    <input type="hidden" name="action" value="move_file">
                    <input type="hidden" name="tab" value="files">
                    <input type="hidden" name="space_id" value="<?= htmlspecialchars($currentSpaceId) ?>">
                    <div class="form-group">
                        <label class="text-muted" style="font-size:11px">移动文件（fileid 逗号分隔）</label>
                        <input type="text" name="file_ids" class="form-control input-sm" placeholder="fileid1, fileid2">
                    </div>
                    <div class="form-group">
                        <label class="text-muted" style="font-size:11px">目标目录（留空=根目录）</label>
                        <input type="text" name="target_dir_id" class="form-control input-sm" placeholder="可选">
                    </div>
                    <button type="submit" class="btn btn-sm btn-warning btn-block"><i class="fa fa-arrows"></i> 移动</button>
                </form>

                <form method="post" onsubmit="return confirm('确定删除选中文件？')">
                    <input type="hidden" name="action" value="delete_file">
                    <input type="hidden" name="tab" value="files">
                    <input type="hidden" name="space_id" value="<?= htmlspecialchars($currentSpaceId) ?>">
                    <div class="form-group">
                        <label class="text-muted" style="font-size:11px">删除文件（fileid 逗号分隔）</label>
                        <input type="text" name="file_ids" class="form-control input-sm" placeholder="fileid1, fileid2" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-danger btn-block"><i class="fa fa-trash"></i> 删除</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-9">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-list"></i> <span class="caption-subject bold uppercase">文件列表</span></div>
                <div class="actions">
                    <?php if (!empty($currentDirId)): ?>
                    <a href="?tab=files&space_id=<?= urlencode($currentSpaceId) ?>" class="btn btn-xs default"><i class="fa fa-level-up"></i> 回到根目录</a>
                    <?php endif; ?>
                    <span class="text-muted" style="font-size:11px">空间: <code><?= htmlspecialchars($currentSpaceId) ?></code></span>
                </div>
            </div>
            <div class="portlet-body">
                <?php if (empty($currentSpaceId)): ?>
                    <?= renderEmpty('fa-folder-open-o', '请选择空间', '在左侧输入空间 ID 后点击"浏览"') ?>
                <?php elseif (empty($fileList)): ?>
                    <?= renderEmpty('fa-file-o', '暂无文件', '此目录为空，可以新建文件夹或上传文件') ?>
                <?php else: ?>
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th class="file-icon"></th>
                            <th>文件名</th>
                            <th>类型</th>
                            <th>文件 ID</th>
                            <th>大小</th>
                            <th>创建者</th>
                            <th class="text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $typeIcons = [1=>'fa-folder text-warning', 2=>'fa-file-o text-muted', 3=>'fa-file-text-o text-primary', 4=>'fa-table text-info', 10=>'fa-cubes text-success'];
                        $typeNames = [1=>'文件夹', 2=>'文件', 3=>'文档', 4=>'表格', 10=>'智能表'];
                        ?>
                        <?php foreach ($fileList as $f): 
                            $ft = (int)($f['file_type'] ?? 2);
                            $icon = $typeIcons[$ft] ?? 'fa-file-o';
                        ?>
                        <tr>
                            <td class="file-icon"><i class="fa <?= $icon ?>"></i></td>
                            <td>
                                <?php if ($ft === 1): // 文件夹可点击进入 ?>
                                    <a href="?tab=files&space_id=<?= urlencode($currentSpaceId) ?>&dir_id=<?= urlencode($f['fileid'] ?? '') ?>" class="fw-bold">
                                        <?= htmlspecialchars($f['file_name'] ?? $f['name'] ?? '') ?>
                                    </a>
                                <?php else: ?>
                                    <strong><?= htmlspecialchars($f['file_name'] ?? $f['name'] ?? '') ?></strong>
                                <?php endif; ?>
                            </td>
                            <td><?= $typeNames[$ft] ?? '未知' ?></td>
                            <td><code style="font-size:10px"><?= htmlspecialchars($f['fileid'] ?? '') ?></code></td>
                            <td><?= $ft > 1 ? (function($b){ $b=(int)$b; return $b>1048576?round($b/1048576,1).'MB':($b>1024?round($b/1024,1).'KB':$b.'B'); })($f['size']??0) : '-' ?></td>
                            <td><?= htmlspecialchars($f['create_userid'] ?? $f['creator'] ?? '-') ?></td>
                            <td class="text-center">
                                <form method="post" style="display:inline" onsubmit="var n=prompt('新名称','<?= htmlspecialchars(addslashes($f['file_name'] ?? $f['name'] ?? '')) ?>');if(n){this.querySelector('[name=new_name]').value=n;return true;}return false">
                                    <input type="hidden" name="action" value="rename_file">
                                    <input type="hidden" name="tab" value="files">
                                    <input type="hidden" name="space_id" value="<?= htmlspecialchars($currentSpaceId) ?>">
                                    <input type="hidden" name="file_id" value="<?= htmlspecialchars($f['fileid'] ?? '') ?>">
                                    <input type="hidden" name="new_name" value="">
                                    <button type="submit" class="btn btn-xs yellow" title="重命名"><i class="fa fa-pencil"></i></button>
                                </form>
                                <a href="?tab=share&file_id_param=<?= urlencode($f['fileid'] ?? '') ?>" class="btn btn-xs green" title="分享"><i class="fa fa-share-alt"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'acl'): ?>
<!-- ==================== 权限管理 ==================== -->
<div class="row">
    <div class="col-md-12">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-shield"></i> <span class="caption-subject bold uppercase">空间权限查询</span></div>
            </div>
            <div class="portlet-body">
                <form method="post" class="form-inline">
                    <input type="hidden" name="tab" value="acl">
                    <div class="form-group">
                        <label>空间 ID：</label>
                        <input type="text" name="space_id_acl" class="form-control" style="width:350px" 
                               value="<?= htmlspecialchars($_POST['space_id_acl'] ?? $_GET['space_id_acl'] ?? '') ?>"
                               placeholder="输入空间 spaceid" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> 查询权限</button>
                </form>

                <?php if (!empty($aclList)): ?>
                <hr>
                <h5>空间成员列表（<?= count($aclList) ?> 人）</h5>
                <table class="table table-striped table-bordered" style="margin-top:10px">
                    <thead>
                        <tr>
                            <th>成员</th>
                            <th>类型</th>
                            <th>权限</th>
                            <th>加入时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aclList as $a): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($a['userid'] ?? '') ?></code></td>
                            <td><?= ($a['type'] ?? 1) == 1 ? '成员' : '部门' ?></td>
                            <td><span class="label label-<?= ($a['auth']??1)==1?'info':'success' ?>"><?= ($a['auth']??1)==1?'只读':'读写' ?></span></td>
                            <td><?= date('Y-m-d H:i', (int)($a['create_time'] ?? 0)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php elseif (isset($_POST['space_id_acl']) || isset($_GET['space_id_acl'])): ?>
                <p class="text-muted" style="margin-top:15px">暂无权限数据</p>
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
                        <label>文件 ID <span class="text-danger">*</span></label>
                        <input type="text" name="file_id" class="form-control" value="<?= htmlspecialchars($_GET['file_id_param'] ?? '') ?>" placeholder="输入文件 fileid" required>
                    </div>
                    <div class="form-group">
                        <label>分享权限</label>
                        <select name="share_perm" class="form-control">
                            <option value="1">仅查看</option>
                            <option value="2">可编辑</option>
                            <option value="3">可下载</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>过期时间（可选）</label>
                        <input type="datetime-local" name="expire_time" class="form-control">
                    </div>
                    <div class="form-group">
                        <div class="checkbox">
                            <label><input type="checkbox" name="enable_pwd" value="1"> 启用访问密码</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-link"></i> 创建分享</button>
                </form>
            </div>
        </div>

        <?php if (!empty($shareInfo)): ?>
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-check-circle text-success"></i> 分享信息</div>
            </div>
            <div class="portlet-body">
                <table class="table table-bordered">
                    <?php foreach (['shareid' => '分享 ID', 'share_url' => '分享链接', 'share_pwd' => '访问密码', 'expire_time' => '过期时间'] as $k => $label): ?>
                    <?php if (!empty($shareInfo[$k])): ?>
                    <tr>
                        <td width="100"><strong><?= $label ?></strong></td>
                        <td>
                            <?php if ($k === 'share_url'): ?>
                                <a href="<?= htmlspecialchars($shareInfo[$k]) ?>" target="_blank"><?= htmlspecialchars(mb_substr($shareInfo[$k], 0, 60)) ?>...</a>
                            <?php elseif ($k === 'expire_time'): ?>
                                <?= date('Y-m-d H:i', (int)$shareInfo[$k]) ?>
                            <?php else: ?>
                                <code><?= htmlspecialchars($shareInfo[$k]) ?></code>
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
                        <input type="text" name="share_id_query" class="form-control" style="width:300px" placeholder="输入分享 shareid" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-search"></i> 查询</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

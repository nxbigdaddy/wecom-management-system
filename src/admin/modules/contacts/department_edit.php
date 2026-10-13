<?php
/**
 * 管理后台 - 部门创建/编辑/删除
 * 
 * 路由：
 *   GET  /contacts/departments/create        → 创建部门表单
 *   POST /contacts/departments/create        → 保存新部门
 *   GET  /contacts/departments/{id}/edit     → 编辑部门表单
 *   POST /contacts/departments/{id}/edit     → 保存部门变更
 *   POST /contacts/departments/{id}/delete   → 删除部门
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '编辑部门';
$error = '';
$success = '';
$isEdit = false;
$deptId = null;
$dept = null;
$parentDepts = [];

// 解析部门 ID
$uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($uri, PHP_URL_PATH);
// 匹配 /contacts/departments/{id}/edit 或 /contacts/departments/{id}/delete
if (preg_match('#/contacts/departments/(\d+)/(edit|delete)#', $path, $matches)) {
    $deptId = (int)$matches[1];
    $isEdit = true;
}

// 获取父部门列表（用于下拉选择）
try {
    $result = $wx->get('/cgi-bin/department/list');
    if (($result['errcode'] ?? -1) === 0) {
        $parentDepts = $result['department'] ?? [];
    }
} catch (\Throwable $e) {}

// 编辑模式：获取当前部门信息
if ($isEdit && $deptId && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $result = $wx->get('/cgi-bin/department/get', ['id' => $deptId]);
        if (($result['errcode'] ?? -1) === 0) {
            $dept = $result;
            $title = '编辑部门: ' . ($dept['department']['name'] ?? "ID {$deptId}");
        } else {
            $error = '获取部门失败: ' . ($result['errmsg'] ?? '未知错误');
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

// 处理 POST 提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    
    try {
        // 删除操作
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? $deptId ?? 0);
            if ($id <= 0) {
                throw new \RuntimeException('无效的部门 ID');
            }
            $wx->addressbook->departmentDelete($id);
            $_SESSION['flash_success'] = '部门已删除';
            header('Location: /contacts/departments');
            exit;
        }

        // 保存操作
        $name = trim($_POST['name'] ?? '');
        $parentId = (int)($_POST['parentid'] ?? 0);
        $order = (int)($_POST['order'] ?? 1);
        
        if (empty($name)) {
            throw new \RuntimeException('部门名称不能为空');
        }

        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? $deptId ?? 0);
            if ($id <= 0) {
                throw new \RuntimeException('无效的部门 ID');
            }
            $wx->addressbook->departmentUpdate([
                'id'       => $id,
                'name'     => $name,
                'parentid' => $parentId,
                'order'    => $order,
            ]);
            $_SESSION['flash_success'] = '部门更新成功';
        } else {
            $result = $wx->addressbook->departmentCreate([
                'name'     => $name,
                'parentid' => $parentId,
                'order'    => $order,
            ]);
            $_SESSION['flash_success'] = '部门创建成功 (ID: ' . ($result['id'] ?? 'N/A') . ')';
        }
        
        header('Location: /contacts/departments');
        exit;
        
    } catch (\Throwable $e) {
        $error = $e->getMessage();
        // 保留表单数据
        if ($action !== 'delete') {
            $dept = [
                'department' => [
                    'name'     => $_POST['name'] ?? '',
                    'parentid' => (int)($_POST['parentid'] ?? 0),
                    'order'    => (int)($_POST['order'] ?? 1),
                ]
            ];
        }
    }
}

$deptData = $dept['department'] ?? ['name' => '', 'parentid' => 0, 'order' => 1];

require __DIR__ . '/../../layout/header.php';
?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-<?= $isEdit ? 'edit' : 'plus' ?>"></i>
                    <span class="caption-subject bold uppercase"><?= $isEdit ? '编辑部门' : '创建部门' ?></span>
                </div>
                <div class="actions">
                    <a href="/contacts/departments" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> 返回列表
                    </a>
                </div>
            </div>
            <div class="portlet-body">
                <form method="post" class="form-horizontal">
                    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'save' ?>">
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= $deptId ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="col-md-3 control-label">部门名称 <span class="text-danger">*</span></label>
                        <div class="col-md-8">
                            <input type="text" name="name" class="form-control input-lg" 
                                   value="<?= htmlspecialchars($deptData['name']) ?>" 
                                   placeholder="请输入部门名称" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-3 control-label">父部门</label>
                        <div class="col-md-8">
                            <select name="parentid" class="form-control">
                                <option value="0">根部门（无上级）</option>
                                <?php foreach ($parentDepts as $p): ?>
                                <?php if ($isEdit && (int)$p['id'] === $deptId) continue; // 不能选自己 ?>
                                <option value="<?= $p['id'] ?>" <?= (int)$deptData['parentid'] === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?> (ID: <?= $p['id'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="help-block">选择上级部门，选择"根部门"则创建为顶级部门</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-3 control-label">排序</label>
                        <div class="col-md-4">
                            <input type="number" name="order" class="form-control" 
                                   value="<?= (int)$deptData['order'] ?>" min="0" max="99999">
                            <p class="help-block">数字越小越靠前，默认为 1</p>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                    <div class="form-group">
                        <label class="col-md-3 control-label">部门 ID</label>
                        <div class="col-md-8">
                            <p class="form-control-static"><code><?= $deptId ?></code></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <hr>
                    <div class="form-group">
                        <div class="col-md-offset-3 col-md-8">
                            <button type="submit" class="btn btn-lg btn-primary">
                                <i class="fa fa-save"></i> <?= $isEdit ? '保存修改' : '创建部门' ?>
                            </button>
                            <a href="/contacts/departments" class="btn btn-lg btn-default">取消</a>
                            <?php if ($isEdit): ?>
                            <button type="button" class="btn btn-lg btn-danger pull-right" 
                                    onclick="if(confirm('确定要删除此部门吗？\n注意：删除部门会同时删除其下属所有子部门！')){var f=document.createElement('form');f.method='POST';f.innerHTML='<input name=action value=delete><input name=id value=<?= $deptId ?>>';document.body.appendChild(f);f.submit();}">
                                <i class="fa fa-trash"></i> 删除此部门
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($isEdit && isset($dept['department'])): ?>
    <div class="col-md-4">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-info-circle"></i> 部门信息</div>
            </div>
            <div class="portlet-body">
                <table class="table table-bordered">
                    <tr><td width="100"><strong>ID</strong></td><td><code><?= $deptId ?></code></td></tr>
                    <tr><td><strong>名称</strong></td><td><?= htmlspecialchars($dept['department']['name'] ?? '-') ?></td></tr>
                    <tr><td><strong>英文名</strong></td><td><?= htmlspecialchars($dept['department']['name_en'] ?? '-') ?></td></tr>
                    <tr><td><strong>父部门ID</strong></td><td><?= (int)($dept['department']['parentid'] ?? 0) ?></td></tr>
                    <tr><td><strong>排序</strong></td><td><?= (int)($dept['department']['order'] ?? 0) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

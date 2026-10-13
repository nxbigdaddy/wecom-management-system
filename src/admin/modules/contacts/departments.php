<?php
/**
 * 管理后台 - 部门管理（细化版：树形结构+详情弹窗）
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '部门管理';
$error = ''; $success = '';
$departments = [];

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'create':
                $wx->addressbook->departmentCreate([
                    'name' => $_POST['name'],
                    'parentid' => (int)$_POST['parentid'],
                    'order' => (int)$_POST['order'] ?? 1,
                ]);
                $success = '部门创建成功';
                break;
            case 'update':
                $wx->addressbook->departmentUpdate([
                    'id' => (int)$_POST['id'],
                    'name' => $_POST['name'],
                    'parentid' => (int)$_POST['parentid'],
                    'order' => (int)$_POST['order'] ?? 1,
                ]);
                $success = '部门更新成功';
                break;
            case 'delete':
                $wx->addressbook->departmentDelete((int)$_POST['id']);
                $success = '部门已删除';
                break;
        }
    } catch (\Throwable $e) { $error = $e->getMessage(); }
}

// 获取部门列表
try {
    $result = $wx->get('/cgi-bin/department/list');
    $departments = ($result['errcode'] ?? -1) === 0 ? ($result['department'] ?? []) : [];
} catch (\Throwable $e) { $error = $e->getMessage(); }

// 构建树形结构
function buildTree(array $items, int $parentId = 0): array {
    $branch = [];
    foreach ($items as $item) {
        if ((int)($item['parentid'] ?? 0) === $parentId) {
            $item['children'] = buildTree($items, (int)$item['id']);
            $branch[] = $item;
        }
    }
    return $branch;
}
$tree = buildTree($departments);

require __DIR__ . '/../../layout/header.php';
?>

<?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-sitemap"></i> <span class="caption-subject bold uppercase">部门树形结构</span>
                </div>
                <div class="actions">
                    <a class="btn btn-shy btn-default" href="javascript:void(0)" onclick="openCreate(0)"><i class="fa fa-plus"></i> 创建根部门</a>
                </div>
            </div>
            <div class="portlet-body">
                <?php if (empty($tree)): ?>
                    <div class="text-center text-muted" style="padding:40px">
                        <i class="fa fa-info-circle fa-3x"></i>
                        <p>暂无部门数据，请创建根部门</p>
                    </div>
                <?php else: ?>
                    <div class="tree-view" style="padding:10px">
                        <?php
                        function renderTree(array $nodes, int $level = 0) {
                            foreach ($nodes as $node) {
                                $hasChildren = !empty($node['children']);
                                $indent = $level * 30;
                                echo '<div class="tree-node" style="padding:8px 0 ' . ($hasChildren ? '0' : '8px') . ' ' . $indent . 'px;border-bottom:1px solid #eee">';
                                echo '<i class="fa fa-' . ($hasChildren ? 'folder-open text-warning' : 'file-o text-muted') . '"></i> ';
                                echo '<strong>' . htmlspecialchars($node['name']) . '</strong>';
                                echo ' <small class="text-muted">(ID:' . $node['id'] . ', 父级:' . ($node['parentid'] ?? 0) . ')</small>';
                                echo '<div class="pull-right">';
                                echo '<a class="btn btn-xs blue" href="javascript:void(0)" onclick="showDetail(' . $node['id'] . ',\'' . addslashes($node['name']) . '\',' . ($node['parentid'] ?? 0) . ',' . ($node['order'] ?? 1) . ')"><i class="fa fa-eye"></i></a>';
                                echo '<a class="btn btn-xs green" href="javascript:void(0)" onclick="openEdit(' . $node['id'] . ',\'' . addslashes($node['name']) . '\',' . ($node['parentid'] ?? 0) . ',' . ($node['order'] ?? 1) . ')"><i class="fa fa-edit"></i></a>';
                                echo '<a class="btn btn-xs red" href="javascript:void(0)" onclick="deleteDept(' . $node['id'] . ')"><i class="fa fa-trash"></i></a>';
                                echo '</div>';
                                echo '</div>';
                                if ($hasChildren) {
                                    renderTree($node['children'], $level + 1);
                                }
                            }
                        }
                        renderTree($tree);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-list"></i> <span class="caption-subject bold uppercase">部门详情</span>
                </div>
            </div>
            <div class="portlet-body" id="deptDetail">
                <div class="text-center text-muted" style="padding:60px 20px">
                    <i class="fa fa-hand-o-left fa-3x"></i>
                    <p>点击左侧部门查看详情</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 创建/编辑 Modal -->
<div class="modal fade" id="deptModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="deptForm">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="modalTitle">创建部门</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="fAction" value="create">
                    <input type="hidden" name="id" id="fId">
                    <div class="form-group">
                        <label>部门名称 <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="fName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>父部门 ID</label>
                        <input type="number" name="parentid" id="fParent" class="form-control" value="0" min="0">
                        <p class="help-block">0 表示根部门</p>
                    </div>
                    <div class="form-group">
                        <label>排序</label>
                        <input type="number" name="order" id="fOrder" class="form-control" value="1" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreate(parentId) {
    $('#modalTitle').text('创建部门');
    $('#fAction').val('create');
    $('#fId').val('');
    $('#fName').val('');
    $('#fParent').val(parentId || 0);
    $('#fOrder').val(1);
    $('#deptModal').modal('show');
}

function openEdit(id, name, parentId, order) {
    $('#modalTitle').text('编辑部门');
    $('#fAction').val('update');
    $('#fId').val(id);
    $('#fName').val(name);
    $('#fParent').val(parentId);
    $('#fOrder').val(order);
    $('#deptModal').modal('show');
}

function deleteDept(id) {
    if (confirm('确定要删除该部门吗？\n\n注意：删除部门会同时删除其下属所有子部门！')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input name="action" value="delete"><input name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function showDetail(id, name, parentId, order) {
    var html = '<h4><i class="fa fa-folder-open text-warning"></i> ' + name + '</h4>';
    html += '<hr>';
    html += '<table class="table table-bordered">';
    html += '<tr><td width="120"><strong>部门 ID</strong></td><td><code>' + id + '</code></td></tr>';
    html += '<tr><td><strong>部门名称</strong></td><td>' + name + '</td></tr>';
    html += '<tr><td><strong>父部门 ID</strong></td><td>' + (parentId || '<span class="text-muted">根部门</span>') + '</td></tr>';
    html += '<tr><td><strong>排序</strong></td><td>' + order + '</td></tr>';
    html += '</table>';
    html += '<div class="text-right">';
    html += '<a class="btn btn-sm green" href="javascript:void(0)" onclick="openEdit(' + id + ',\'' + name.replace(/'/g, "\\'") + '\',' + parentId + ',' + order + ')"><i class="fa fa-edit"></i> 编辑</a> ';
    html += '<a class="btn btn-sm blue" href="javascript:void(0)" onclick="openCreate(' + id + ')"><i class="fa fa-plus"></i> 创建子部门</a> ';
    html += '<a class="btn btn-sm red" href="javascript:void(0)" onclick="deleteDept(' + id + ')"><i class="fa fa-trash"></i> 删除</a>';
    html += '</div>';
    $('#deptDetail').html(html);
}
</script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

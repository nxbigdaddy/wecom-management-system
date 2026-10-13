<?php
/**
 * 管理后台 - 标签管理（细化版：完整CRUD+成员管理）
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '标签管理';
$error = ''; $success = '';
$tags = [];

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'create':
                $result = $wx->addressbook->tagCreate($_POST['tag_name'], (int)($_POST['tag_id'] ?? 0));
                $success = '标签创建成功 (ID: ' . ($result['tagid'] ?? 'N/A') . ')';
                break;
            case 'update':
                $wx->addressbook->tagUpdate((int)$_POST['tag_id'], $_POST['tag_name']);
                $success = '标签更新成功';
                break;
            case 'delete':
                $wx->addressbook->tagDelete((int)$_POST['tag_id']);
                $success = '标签已删除';
                break;
            case 'add_users':
                $wx->addressbook->tagAddUsers((int)$_POST['tag_id'], array_filter(explode(',', $_POST['userids'] ?? '')));
                $success = '已添加成员到标签';
                break;
            case 'remove_users':
                $wx->addressbook->tagDeleteUsers((int)$_POST['tag_id'], array_filter(explode(',', $_POST['userids'] ?? '')));
                $success = '已从标签移除成员';
                break;
        }
    } catch (\Throwable $e) { $error = $e->getMessage(); }
}

// 获取标签列表
try {
    $result = $wx->get('/cgi-bin/tag/list');
    $tags = ($result['errcode'] ?? -1) === 0 ? ($result['taglist'] ?? []) : [];
} catch (\Throwable $e) { $error = $e->getMessage(); }

// 获取标签成员详情
$viewTagId = (int)($_GET['view'] ?? 0);
$tagMembers = [];
if ($viewTagId) {
    try {
        $result = $wx->get('/cgi-bin/tag/get', ['tagid' => $viewTagId]);
        if (($result['errcode'] ?? -1) === 0) {
            $tagMembers = $result['userlist'] ?? [];
        }
    } catch (\Throwable $e) {}
}

require __DIR__ . '/../../layout/header.php';
?>

<?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="row">
                    <div class="col-md-6">
                        <div class="caption"><div class="form-inline">
                            <a class="btn btn-shy btn-default tooltips" href="javascript:void(0)" onclick="openCreate()" data-original-title="创建标签"><i class="fa fa-plus"></i></a>
                            <a class="btn btn-shy btn-default tooltips" href="/contacts/tags" data-original-title="刷新"><i class="fa fa-refresh"></i></a>
                        </div></div>
                    </div>
                </div>
            </div>
            <div class="portlet-body table-responsive">
                <table class="listTable table table-striped table-bordered table-hover dataTable no-footer">
                    <thead>
                        <tr><th width="80" class="text-center">Tag ID</th><th>标签名称</th><th width="100" class="text-center">成员数</th><th width="180" class="text-center">操作</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tags as $tag): ?>
                        <tr>
                            <td class="text-center"><?= $tag['tagid'] ?? '-' ?></td>
                            <td><strong><?= htmlspecialchars($tag['tagname'] ?? '') ?></strong></td>
                            <td class="text-center"><?= (int)($tag['usernum'] ?? 0) ?></td>
                            <td class="text-center">
                                <a href="/contacts/tags?view=<?= $tag['tagid'] ?>" class="btn btn-xs blue"><i class="fa fa-users"></i> 查看成员</a>
                                <a class="btn btn-xs green" href="javascript:void(0)" onclick="editTag(<?= $tag['tagid'] ?>,'<?= htmlspecialchars(addslashes($tag['tagname'])) ?>')"><i class="fa fa-edit"></i> 编辑</a>
                                <a class="btn btn-xs red" href="javascript:void(0)" onclick="delTag(<?= $tag['tagid'] ?>)"><i class="fa fa-trash"></i> 删除</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tags)): ?>
                        <tr><td colspan="4" class="text-center">暂无标签数据</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php if ($viewTagId): ?>
    <div class="col-md-4">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-users"></i> <span class="caption-subject bold uppercase">标签成员 (ID: <?= $viewTagId ?>)</span></div>
            </div>
            <div class="portlet-body">
                <div class="row">
                    <div class="col-md-12" style="margin-bottom:10px">
                        <form method="post" class="form-inline">
                            <input type="hidden" name="action" value="add_users">
                            <input type="hidden" name="tag_id" value="<?= $viewTagId ?>">
                            <input type="text" name="userids" class="form-control input-sm" placeholder="UserID,逗号分隔" style="width:100%;margin-bottom:5px">
                            <button type="submit" class="btn btn-sm green"><i class="fa fa-plus"></i> 添加成员</button>
                        </form>
                        <form method="post" class="form-inline" style="margin-top:5px">
                            <input type="hidden" name="action" value="remove_users">
                            <input type="hidden" name="tag_id" value="<?= $viewTagId ?>">
                            <input type="text" name="userids" class="form-control input-sm" placeholder="UserID,逗号分隔" style="width:100%;margin-bottom:5px">
                            <button type="submit" class="btn btn-sm red"><i class="fa fa-minus"></i> 移除成员</button>
                        </form>
                    </div>
                </div>
                <hr>
                <?php if (empty($tagMembers)): ?>
                    <p class="text-muted text-center">该标签暂无成员</p>
                <?php else: ?>
                <ul class="list-unstyled" style="max-height:300px;overflow-y:auto">
                    <?php foreach ($tagMembers as $m): ?>
                    <li style="padding:5px 0;border-bottom:1px solid #eee">
                        <code><?= htmlspecialchars($m['userid'] ?? '') ?></code>
                        <span class="text-muted"><?= htmlspecialchars($m['name'] ?? '') ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <a href="/contacts/tags" class="btn btn-sm default"><i class="fa fa-arrow-left"></i> 返回标签列表</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- 创建/编辑 Modal -->
<div class="modal fade" id="tagModal">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post">
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title" id="modalTitle">创建标签</h4></div>
            <div class="modal-body">
                <input type="hidden" name="action" id="fAction" value="create">
                <input type="hidden" name="tag_id" id="fTagId">
                <div class="form-group"><label>标签名称</label><input type="text" name="tag_name" id="fTagName" class="form-control" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">取消</button><button type="submit" class="btn btn-primary">保存</button></div>
        </form>
    </div></div>
</div>

<script>
function openCreate(){ $('#modalTitle').text('创建标签'); $('#fAction').val('create'); $('#fTagId').val(''); $('#fTagName').val(''); $('#tagModal').modal('show'); }
function editTag(id,name){ $('#modalTitle').text('编辑标签'); $('#fAction').val('update'); $('#fTagId').val(id); $('#fTagName').val(name); $('#tagModal').modal('show'); }
function delTag(id){ if(confirm('确定删除该标签？')){var f=document.createElement('form');f.method='POST';f.innerHTML='<input name=action value=delete><input name=tag_id value='+id+'>';document.body.appendChild(f);f.submit();} }
</script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

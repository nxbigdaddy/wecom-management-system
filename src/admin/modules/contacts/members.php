<?php
/**
 * 管理后台 - 成员管理（细化版：搜索+批量操作+分页）
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '成员管理';
$error = ''; $success = '';
$members = [];
$deptId = (int)($_GET['department_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$size = 20;
$keyword = trim($_GET['keyword'] ?? '');

// 处理批量操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['batch_action'])) {
    $userids = $_POST['userids'] ?? [];
    try {
        switch ($_POST['batch_action']) {
            case 'delete':
                if ($userids) {
                    $wx->addressbook->userBatchDelete($userids);
                    $success = '已删除 ' . count($userids) . ' 个成员';
                }
                break;
            case 'send_msg':
                // 跳转到消息发送页并预填接收人
                header('Location: /messages/send?touser=' . implode('|', $userids));
                exit;
        }
    } catch (\Throwable $e) { $error = $e->getMessage(); }
}

// 获取部门列表
try {
    $deptResult = $wx->get('/cgi-bin/department/list');
    $departments = ($deptResult['errcode'] ?? -1) === 0 ? ($deptResult['department'] ?? []) : [];
} catch (\Throwable $e) { $departments = []; }

// 获取成员列表（分页）
try {
    $result = $wx->get('/cgi-bin/user/list', [
        'department_id' => $deptId ?: 1,
        'fetch_child'   => 1,
    ]);
    if (($result['errcode'] ?? -1) === 0) {
        $allMembers = $result['userlist'] ?? [];
        // 关键词搜索
        if ($keyword) {
            $allMembers = array_filter($allMembers, function($m) use ($keyword) {
                return stripos($m['name'] ?? '', $keyword) !== false ||
                       stripos($m['userid'] ?? '', $keyword) !== false ||
                       stripos($m['mobile'] ?? '', $keyword) !== false;
            });
        }
        $total = count($allMembers);
        $members = array_slice($allMembers, ($page-1)*$size, $size);
    }
} catch (\Throwable $e) { $error = $e->getMessage(); }

require __DIR__ . '/../../layout/header.php';
?>

<?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row"><div class="col-md-12">
    <div class="portlet light">
        <div class="portlet-title">
            <div class="row">
                <div class="col-md-6 col-sm-6">
                    <div class="caption">
                        <div class="form-inline">
                            <a class="btn btn-shy btn-default tooltips" href="/contacts/members?department_id=<?= $deptId ?>" data-original-title="刷新列表"><i class="fa fa-refresh"></i></a>
                            <select class="form-control" onchange="location.href='/contacts/members?department_id='+this.value+'&keyword=<?= urlencode($keyword) ?>'" style="width:180px">
                                <option value="0">全部部门</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= $deptId===(int)$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <form method="get" class="sch-form" style="display:inline">
                                <input type="hidden" name="department_id" value="<?= $deptId ?>">
                                <input type="text" name="keyword" class="form-control" placeholder="搜索姓名/UserID/手机号" value="<?= htmlspecialchars($keyword) ?>" style="width:250px">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-sm-6">
                    <div class="pull-right">
                        <form method="post" style="display:inline" id="batchForm">
                            <select name="batch_action" class="form-control" style="display:inline;width:auto">
                                <option value="">批量操作</option>
                                <option value="delete">批量删除</option>
                                <option value="send_msg">批量发消息</option>
                            </select>
                            <button type="submit" class="btn btn-shy btn-default" onclick="return confirm('确定执行批量操作？')"><i class="fa fa-check"></i> 执行</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="portlet-body table-responsive">
            <form id="memberForm">
            <table class="listTable table table-striped table-bordered table-hover dataTable no-footer">
                <thead>
                    <tr>
                        <th width="30"><input type="checkbox" id="checkAll" onchange="$('input[name=userids[]]').prop('checked',this.checked)"></th>
                        <th>头像</th><th>UserID</th><th>姓名</th><th>部门</th><th>手机号</th><th>职位</th><th width="80" class="text-center">状态</th><th width="150" class="text-center">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                    <tr>
                        <td class="text-center"><input type="checkbox" name="userids[]" value="<?= htmlspecialchars($m['userid']) ?>"></td>
                        <td><?= !empty($m['avatar'])?'<img src="'.htmlspecialchars($m['avatar']).'" class="img-circle" width="32">':'<i class="fa fa-user-circle fa-2x text-muted"></i>' ?></td>
                        <td><code><?= htmlspecialchars($m['userid']) ?></code></td>
                        <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                        <td><?= htmlspecialchars(is_array($m['department']??null)?implode(', ', $m['department']):($m['department']??'-')) ?></td>
                        <td><?= htmlspecialchars($m['mobile'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($m['position'] ?? '-') ?></td>
                        <td class="text-center">
                            <?php $st=$m['status']??1; ?>
                            <?php if($st===1):?><span class="label label-sm label-success">已激活</span>
                            <?php elseif($st===2):?><span class="label label-sm label-danger">已禁用</span>
                            <?php elseif($st===4):?><span class="label label-sm label-warning">未激活</span>
                            <?php else:?><span class="label label-sm label-default"><?=$st?></span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="/contacts/members/<?= urlencode($m['userid']) ?>" class="btn btn-xs blue"><i class="fa fa-eye"></i> 详情</a>
                            <a href="/messages/send?touser=<?= urlencode($m['userid']) ?>" class="btn btn-xs green"><i class="fa fa-paper-plane"></i> 发消息</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($members)): ?>
                    <tr><td colspan="9" class="text-center">暂无成员数据</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </form>
        </div>
        <?php if ($total > $size): ?>
        <div class="portlet-footer">
            <div class="pull-right">
                <ul class="pagination" style="margin:0">
                    <?php $totalPages = ceil($total / $size); ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="<?= $i === $page ? 'active' : '' ?>">
                            <a href="?department_id=<?= $deptId ?>&keyword=<?= urlencode($keyword) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
                <span class="text-muted" style="margin-left:10px">共 <?= $total ?> 条</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div></div>

<script>
$(function(){
    // 批量操作表单提交
    $('#batchForm').on('submit', function(){
        var action = $('[name=batch_action]').val();
        var checked = $('input[name="userids[]"]:checked').length;
        if (!action) { alert('请选择批量操作'); return false; }
        if (!checked) { alert('请至少选择一个成员'); return false; }
        if (action === 'delete' && !confirm('确定删除 ' + checked + ' 个成员？')) return false;
        
        // 将选中的userid复制到form
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input name="batch_action" value="'+action+'">';
        $('input[name="userids[]"]:checked').each(function(){
            form.innerHTML += '<input name="userids[]" value="'+this.value+'">';
        });
        document.body.appendChild(form);
        form.submit();
        return false;
    });
});
</script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

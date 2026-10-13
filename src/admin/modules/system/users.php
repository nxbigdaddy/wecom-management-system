<?php
/**
 * 管理后台 - 管理员账号管理
 * 
 * 功能：管理员列表、新增、编辑、启用/禁用、删除
 * 需要超级管理员权限
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn() || !Session::isSuperAdmin()) { header('Location: /login'); exit; }

$title = '管理员管理';
$error = ''; $success = '';
$users = [];
$editUser = null;

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        $db = $wx->getDatabase();
        switch ($action) {
            case 'create':
                $username = trim($_POST['username'] ?? '');
                $password = YOUR_PASSWORD'password'] ?? '';
                if (empty($username) || empty($password)) throw new \RuntimeException('用户名和密码不能为空');
                if (strlen($password) < 6) throw new \RuntimeException('密码至少6位');
                $exists = $db->selectValue('SELECT COUNT(*) FROM wxwork_admin_user WHERE username=?', [$username]);
                if ($exists) throw new \RuntimeException("用户名 '{$username}' 已存在");
                $db->insertRow('wxwork_admin_user', [
                    'username' => $username, 'password' => password_hash($password, PASSWORD_BCRYPT),
                    'realname' => $_POST['realname'] ?? '', 'role' => $_POST['role'] ?? 'admin', 'is_enabled' => 1,
                ]);
                $success = "管理员 '{$username}' 创建成功";
                break;

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $data = [];
                if ($_POST['realname'] ?? '') $data['realname'] = $_POST['realname'];
                if ($_POST['role'] ?? '') $data['role'] = $_POST['role'];
                if ($_POST['password'] ?? '') {
                    if (strlen($_POST['password']) < 6) throw new \RuntimeException('密码至少6位');
                    $data['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
                }
                if (!empty($data)) $db->updateRow('wxwork_admin_user', $data, 'id=?', [$id]);
                $success = '管理员信息已更新';
                break;

            case 'toggle':
                $id = (int)($_POST['id'] ?? 0);
                $user = $db->selectOne('SELECT * FROM wxwork_admin_user WHERE id=?', [$id]);
                if (!$user) throw new \RuntimeException('用户不存在');
                if ($user['role'] === 'superadmin') throw new \RuntimeException('不能禁用超级管理员');
                $newStatus = $user['is_enabled'] ? 0 : 1;
                $db->execute('UPDATE wxwork_admin_user SET is_enabled=? WHERE id=?', [$newStatus, $id]);
                $success = $newStatus ? '账号已启用' : '账号已禁用';
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $user = $db->selectOne('SELECT * FROM wxwork_admin_user WHERE id=?', [$id]);
                if (!$user) throw new \RuntimeException('用户不存在');
                if ($user['role'] === 'superadmin') throw new \RuntimeException('不能删除超级管理员');
                if ($user['id'] == Session::getUserId()) throw new \RuntimeException('不能删除自己');
                $db->execute('DELETE FROM wxwork_admin_user WHERE id=?', [$id]);
                $success = '管理员已删除';
                break;
        }
    } catch (\Throwable $e) { $error = $e->getMessage(); }
}

// 加载列表
try {
    $db = $wx->getDatabase();
    $users = $db->select('SELECT * FROM wxwork_admin_user ORDER BY id ASC');
} catch (\Throwable $e) { $error = $e->getMessage(); }

// 编辑模式
if (isset($_GET['edit'])) {
    foreach ($users as $u) {
        if ((int)$u['id'] === (int)$_GET['edit']) { $editUser = $u; break; }
    }
}

require __DIR__ . '/../../layout/header.php';
?>

<style>
.user-avatar-sm { width: 36px; height: 36px; border-radius: 50%; background: #2196f3; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
</style>

<?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row">
    <!-- 列表 -->
    <div class="col-md-7">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-users-cog"></i> <span class="caption-subject bold uppercase">管理员列表</span></div>
                <div class="actions"><button class="btn btn-sm btn-success" onclick="showCreate()"><i class="fa fa-plus"></i> 新增管理员</button></div>
            </div>
            <div class="portlet-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead><tr><th>ID</th><th>用户名</th><th>姓名</th><th>角色</th><th width="70">状态</th><th>最后登录</th><th width="140">操作</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td>
                                <span class="user-avatar-sm" style="font-size:11px;margin-right:6px"><?= mb_substr($u['realname'] ?: $u['username'], 0, 2) ?></span>
                                <strong><?= htmlspecialchars($u['username']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($u['realname'] ?: '-') ?></td>
                            <td>
                                <?php if (($u['role']??'admin') === 'superadmin'): ?>
                                <span class="label label-sm label-danger">超级管理员</span>
                                <?php else: ?>
                                <span class="label label-sm label-info">管理员</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($u['is_enabled']): ?>
                                <span class="label label-sm label-success">启用</span>
                                <?php else: ?>
                                <span class="label label-sm label-default">禁用</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:11px"><?= $u['last_login_at'] ? date('m-d H:i', strtotime($u['last_login_at'])) : '从未登录' ?></td>
                            <td class="text-center">
                                <a href="?edit=<?= $u['id'] ?>" class="btn btn-xs blue"><i class="fa fa-edit"></i></a>
                                <?php if (($u['role']??'') !== 'superadmin' && $u['id'] != Session::getUserId()): ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button class="btn btn-xs <?= $u['is_enabled']?'warning':'success' ?>" title="<?= $u['is_enabled']?'禁用':'启用' ?>">
                                        <i class="fa fa-<?= $u['is_enabled']?'pause':'play' ?>"></i>
                                    </button>
                                </form>
                                <form method="post" style="display:inline" onsubmit="return confirm('确定删除该管理员？')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button class="btn btn-xs red"><i class="fa fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 创建/编辑表单 -->
    <div class="col-md-5">
        <?php if ($editUser): ?>
        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-edit"></i> 编辑管理员: <?= htmlspecialchars($editUser['username']) ?></div></div>
            <div class="portlet-body">
                <form method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
                    <div class="form-group"><label>用户名</label><input type="text" class="form-control" value="<?= htmlspecialchars($editUser['username']) ?>" disabled></div>
                    <div class="form-group"><label>姓名</label><input type="text" name="realname" class="form-control" value="<?= htmlspecialchars($editUser['realname'] ?? '') ?>"></div>
                    <div class="form-group"><label>角色</label>
                        <select name="role" class="form-control">
                            <option value="admin" <?= ($editUser['role']??'')==='admin'?'selected':'' ?>>管理员</option>
                            <option value="superadmin" <?= ($editUser['role']??'')==='superadmin'?'selected':'' ?>>超级管理员</option>
                        </select>
                    </div>
                    <div class="form-group"><label>新密码（留空不修改）</label><input type="password" name="YOUR_PASSWORD" class="form-control" placeholder="至少6位" minlength="6"></div>
                    <button class="btn btn-primary"><i class="fa fa-save"></i> 保存修改</button>
                    <a href="/system/users" class="btn btn-default">取消</a>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="portlet light" id="createForm">
            <div class="portlet-title"><div class="caption"><i class="fa fa-user-plus"></i> 新增管理员</div></div>
            <div class="portlet-body">
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group"><label>用户名 <span class="text-danger">*</span></label><input type="text" name="username" class="form-control" placeholder="登录用户名" required></div>
                    <div class="form-group"><label>姓名</label><input type="text" name="realname" class="form-control" placeholder="真实姓名"></div>
                    <div class="form-group"><label>密码 <span class="text-danger">*</span></label><input type="password" name="YOUR_PASSWORD" class="form-control" placeholder="至少6位" required minlength="6"></div>
                    <div class="form-group"><label>角色</label>
                        <select name="role" class="form-control"><option value="admin">管理员</option><option value="superadmin">超级管理员</option></select>
                    </div>
                    <button class="btn btn-success"><i class="fa fa-plus"></i> 创建</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-shield"></i> 权限说明</div></div>
            <div class="portlet-body" style="font-size:13px">
                <ul>
                    <li><strong>超级管理员</strong>：拥有所有权限，可管理其他管理员</li>
                    <li><strong>管理员</strong>：可使用所有功能，但不能管理管理员账号</li>
                    <li>默认账号：<code>admin / admin123</code>（首次登录后请修改）</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function showCreate() { document.getElementById('createForm')?.scrollIntoView({behavior:'smooth'}); }
</script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

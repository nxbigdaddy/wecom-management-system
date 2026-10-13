<?php
/**
 * 管理后台 - 客户列表（细化版：DB数据+搜索+筛选）
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '客户列表';
$error = '';
$customers = [];
$page = max(1, (int)($_GET['page'] ?? 1));
$size = 20;
$keyword = trim($_GET['keyword'] ?? '');
$filterType = $_GET['type'] ?? '';
$filterGender = $_GET['gender'] ?? '';

try {
    $db = $wx->getDatabase();
    $where = 'ec.is_deleted = 0';
    $params = [];
    if ($keyword) { $where .= ' AND (ec.name LIKE ? OR ec.external_userid LIKE ? OR ef.remark LIKE ?)'; $params[] = "%$keyword%"; $params[] = "%$keyword%"; $params[] = "%$keyword%"; }
    if ($filterType) { $where .= ' AND ec.type = ?'; $params[] = (int)$filterType; }
    if ($filterGender !== '') { $where .= ' AND ec.gender = ?'; $params[] = (int)$filterGender; }
    
    $total = (int)$db->selectValue("SELECT COUNT(*) FROM wxwork_external_contact ec LEFT JOIN wxwork_external_follow ef ON ec.external_userid=ef.external_userid WHERE $where", $params);
    
    // CSV 导出
    if (isset($_GET['export'])) {
        $allData = $db->select(
            "SELECT ec.*, ef.userid as follow_userid, ef.remark, ef.add_time 
             FROM wxwork_external_contact ec 
             LEFT JOIN wxwork_external_follow ef ON ec.external_userid=ef.external_userid AND ef.is_deleted=0
             WHERE $where ORDER BY ec.id DESC",
            $params
        );
        $headers = ['ExternalUserID', '名称', '性别', '类型', '企业', '跟进人', '备注', '添加时间'];
        $rows = array_map(function($c) {
            return [
                'external_userid' => $c['external_userid'], '名称' => $c['name'],
                '性别' => [0=>'?',1=>'男',2=>'女'][$c['gender']??0]??'?',
                '类型' => ($c['type']??1)==1?'微信':'企业', '企业' => $c['corp_name'] ?? '',
                '跟进人' => $c['follow_userid'] ?? '', '备注' => $c['remark'] ?? '',
                '添加时间' => $c['add_time'] ?? '',
            ];
        }, $allData);
        exportCsv($headers, $rows, '客户列表_' . date('Ymd'));
    }
    $customers = $db->select(
        "SELECT ec.*, ef.userid as follow_userid, ef.remark, ef.add_time 
         FROM wxwork_external_contact ec 
         LEFT JOIN wxwork_external_follow ef ON ec.external_userid=ef.external_userid AND ef.is_deleted=0
         WHERE $where 
         ORDER BY ec.id DESC LIMIT " . (($page-1)*$size) . ", $size",
        $params
    );
} catch (\Throwable $e) { $error = $e->getMessage(); }

require __DIR__ . '/../../layout/header.php';
?>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row"><div class="col-md-12">
    <div class="portlet light">
        <div class="portlet-title">
            <div class="row">
                <div class="col-md-8 col-sm-8">
                    <div class="caption">
                        <div class="form-inline">
                            <a class="btn btn-shy btn-default tooltips" href="/external/contacts" data-original-title="重置筛选"><i class="fa fa-refresh"></i></a>
                            <select class="form-control input-sm" onchange="location.href='?type='+this.value+'&gender=<?= $filterGender ?>&keyword=<?= urlencode($keyword) ?>'" style="width:120px">
                                <option value="">全部类型</option>
                                <option value="1" <?= $filterType==='1'?'selected':'' ?>>微信用户</option>
                                <option value="2" <?= $filterType==='2'?'selected':'' ?>>企业微信用户</option>
                            </select>
                            <select class="form-control input-sm" onchange="location.href='?type=<?= $filterType ?>&gender='+this.value+'&keyword=<?= urlencode($keyword) ?>'" style="width:100px">
                                <option value="">全部性别</option>
                                <option value="1" <?= $filterGender==='1'?'selected':'' ?>>男</option>
                                <option value="2" <?= $filterGender==='2'?'selected':'' ?>>女</option>
                                <option value="0" <?= $filterGender==='0'?'selected':'' ?>>未知</option>
                            </select>
                            <form method="get" class="sch-form" style="display:inline">
                                <input type="hidden" name="type" value="<?= $filterType ?>">
                                <input type="hidden" name="gender" value="<?= $filterGender ?>">
                                <input type="text" name="keyword" class="form-control input-sm" placeholder="搜索客户名/ID/备注" value="<?= htmlspecialchars($keyword) ?>" style="width:220px">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-4">
                    <div class="pull-right">
                        <a href="/external/transfer" class="btn btn-sm blue"><i class="fa fa-exchange"></i> 离职继承</a>
                        <a href="/external/contacts?keyword=<?= urlencode($keyword) ?>&type=<?= $filterType ?>&gender=<?= $filterGender ?>&export=1" class="btn btn-sm btn-success"><i class="fa fa-download"></i> 导出 CSV</a>
                        <span class="text-muted" style="margin-left:10px">共 <?= $total ?> 条</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="portlet-body table-responsive">
            <table class="listTable table table-striped table-bordered table-hover dataTable no-footer">
                <thead>
                    <tr><th>头像</th><th>客户名称</th><th>ExternalID</th><th>跟进人</th><th>备注</th><th>类型</th><th width="60" class="text-center">性别</th><th>企业</th><th width="100" class="text-center">操作</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c): ?>
                    <tr>
                        <td><?= !empty($c['avatar'])?'<img src="'.htmlspecialchars($c['avatar']).'" class="img-circle" width="32">':'<i class="fa fa-user fa-2x text-muted"></i>' ?></td>
                        <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                        <td><code style="font-size:11px"><?= htmlspecialchars($c['external_userid']) ?></code></td>
                        <td><?= htmlspecialchars($c['follow_userid'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($c['remark'] ?? '-') ?></td>
                        <td><?= ($c['type']??1)===1?'<span class="label label-sm label-info">微信</span>':'<span class="label label-sm label-success">企业</span>' ?></td>
                        <td class="text-center"><?= ['0'=>'?','1'=>'男','2'=>'女'][$c['gender']??0] ?? '?' ?></td>
                        <td><?= htmlspecialchars($c['corp_name'] ?? '-') ?></td>
                        <td class="text-center">
                            <a href="/external/contacts/detail?external_userid=<?= urlencode($c['external_userid']) ?>" class="btn btn-xs blue"><i class="fa fa-eye"></i> 详情</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($customers)): ?>
                    <tr><td colspan="9" class="text-center">暂无客户数据，请先运行同步脚本同步客户数据</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total > $size): ?>
        <div class="portlet-footer">
            <div class="pull-right">
                <ul class="pagination" style="margin:0">
                    <?php $totalPages = ceil($total / $size); ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="<?= $i === $page ? 'active' : '' ?>">
                            <a href="?type=<?= $filterType ?>&gender=<?= $filterGender ?>&keyword=<?= urlencode($keyword) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div></div>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

<?php
/**
 * 管理后台 - 客户群列表（细化版）
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '客户群列表';
$error = '';
$groups = [];
$detail = null;

// 获取群详情
if (isset($_GET['detail'])) {
    try {
        $result = $wx->post('/cgi-bin/externalcontact/groupchat/get', ['chat_id' => $_GET['detail']]);
        if (($result['errcode'] ?? -1) === 0) {
            $detail = $result['group_chat'] ?? [];
        }
    } catch (\Throwable $e) { $error = $e->getMessage(); }
}

// 获取群列表
try {
    $result = $wx->post('/cgi-bin/externalcontact/groupchat/list', [
        'status_filter' => (int)($_GET['status'] ?? 0),
        'limit' => 100,
    ]);
    if (($result['errcode'] ?? -1) === 0) {
        $groups = $result['group_chat_list'] ?? [];
    }
} catch (\Throwable $e) { $error = $e->getMessage(); }

require __DIR__ . '/../../layout/header.php';
?>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row">
    <?php if (!$detail): ?>
    <div class="col-md-12">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="row">
                    <div class="col-md-8 col-sm-8">
                        <div class="caption">
                            <div class="form-inline">
                                <a class="btn btn-shy btn-default tooltips" href="/external/groups" data-original-title="重置"><i class="fa fa-refresh"></i></a>
                                <select class="form-control input-sm" onchange="location.href='?status='+this.value" style="width:120px">
                                    <option value="0" <?= !isset($_GET['status'])||$_GET['status']=='0'?'selected':'' ?>>全部状态</option>
                                    <option value="0" <?= ($_GET['status']??'')==='0'?'selected':'' ?>>正常</option>
                                    <option value="1" <?= ($_GET['status']??'')==='1'?'selected':'' ?>>离职待继承</option>
                                    <option value="2" <?= ($_GET['status']??'')==='2'?'selected':'' ?>>离职继承中</option>
                                    <option value="3" <?= ($_GET['status']??'')==='3'?'selected':'' ?>>离职继承完成</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="portlet-body table-responsive">
                <table class="listTable table table-striped table-bordered table-hover dataTable no-footer">
                    <thead>
                        <tr><th width="80" class="text-center">ChatID</th><th>群名称</th><th>群主</th><th width="80" class="text-center">成员数</th><th width="100" class="text-center">状态</th><th width="120" class="text-center">创建时间</th><th width="100" class="text-center">操作</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $g): ?>
                        <tr>
                            <td class="text-center"><code style="font-size:10px"><?= htmlspecialchars(mb_substr($g['chat_id'] ?? '-', 0, 10)) ?>...</code></td>
                            <td><strong><?= htmlspecialchars($g['name'] ?? '-') ?></strong></td>
                            <td><?= htmlspecialchars($g['owner'] ?? '-') ?></td>
                            <td class="text-center"><?= (int)($g['member_count'] ?? 0) ?></td>
                            <td class="text-center">
                                <?php $st = $g['status'] ?? 0; ?>
                                <?php if ($st === 0): ?><span class="label label-sm label-success">正常</span>
                                <?php elseif ($st === 1): ?><span class="label label-sm label-warning">待继承</span>
                                <?php elseif ($st === 2): ?><span class="label label-sm label-info">继承中</span>
                                <?php elseif ($st === 3): ?><span class="label label-sm label-default">继承完成</span>
                                <?php else: ?><span class="label label-sm label-default"><?= $st ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= date('Y-m-d', (int)($g['create_time'] ?? 0)) ?></td>
                            <td class="text-center">
                                <a href="/external/groups?detail=<?= urlencode($g['chat_id']) ?>" class="btn btn-xs blue"><i class="fa fa-eye"></i> 详情</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($groups)): ?>
                        <tr><td colspan="7" class="text-center">暂无客户群数据</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-md-12">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-comments"></i> <span class="caption-subject bold uppercase">客户群详情</span>
                </div>
                <div class="actions">
                    <a href="/external/groups" class="btn btn-sm default"><i class="fa fa-arrow-left"></i> 返回列表</a>
                </div>
            </div>
            <div class="portlet-body">
                <div class="row">
                    <div class="col-md-6">
                        <h4><i class="fa fa-comments text-primary"></i> <?= htmlspecialchars($detail['name'] ?? '未命名群') ?></h4>
                        <hr>
                        <table class="table table-bordered">
                            <tr><td width="120"><strong>Chat ID</strong></td><td><code><?= htmlspecialchars($detail['chat_id'] ?? '-') ?></code></td></tr>
                            <tr><td><strong>群主</strong></td><td><?= htmlspecialchars($detail['owner'] ?? '-') ?></td></tr>
                            <tr><td><strong>成员数</strong></td><td><?= (int)($detail['member_count'] ?? 0) ?></td></tr>
                            <tr><td><strong>创建时间</strong></td><td><?= date('Y-m-d H:i:s', (int)($detail['create_time'] ?? 0)) ?></td></tr>
                            <tr><td><strong>状态</strong></td><td>
                                <?php $st = $detail['status'] ?? 0; ?>
                                <?php if ($st === 0): ?><span class="label label-sm label-success">正常</span>
                                <?php elseif ($st === 1): ?><span class="label label-sm label-warning">离职待继承</span>
                                <?php elseif ($st === 2): ?><span class="label label-sm label-info">离职继承中</span>
                                <?php elseif ($st === 3): ?><span class="label label-sm label-default">离职继承完成</span>
                                <?php endif; ?>
                            </td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <?php if (!empty($detail['notice'])): ?>
                        <div class="well">
                            <h5><i class="fa fa-bullhorn"></i> 群公告</h5>
                            <hr>
                            <p><?= nl2br(htmlspecialchars($detail['notice'])) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($detail['member_list'])): ?>
                        <h5><i class="fa fa-users"></i> 群成员 (<?= count($detail['member_list']) ?>)</h5>
                        <hr>
                        <div style="max-height:300px;overflow-y:auto">
                            <?php foreach ($detail['member_list'] as $m): ?>
                            <div class="media" style="margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid #eee">
                                <div class="media-body">
                                    <h6 class="media-heading"><?= htmlspecialchars($m['name'] ?? $m['userid'] ?? '未知') ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($m['userid'] ?? '') ?></small>
                                    <?php if (!empty($m['type'])): ?>
                                        <span class="label label-sm label-info"><?= $m['type'] === 1 ? '微信用户' : '企业用户' ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

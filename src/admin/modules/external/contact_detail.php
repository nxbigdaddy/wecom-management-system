<?php
/**
 * 管理后台 - 客户详情（优化版）
 * 
 * 功能：客户基本信息、跟进信息、备注编辑、标签管理
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '客户详情';
$error = ''; $success = '';
$customer = null;
$followInfo = null;
$members = [];

$euid = $_GET['external_userid'] ?? '';
$followUserid = $_GET['userid'] ?? '';

// 获取成员列表（用于跟进人修改）
try {
    $result = $wx->get('/cgi-bin/user/simplelist', ['department_id' => 1, 'fetch_child' => 1]);
    $members = ($result['errcode'] ?? -1) === 0 ? ($result['userlist'] ?? []) : [];
} catch (\Throwable $e) {}

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $euid) {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'update_remark':
                $remark = trim($_POST['remark'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $remarkCorpName = trim($_POST['remark_corp_name'] ?? '');
                $remarkMobiles = trim($_POST['remark_mobiles'] ?? '');
                
                $wx->externalContact->remark([
                    'userid'           => $followUserid,
                    'external_userid'  => $euid,
                    'remark'           => $remark,
                    'description'      => $description,
                    'remark_company'   => $remarkCorpName,
                    'remark_mobiles'   => $remarkMobiles ? explode(',', str_replace('，', ',', $remarkMobiles)) : [],
                ]);
                $success = '备注信息已更新';
                break;

            case 'update_tags':
                $addTags = array_filter(array_map('trim', explode(',', $_POST['add_tags'] ?? '')));
                $removeTags = array_filter(array_map('trim', explode(',', $_POST['remove_tags'] ?? '')));
                
                if (!empty($addTags) || !empty($removeTags)) {
                    $wx->externalContact->markTag($followUserid, $euid, 
                        array_map(fn($t) => ['tag_id' => $t], $addTags),
                        array_map(fn($t) => ['tag_id' => $t], $removeTags)
                    );
                    $success = '标签已更新';
                }
                break;
        }
    } catch (\Throwable $e) { $error = $e->getMessage(); }
}

// 获取客户信息
if ($euid) {
    try {
        $result = $wx->get('/cgi-bin/externalcontact/get', ['external_userid' => $euid]);
        if (($result['errcode'] ?? -1) === 0) {
            $customer = $result['external_contact'] ?? [];
            $followInfo = $result['follow_user'] ?? [];
            if (!$followUserid && !empty($followInfo)) {
                $followUserid = $followInfo['userid'] ?? '';
            }
        } else {
            $error = $result['errmsg'] ?? '获取客户详情失败';
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$addWays = [
    0 => '未知来源', 1 => '扫描二维码', 2 => '搜索手机号', 3 => '名片分享',
    4 => '群聊', 5 => '手机通讯录', 6 => '微信联系人', 7 => '第三方应用',
    8 => '邮件', 9 => '视频号', 10 => '日程', 11 => '会议',
    201 => '内部成员共享', 202 => '管理员/负责人分配',
];

require __DIR__ . '/../../layout/header.php';
?>

<style>
.info-section { margin-bottom: 5px; }
.info-label { color: #888; font-size: 12px; }
.info-value { font-size: 14px; margin-bottom: 12px; }
.tag-cloud { display: flex; flex-wrap: wrap; gap: 4px; }
.tag-item { display: inline-block; padding: 3px 10px; background: #e3f2fd; color: #1565c0; border-radius: 12px; font-size: 12px; }
</style>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($customer): ?>
<div class="row">
    <!-- 客户基本信息 -->
    <div class="col-md-4">
        <div class="portlet light">
            <div class="portlet-body text-center">
                <?php if (!empty($customer['avatar'])): ?>
                    <img src="<?= htmlspecialchars($customer['avatar']) ?>" class="img-circle" width="90" style="border:3px solid #e0e0e0">
                <?php else: ?><i class="fa fa-user-circle fa-5x text-muted"></i><?php endif; ?>
                <h3 style="margin-top:10px"><?= htmlspecialchars($customer['name']) ?></h3>
                <span class="label label-<?= ($customer['type'] ?? 1) === 1 ? 'info' : 'success' ?>">
                    <?= ($customer['type'] ?? 1) === 1 ? '微信用户' : '企业微信用户' ?>
                </span>
                <?php if (!empty($customer['gender'])): ?>
                <span class="text-muted" style="margin-left:5px"><?= ['1'=>'♂','2'=>'♀'][$customer['gender']] ?? '' ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-info-circle"></i> 基本信息</div></div>
            <div class="portlet-body">
                <div class="info-section">
                    <div class="info-label">External UserID</div>
                    <div class="info-value"><code><?= htmlspecialchars($customer['external_userid'] ?? '-') ?></code></div>
                    
                    <div class="info-label">姓名</div>
                    <div class="info-value"><?= htmlspecialchars($customer['name'] ?? '-') ?></div>
                    
                    <div class="info-label">企业</div>
                    <div class="info-value"><?= htmlspecialchars($customer['corp_name'] ?? '-') ?></div>
                    
                    <div class="info-label">企业全称</div>
                    <div class="info-value"><?= htmlspecialchars($customer['corp_full_name'] ?? '-') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 跟进信息 + 编辑 -->
    <div class="col-md-8">
        <?php if ($followInfo): ?>
        <!-- 跟进信息只读 -->
        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-user-plus"></i> 跟进关系</div></div>
            <div class="portlet-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-label">跟进人</div>
                        <div class="info-value"><strong><?= htmlspecialchars($followInfo['userid'] ?? '-') ?></strong></div>
                        
                        <div class="info-label">添加时间</div>
                        <div class="info-value"><?= date('Y-m-d H:i', (int)($followInfo['createtime'] ?? 0)) ?></div>
                        
                        <div class="info-label">添加方式</div>
                        <div class="info-value"><?= $addWays[$followInfo['add_way'] ?? 0] ?? ('方式' . ($followInfo['add_way'] ?? '?')) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">备注</div>
                        <div class="info-value"><?= htmlspecialchars($followInfo['remark'] ?? '-') ?></div>
                        
                        <div class="info-label">描述</div>
                        <div class="info-value"><?= htmlspecialchars($followInfo['description'] ?? '-') ?></div>
                        
                        <div class="info-label">备注企业名</div>
                        <div class="info-value"><?= htmlspecialchars($followInfo['remark_corp_name'] ?? '-') ?></div>
                    </div>
                </div>

                <?php if (!empty($followInfo['tags'])): ?>
                <hr>
                <div class="info-label">客户标签</div>
                <div class="tag-cloud">
                    <?php 
                    $tags = $followInfo['tags'] ?? [];
                    if (is_string($tags)) $tags = json_decode($tags, true);
                    foreach ($tags as $tag): 
                        $tagName = is_array($tag) ? ($tag['tag_name'] ?? $tag['tag_id'] ?? '') : $tag;
                    ?>
                    <span class="tag-item"><?= htmlspecialchars($tagName) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 编辑备注 -->
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-edit"></i> <span class="caption-subject bold uppercase">编辑备注信息</span></div>
            </div>
            <div class="portlet-body">
                <form method="post" class="form-horizontal">
                    <input type="hidden" name="action" value="update_remark">
                    <div class="form-group">
                        <label class="col-md-3 control-label">备注名称</label>
                        <div class="col-md-8">
                            <input type="text" name="remark" class="form-control" 
                                   value="<?= htmlspecialchars($followInfo['remark'] ?? '') ?>"
                                   placeholder="给客户设置备注名">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">描述</label>
                        <div class="col-md-8">
                            <textarea name="description" class="form-control" rows="2" 
                                      placeholder="客户描述信息"><?= htmlspecialchars($followInfo['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">企业名称</label>
                        <div class="col-md-8">
                            <input type="text" name="remark_corp_name" class="form-control" 
                                   value="<?= htmlspecialchars($followInfo['remark_corp_name'] ?? '') ?>"
                                   placeholder="备注企业名">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">手机号</label>
                        <div class="col-md-8">
                            <input type="text" name="remark_mobiles" class="form-control" 
                                   value="<?= htmlspecialchars($followInfo['remark_mobiles'] ?? '') ?>"
                                   placeholder="备注手机号，逗号分隔">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-md-offset-3 col-md-8">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> 保存备注</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 编辑标签 -->
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-tags"></i> <span class="caption-subject bold uppercase">编辑客户标签</span></div>
            </div>
            <div class="portlet-body">
                <form method="post">
                    <input type="hidden" name="action" value="update_tags">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fa fa-plus-circle text-success"></i> 添加标签（tag_id，逗号分隔）</label>
                                <input type="text" name="add_tags" class="form-control" placeholder="例如：tag1,tag2">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><i class="fa fa-minus-circle text-danger"></i> 移除标签（tag_id，逗号分隔）</label>
                                <input type="text" name="remove_tags" class="form-control" placeholder="例如：tag3,tag4">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-tags"></i> 更新标签</button>
                    <span class="text-muted" style="margin-left:10px;font-size:12px">
                        标签由企业管理员在客户联系中统一管理
                    </span>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif (!$euid): ?>
    <div class="alert alert-info">请从 <a href="/external/contacts">客户列表</a> 点击查看详情</div>
<?php endif; ?>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

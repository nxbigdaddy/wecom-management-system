<?php
/**
 * 管理后台 - 智能工作流
 * 
 * 基于智能表格模板的工作流管理中心。
 * 展示已启用的工作流、快速创建新工作流、查看工作流状态。
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '智能工作流';
// 加载模板数据
$tplFile = __DIR__ . '/../../data/smartsheet_templates.php';
$templates = file_exists($tplFile) ? require $tplFile : [];

require __DIR__ . '/../../layout/header.php';
?>

<style>
.wf-card {
    border: 1px solid #e5e5e5; border-radius: 8px; padding: 0; overflow: hidden;
    transition: all 0.25s; background: #fff; margin-bottom: 16px;
}
.wf-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); transform: translateY(-2px); }
.wf-card .wf-header { padding: 16px 20px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 12px; }
.wf-card .wf-header .wf-icon { font-size: 28px; width: 40px; text-align: center; }
.wf-card .wf-header .wf-title { font-size: 16px; font-weight: 600; flex: 1; }
.wf-card .wf-body { padding: 12px 20px; }
.wf-card .wf-body .wf-desc { color: #666; font-size: 13px; margin-bottom: 8px; }
.wf-card .wf-body .wf-meta { font-size: 11px; color: #999; }
.wf-card .wf-actions { padding: 12px 20px; background: #fafafa; border-top: 1px solid #f0f0f0; }
</style>

<div class="page-head">
    <div class="page-title"><h1>智能工作流 <small>基于智能表格的自动化工作流模板</small></h1></div>
</div>

<?php if (empty($templates)): ?>
<div class="alert alert-warning">
    <i class="fa fa-warning"></i> 未找到工作流模板文件，请确保 <code>src/admin/data/smartsheet_templates.php</code> 存在。
</div>
<?php else: ?>
<div class="row">
    <div class="col-md-8">
        <div class="row">
            <?php foreach ($templates as $tid => $tpl): 
                $totalSheets = count($tpl['sheets']);
                $totalFields = array_sum(array_map(fn($s) => count($s['fields']), $tpl['sheets']));
            ?>
            <div class="col-md-6">
                <div class="wf-card">
                    <div class="wf-header">
                        <span class="wf-icon"><i class="fa <?= $tpl['icon'] ?>"></i></span>
                        <span class="wf-title"><?= htmlspecialchars($tpl['name']) ?></span>
                    </div>
                    <div class="wf-body">
                        <div class="wf-desc"><?= htmlspecialchars($tpl['desc']) ?></div>
                        <div class="wf-meta">
                            <i class="fa fa-table"></i> <?= $totalSheets ?> 个子表 ·
                            <i class="fa fa-columns"></i> <?= $totalFields ?> 个字段
                        </div>
                    </div>
                    <div class="wf-actions">
                        <form method="get" action="/smartsheet" style="display:inline">
                            <input type="hidden" name="tab" value="docs">
                            <input type="hidden" name="tpl" value="<?= $tid ?>">
                            <button class="btn btn-sm btn-success"><i class="fa fa-plus"></i> 创建</button>
                        </form>
                        <span class="text-muted" style="font-size:11px;margin-left:8px">
                            自动建立子表和字段
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-md-4">
        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-info-circle"></i> 关于工作流</div></div>
            <div class="portlet-body" style="font-size:13px">
                <p>智能工作流基于企业微信智能表格，通过预设模板快速建立数据结构，配合 AI Agent 可实现自动化数据录入和分析。</p>
                <hr>
                <h6>使用方式</h6>
                <ol style="padding-left:16px">
                    <li>点击一个工作流的"创建"按钮</li>
                    <li>系统自动创建智能表格及子表/字段</li>
                    <li>在智能表格中查看和管理数据</li>
                    <li>通过 Webhook 接口推送 AI 处理结果</li>
                </ol>
                <hr>
                <h6>可用模板</h6>
                <ul style="padding-left:16px;color:#666">
                    <?php foreach ($templates as $tpl): ?>
                    <li><?= htmlspecialchars($tpl['name']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

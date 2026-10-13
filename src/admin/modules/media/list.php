<?php
/**
 * 管理后台 - 素材管理（细化版：列表+下载+删除）
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '素材管理';
$error = ''; $success = '';
$materials = [];
$count = ['image'=>0,'voice'=>0,'video'=>0,'file'=>0];

// 上传素材
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['media'])) {
    try {
        $type = $_POST['type'] ?? 'image';
        $isPermanent = !empty($_POST['permanent']);
        $tmpFile = $_FILES['media']['tmp_name'];
        
        if ($isPermanent) {
            $result = $wx->media->addMaterial($tmpFile, $type);
            $success = '永久素材上传成功！Media ID: <code>' . ($result['media_id'] ?? 'N/A') . '</code>，URL: ' . ($result['url'] ?? 'N/A');
        } else {
            $result = $wx->media->uploadMedia($tmpFile, $type);
            $success = '临时素材上传成功！Media ID: <code>' . ($result['media_id'] ?? 'N/A') . '</code>，有效期3天';
        }
        
        // 记录到数据库
        try {
            $db = $wx->getDatabase();
            $db->insertRow('wxwork_media', [
                'media_id'     => $result['media_id'] ?? '',
                'media_type'   => $type,
                'filename'     => $_FILES['media']['name'],
                'file_size'    => $_FILES['media']['size'],
                'url'          => $result['url'] ?? '',
                'is_permanent' => $isPermanent ? 1 : 0,
                'expires_at'   => $isPermanent ? null : date('Y-m-d H:i:s', strtotime('+3 days')),
            ]);
        } catch (\Throwable $e) {}
    } catch (\Throwable $e) { $error = $e->getMessage(); }
}

// 获取素材统计
try {
    $countResult = $wx->media->getMaterialCount();
    if (($countResult['errcode'] ?? -1) === 0) {
        $count = [
            'image' => $countResult['image_count'] ?? 0,
            'voice' => $countResult['voice_count'] ?? 0,
            'video' => $countResult['video_count'] ?? 0,
            'file'  => $countResult['file_count'] ?? 0,
        ];
    }
} catch (\Throwable $e) {}

// CSV 导出
if (isset($_GET['export']) && !empty($materials)) {
    $headers = ['ID', 'Media ID', '类型', '文件名', '大小(KB)', '状态', '上传时间', 'URL'];
    $rows = array_map(function($m) {
        return [
            'id' => $m['id'], 'media_id' => $m['media_id'], '类型' => $m['media_type'],
            '文件名' => $m['filename'] ?? '', '大小' => round($m['file_size']/1024, 1),
            '状态' => ($m['is_permanent']??0)==1?'永久':'临时', '上传时间' => $m['created_at'],
            'URL' => $m['url'] ?? '',
        ];
    }, $materials);
    exportCsv($headers, $rows, '素材列表_' . date('Ymd'));
}

// 获取临时素材列表（从数据库）
try {
    $db = $wx->getDatabase();
    $materials = $db->select('SELECT * FROM wxwork_media ORDER BY id DESC LIMIT 50');
} catch (\Throwable $e) {}

require __DIR__ . '/../../layout/header.php';
?>

<?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-upload"></i> <span class="caption-subject bold uppercase">上传素材</span></div></div>
            <div class="portlet-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>素材类型</label>
                        <select name="type" class="form-control">
                            <option value="image">图片 (2MB, 支持bmp/png/jpeg/jpg/gif)</option>
                            <option value="voice">语音 (2MB, 支持amr/mp3)</option>
                            <option value="video">视频 (10MB, 支持mp4)</option>
                            <option value="file">文件 (10MB)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>选择文件</label>
                        <input type="file" name="media" class="form-control" required>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="permanent" value="1"> 
                            <strong>上传为永久素材</strong>（图片类型会返回URL，其他类型仅支持3天有效期）
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> 上传</button>
                    <p class="help-block" style="margin-top:10px;font-size:12px;color:#999">
                        临时素材有效期3天；永久素材有数量限制（图片5000张，其他各1000个）
                    </p>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption"><i class="fa fa-folder-open"></i> <span class="caption-subject bold uppercase">素材统计</span></div>
                <div class="actions">
                    <a href="/media?export=1" class="btn btn-xs btn-success"><i class="fa fa-download"></i> 导出 CSV</a>
                </div>
            </div>
            <div class="portlet-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="well well-sm">
                            <h3 class="font-blue"><?= $count['image'] ?></h3>
                            <small>图片</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="well well-sm">
                            <h3 class="font-green"><?= $count['voice'] ?></h3>
                            <small>语音</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="well well-sm">
                            <h3 class="font-red"><?= $count['video'] ?></h3>
                            <small>视频</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="well well-sm">
                            <h3 class="font-purple"><?= $count['file'] ?></h3>
                            <small>文件</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($materials)): ?>
        <div class="portlet light">
            <div class="portlet-title"><div class="caption"><i class="fa fa-list"></i> <span class="caption-subject bold uppercase">上传记录</span></div></div>
            <div class="portlet-body table-responsive">
                <table class="table table-striped table-hover">
                    <thead><tr><th>ID</th><th>Media ID</th><th>类型</th><th>文件名</th><th>大小</th><th>状态</th><th>上传时间</th><th width="140" class="text-center">操作</th></tr></thead>
                    <tbody>
                        <?php foreach ($materials as $m): ?>
                        <tr>
                            <td><?= $m['id'] ?></td>
                            <td>
                                <code style="font-size:10px"><?= htmlspecialchars(mb_substr($m['media_id'], 0, 24)) ?>...</code>
                                <a href="#" onclick="copyText('<?= htmlspecialchars($m['media_id']) ?>');return false" title="复制 Media ID"><i class="fa fa-copy text-muted"></i></a>
                            </td>
                            <td><span class="label label-sm label-info"><?= htmlspecialchars($m['media_type']) ?></span></td>
                            <td><?= htmlspecialchars($m['filename'] ?? '-') ?></td>
                            <td><?= round($m['file_size']/1024, 1) ?>KB</td>
                            <td><?= ($m['is_permanent']??0)==1?'<span class="label label-sm label-success">永久</span>':'<span class="label label-sm label-warning">临时</span>' ?></td>
                            <td><?= $m['created_at'] ?></td>
                            <td class="text-center">
                                <?php if ($m['url'] ?? ''): ?>
                                <a href="<?= htmlspecialchars($m['url']) ?>" target="_blank" class="btn btn-xs green"><i class="fa fa-eye"></i> 预览</a>
                                <?php endif; ?>
                                <a href="/media/download?id=<?= $m['id'] ?>" class="btn btn-xs blue"><i class="fa fa-download"></i></a>
                                <a href="/media/delete?id=<?= $m['id'] ?>" class="btn btn-xs red" onclick="return confirm('确定删除？')"><i class="fa fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../../layout/footer.php'; ?>
<script>
function copyText(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text);
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed'; ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    }
}
</script>

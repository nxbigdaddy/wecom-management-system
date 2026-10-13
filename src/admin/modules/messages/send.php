<?php
/**
 * 管理后台 - 发送消息（便捷优化版）
 */
use Wxwork\Core\Session;
if (!Session::isLoggedIn()) { header('Location: /login'); exit; }

$title = '发送消息';
$error = ''; $success = '';
$members = [];
$departments = [];
$tags = [];
$msgTemplates = [];
$currentUser = Session::getUser(); // 获取当前登录用户

// 获取成员、部门、标签列表
try {
    $memberResult = $wx->get('/cgi-bin/user/simplelist', ['department_id' => 1, 'fetch_child' => 1]);
    $members = ($memberResult['errcode'] ?? -1) === 0 ? ($memberResult['userlist'] ?? []) : [];
    
    $deptResult = $wx->get('/cgi-bin/department/list');
    $departments = ($deptResult['errcode'] ?? -1) === 0 ? ($deptResult['department'] ?? []) : [];
    
    $tagResult = $wx->get('/cgi-bin/tag/list');
    $tags = ($tagResult['errcode'] ?? -1) === 0 ? ($tagResult['taglist'] ?? []) : [];
} catch (\Throwable $e) {}

// 获取消息模板（从数据库）
try {
    $db = $wx->getDatabase();
    $msgTemplates = $db->select('SELECT * FROM wxwork_message_template WHERE is_enabled = 1 ORDER BY id DESC LIMIT 20');
} catch (\Throwable $e) {}

// 处理模板保存（AJAX）
$templateResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_template') {
    header('Content-Type: application/json');
    try {
        $db = $wx->getDatabase();
        $tplData = [
            'tpl_name'  => trim($_POST['tpl_name'] ?? ''),
            'tpl_desc'  => trim($_POST['tpl_desc'] ?? ''),
            'msg_type'  => $_POST['template_msg_type'] ?? 'text',
            'content'   => json_decode($_POST['template_content'] ?? '{}', true),
        ];
        if (empty($tplData['tpl_name'])) {
            echo json_encode(['success' => false, 'error' => '模板名称不能为空']);
            exit;
        }
        // 检查重名
        $exists = $db->selectValue('SELECT id FROM wxwork_message_template WHERE tpl_name = ? AND is_enabled = 1', [$tplData['tpl_name']]);
        if ($exists) {
            $db->updateRow('wxwork_message_template', [
                'tpl_desc' => $tplData['tpl_desc'],
                'msg_type' => $tplData['msg_type'],
                'content'  => json_encode($tplData['content'], JSON_UNESCAPED_UNICODE),
            ], 'id = ?', [(int)$exists]);
        } else {
            $db->insertRow('wxwork_message_template', [
                'tpl_name'  => $tplData['tpl_name'],
                'tpl_desc'  => $tplData['tpl_desc'],
                'msg_type'  => $tplData['msg_type'],
                'content'   => json_encode($tplData['content'], JSON_UNESCAPED_UNICODE),
                'is_enabled' => 1,
            ]);
        }
        echo json_encode(['success' => true, 'message' => '模板保存成功']);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 处理模板加载（AJAX）
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'load_template') {
    header('Content-Type: application/json');
    try {
        $db = $wx->getDatabase();
        $id = (int)($_GET['id'] ?? 0);
        $tpl = $db->selectOne('SELECT * FROM wxwork_message_template WHERE id = ? AND is_enabled = 1', [$id]);
        if ($tpl) {
            $tpl['content'] = json_decode($tpl['content'] ?? '{}', true);
            echo json_encode(['success' => true, 'template' => $tpl]);
        } else {
            echo json_encode(['success' => false, 'error' => '模板不存在']);
        }
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 处理发送
$apiResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $msgType = $_POST['msg_type'] ?? 'text';
        $receiverMode = $_POST['receiver_mode'] ?? 'user';
        $receiver = [];
        
        switch ($receiverMode) {
            case 'user':
                $receiver['touser'] = $_POST['touser'] ?? '';
                break;
            case 'party':
                $receiver['toparty'] = $_POST['toparty'] ?? '';
                break;
            case 'tag':
                $receiver['totag'] = $_POST['totag'] ?? '';
                break;
            case 'all':
                $receiver['touser'] = '@all';
                break;
        }
        $receiver = array_filter($receiver);
        
        $content = [];
        switch ($msgType) {
            case 'text':
                $content = ['content' => $_POST['content'] ?? ''];
                break;
            case 'textcard':
                $content = [
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'url' => $_POST['url'] ?? '',
                    'btntxt' => $_POST['btntxt'] ?? '详情'
                ];
                break;
            case 'news':
                $articles = [];
                $titles = $_POST['article_title'] ?? [];
                $urls = $_POST['article_url'] ?? [];
                $pics = $_POST['article_pic'] ?? [];
                $descs = $_POST['article_desc'] ?? [];
                for ($i=0; $i<count($titles); $i++) {
                    if (!empty($titles[$i])) {
                        $articles[] = [
                            'title' => $titles[$i],
                            'description' => $descs[$i] ?? '',
                            'url' => $urls[$i] ?? '',
                            'picurl' => $pics[$i] ?? ''
                        ];
                    }
                }
                $content = ['articles' => $articles];
                break;
            case 'file':
                $content = ['media_id' => $_POST['media_id'] ?? ''];
                break;
            case 'image':
                $content = ['media_id' => $_POST['media_id'] ?? ''];
                break;
        }
        
        $options = [];
        if (isset($_POST['safe'])) $options['safe'] = (int)$_POST['safe'];
        
        try {
            $apiResult = $wx->message->send($msgType, $receiver, $content, $options);
            $success = '消息发送成功！';
        } catch (\Throwable $apiErr) {
            $error = $apiErr->getMessage();
            $apiResult = ['errcode' => -1, 'errmsg' => $error];
        }
        
        // 写入发送记录到数据库（无论成功失败都记录）
        try {
            $db = $wx->getDatabase();
            $db->insertRow('wxwork_message_log', [
                'msg_type'   => $msgType,
                'to_users'   => $receiver['touser'] ?? '',
                'to_party'   => $receiver['toparty'] ?? '',
                'to_tag'     => $receiver['totag'] ?? '',
                'content'    => json_encode($content, JSON_UNESCAPED_UNICODE),
                'safe'       => $options['safe'] ?? 0,
                'msg_id'     => $apiResult['msgid'] ?? '',
                'errcode'    => (int)($apiResult['errcode'] ?? -1),
                'errmsg'     => $apiResult['errmsg'] ?? '',
                'status'     => (($apiResult['errcode'] ?? -1) === 0) ? 1 : 2,
                'sent_by'    => $currentUser['username'] ?? 'admin',
            ]);
        } catch (\Throwable $logErr) {
            // 日志写入失败不影响发送
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

require __DIR__ . '/../../layout/header.php';
?>

<style>
.receiver-mode-tabs { margin-bottom: 15px; }
.receiver-mode-tabs .nav-tabs > li > a { padding: 8px 15px; font-size: 13px; }
.member-search-box { margin-bottom: 10px; }
.member-list-container { max-height: 350px; overflow-y: auto; border: 1px solid #e5e5e5; border-radius: 4px; padding: 10px; background: #fafafa; }
.member-item { padding: 6px 10px; margin: 3px 0; background: #fff; border: 1px solid #eee; border-radius: 3px; cursor: pointer; transition: all 0.2s; }
.member-item:hover { background: #e3f2fd; border-color: #2196f3; }
.member-item.selected { background: #bbdefb; border-color: #1976d2; }
.member-item .member-name { font-weight: 500; font-size: 13px; }
.member-item .member-userid { font-size: 11px; color: #999; margin-left: 8px; }
.preview-box { background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-top: 15px; min-height: 100px; }
.preview-box h5 { margin-top: 0; color: #666; font-size: 13px; }
.template-item { padding: 8px 12px; margin: 5px 0; background: #fff; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; transition: all 0.2s; }
.template-item:hover { background: #e8f5e9; border-color: #4caf50; }
.template-item .template-name { font-weight: 500; font-size: 13px; }
.template-item .template-desc { font-size: 11px; color: #999; margin-top: 3px; }
.quick-insert-btns { margin-top: 10px; }
.quick-insert-btns .btn { margin-right: 5px; margin-bottom: 5px; }
.selected-receivers { margin-top: 10px; padding: 10px; background: #e3f2fd; border-radius: 4px; min-height: 40px; }
.selected-receivers .receiver-tag { display: inline-block; padding: 3px 8px; margin: 2px; background: #2196f3; color: #fff; border-radius: 3px; font-size: 12px; }
.selected-receivers .receiver-tag .remove { margin-left: 5px; cursor: pointer; }
</style>

<?php if ($success): ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-9">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-paper-plane font-blue"></i> <span class="caption-subject font-blue bold uppercase">发送消息</span>
                </div>
            </div>
            <div class="portlet-body">
                <form method="post" id="msgForm" onsubmit="return confirmSend()">
                    
                    <!-- 消息类型 -->
                    <div class="form-group">
                        <label><i class="fa fa-tag"></i> 消息类型</label>
                        <select name="msg_type" id="msgType" class="form-control input-medium" onchange="switchType()">
                            <option value="text"> 文本消息</option>
                            <option value="textcard"> 文本卡片</option>
                            <option value="news">📰 图文消息</option>
                            <option value="image">🖼️ 图片消息</option>
                            <option value="file">📎 文件消息</option>
                        </select>
                    </div>
                    
                    <!-- 接收人选择方式 -->
                    <div class="form-group">
                        <label><i class="fa fa-users"></i> 接收人</label>
                        <ul class="nav nav-tabs receiver-mode-tabs" id="receiverModeTabs">
                            <li class="active"><a href="#mode-user" data-toggle="tab"><i class="fa fa-user"></i> 按成员</a></li>
                            <li><a href="#mode-party" data-toggle="tab"><i class="fa fa-sitemap"></i> 按部门</a></li>
                            <li><a href="#mode-tag" data-toggle="tab"><i class="fa fa-tags"></i> 按标签</a></li>
                            <li><a href="#mode-all" data-toggle="tab"><i class="fa fa-globe"></i> 全部成员</a></li>
                        </ul>
                        
                        <div class="tab-content" style="padding: 15px 0;">
                            <!-- 按成员 -->
                            <div class="tab-pane active" id="mode-user">
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- 快速输入框 -->
                                        <div class="member-search-box">
                                            <label style="font-size: 12px; color: #666; margin-bottom: 5px; display: block;">
                                                <i class="fa fa-keyboard-o"></i> 快速输入（输入姓名或UserID，用 | 分隔多人）
                                            </label>
                                            <input type="text" class="form-control" id="quickInput" 
                                                placeholder="例如：张三 | 李四 | zhangsan" 
                                                autocomplete="off"
                                                oninput="showSuggestions(this.value)"
                                                onblur="setTimeout(hideSuggestions, 200)">
                                            <!-- 自动完成下拉 -->
                                            <div id="suggestionsBox" style="display: none; position: absolute; z-index: 1000; background: #fff; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); max-height: 200px; overflow-y: auto; width: calc(100% - 30px);"></div>
                                        </div>
                                        
                                        <!-- 成员列表 -->
                                        <div style="margin-top: 15px; margin-bottom: 10px;">
                                            <label style="font-size: 12px; color: #666;">
                                                <i class="fa fa-list"></i> 或从列表选择：
                                            </label>
                                            <input type="text" class="form-control input-sm" id="memberSearch" placeholder="🔍 搜索成员..." style="margin-top: 5px;">
                                        </div>
                                        <div class="member-list-container" id="memberList">
                                            <?php foreach ($members as $m): ?>
                                            <div class="member-item" data-userid="<?= htmlspecialchars($m['userid']) ?>" data-name="<?= htmlspecialchars($m['name']) ?>">
                                                <input type="checkbox" class="user-check" value="<?= htmlspecialchars($m['userid']) ?>" style="margin-right: 8px;">
                                                <span class="member-name"><?= htmlspecialchars($m['name']) ?></span>
                                                <span class="member-userid"><?= htmlspecialchars($m['userid']) ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div style="margin-top: 10px;">
                                            <button type="button" class="btn btn-xs btn-default" onclick="selectAllMembers()"><i class="fa fa-check-square"></i> 全选</button>
                                            <button type="button" class="btn btn-xs btn-default" onclick="clearAllMembers()"><i class="fa fa-square"></i> 清空</button>
                                            <span class="text-muted" style="margin-left: 10px;">已选 <strong id="selectedCount">0</strong> 人</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>已选接收人：</label>
                                        <div class="selected-receivers" id="selectedReceivers">
                                            <span class="text-muted">请从左侧选择成员或快速输入</span>
                                        </div>
                                        <input type="hidden" name="touser" id="touserInput" value="">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 按部门 -->
                            <div class="tab-pane" id="mode-party">
                                <select name="toparty" class="form-control" id="topartySelect">
                                    <option value="">-- 请选择部门（可多选） --</option>
                                    <?php foreach ($departments as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (ID: <?= $d['id'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="help-block"><i class="fa fa-info-circle"></i> 选择部门后，该部门及所有子部门的成员都将收到消息</p>
                            </div>
                            
                            <!-- 按标签 -->
                            <div class="tab-pane" id="mode-tag">
                                <select name="totag" class="form-control" id="totagSelect">
                                    <option value="">-- 请选择标签（可多选） --</option>
                                    <?php foreach ($tags as $t): ?>
                                    <option value="<?= $t['tagid'] ?>"><?= htmlspecialchars($t['tagname']) ?> (ID: <?= $t['tagid'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="help-block"><i class="fa fa-info-circle"></i> 选择标签后，带有该标签的成员都将收到消息</p>
                            </div>
                            
                            <!-- 全部成员 -->
                            <div class="tab-pane" id="mode-all">
                                <div class="alert alert-info">
                                    <i class="fa fa-globe"></i> 将发送消息给企业中的所有成员（包括未来新加入的成员）
                                </div>
                                <input type="hidden" name="touser" value="@all">
                            </div>
                        </div>
                    </div>
                    
                    <!-- 消息内容区域 -->
                    <div class="type-section" id="sText">
                        <div class="form-group">
                            <label><i class="fa fa-comment"></i> 消息内容</label>
                            <textarea name="content" id="textContent" class="form-control" rows="8" placeholder="请输入消息内容..." oninput="updatePreview()"></textarea>
                            <div class="quick-insert-btns">
                                <button type="button" class="btn btn-xs btn-default" onclick="insertText('\\n')"> 换行</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="insertText('【重要】')"> 重要标记</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="insertText('请尽快处理')">⏰ 催促</button>
                                <button type="button" class="btn btn-xs btn-default" onclick="insertText('谢谢！')">🙏 感谢</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="type-section" id="sTextCard" style="display:none">
                        <div class="form-group">
                            <label><i class="fa fa-header"></i> 标题</label>
                            <input type="text" name="title" class="form-control" placeholder="请输入卡片标题" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label><i class="fa fa-align-left"></i> 描述</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="请输入卡片描述" oninput="updatePreview()"></textarea>
                        </div>
                        <div class="form-group">
                            <label><i class="fa fa-link"></i> 链接 URL</label>
                            <input type="text" name="url" class="form-control" placeholder="https://..." oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label><i class="fa fa-hand-o-right"></i> 按钮文字</label>
                            <input type="text" name="btntxt" class="form-control" value="详情" placeholder="按钮文字">
                        </div>
                    </div>
                    
                    <div class="type-section" id="sNews" style="display:none">
                        <div id="articlesWrap">
                            <div class="article-item well">
                                <h5><i class="fa fa-newspaper-o"></i> 图文 1</h5>
                                <div class="form-group">
                                    <label>标题</label>
                                    <input type="text" name="article_title[]" class="form-control" oninput="updatePreview()">
                                </div>
                                <div class="form-group">
                                    <label>描述</label>
                                    <input type="text" name="article_desc[]" class="form-control" oninput="updatePreview()">
                                </div>
                                <div class="form-group">
                                    <label>链接 URL</label>
                                    <input type="text" name="article_url[]" class="form-control" oninput="updatePreview()">
                                </div>
                                <div class="form-group">
                                    <label>图片 URL</label>
                                    <input type="text" name="article_pic[]" class="form-control" oninput="updatePreview()">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-default" onclick="addArticle()"><i class="fa fa-plus"></i> 添加图文</button>
                    </div>
                    
                    <div class="type-section" id="sMedia" style="display:none">
                        <div class="form-group">
                            <label><i class="fa fa-cloud-upload"></i> 素材 Media ID</label>
                            <input type="text" name="media_id" class="form-control" placeholder="请先在素材管理中上传文件，然后填入 Media ID">
                            <p class="help-block"><i class="fa fa-info-circle"></i> <a href="/media" target="_blank">前往素材管理上传文件</a></p>
                        </div>
                    </div>
                    
                    <!-- 高级选项 -->
                    <div class="form-group" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                        <label><i class="fa fa-cog"></i> 高级选项</label>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="safe" value="1"> 保密消息（仅受信设备可查看，禁止转发）
                            </label>
                        </div>
                    </div>
                    
                    <!-- 实时预览 -->
                    <div class="preview-box">
                        <h5><i class="fa fa-eye"></i> 消息预览</h5>
                        <div id="previewContent">
                            <span class="text-muted">请输入消息内容后查看预览</span>
                        </div>
                    </div>
                    
                    <!-- 操作按钮 -->
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                        <button type="submit" class="btn btn-lg btn-primary"><i class="fa fa-send"></i> 发送消息</button>
                        <button type="button" class="btn btn-lg btn-default" onclick="resetForm()"><i class="fa fa-refresh"></i> 重置</button>
                        <button type="button" class="btn btn-lg btn-success" onclick="saveAsTemplate()"><i class="fa fa-save"></i> 保存为模板</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- 右侧：消息模板 -->
    <div class="col-md-3">
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-file-text-o font-green"></i> <span class="caption-subject font-green bold uppercase">消息模板</span>
                </div>
            </div>
            <div class="portlet-body">
                <?php if (empty($msgTemplates)): ?>
                <p class="text-muted text-center">暂无消息模板</p>
                <?php else: ?>
                <?php foreach ($msgTemplates as $tpl): ?>
                <div class="template-item" onclick="loadTemplate(<?= htmlspecialchars(json_encode($tpl)) ?>)">
                    <div class="template-name"><?= htmlspecialchars($tpl['tpl_name']) ?></div>
                    <div class="template-desc"><?= htmlspecialchars($tpl['tpl_desc'] ?? '') ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="portlet light">
            <div class="portlet-title">
                <div class="caption">
                    <i class="fa fa-question-circle font-purple"></i> <span class="caption-subject font-purple bold uppercase">使用说明</span>
                </div>
            </div>
            <div class="portlet-body">
                <ul style="padding-left: 20px; font-size: 13px; color: #666;">
                    <li>选择消息类型后填写对应内容</li>
                    <li>选择接收人（成员/部门/标签/全部）</li>
                    <li>预览消息效果后点击发送</li>
                    <li>可保存常用消息为模板</li>
                    <li>保密消息禁止转发和截图</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- 发送确认 Modal -->
<div class="modal fade" id="confirmModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-exclamation-triangle text-warning"></i> 确认发送</h4>
            </div>
            <div class="modal-body">
                <p>您确定要发送此消息吗？</p>
                <div id="confirmPreview" style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin-top: 10px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('msgForm').submit();">确认发送</button>
            </div>
        </div>
    </div>
</div>

<!-- 保存模板 Modal -->
<div class="modal fade" id="templateModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-save"></i> 保存为消息模板</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>模板名称</label>
                    <input type="text" class="form-control" id="tplName" placeholder="例如：会议通知模板">
                </div>
                <div class="form-group">
                    <label>模板描述</label>
                    <input type="text" class="form-control" id="tplDesc" placeholder="简要描述此模板的用途">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="doSaveTemplate()">保存</button>
            </div>
        </div>
    </div>
</div>

<script>
var articleCount = 1;
var currentReceiverMode = 'user';

// 切换消息类型
function switchType() {
    var t = $('#msgType').val();
    $('.type-section').hide();
    var m = {text:'sText', textcard:'sTextCard', news:'sNews', image:'sMedia', file:'sMedia'};
    if (m[t]) $('#'+m[t]).show();
    updatePreview();
}

// 切换接收人模式
$('#receiverModeTabs a').click(function(e) {
    e.preventDefault();
    var mode = $(this).attr('href').replace('#mode-', '');
    currentReceiverMode = mode;
    updateReceiverInput();
});

// 自动完成提示
function showSuggestions(value) {
    if (!value.trim()) {
        hideSuggestions();
        return;
    }
    
    var keyword = value.toLowerCase().trim();
    var matches = [];
    
    // 从成员列表中查找匹配项
    $('.member-item').each(function() {
        var name = $(this).data('name').toLowerCase();
        var userid = $(this).data('userid').toLowerCase();
        if (name.indexOf(keyword) >= 0 || userid.indexOf(keyword) >= 0) {
            matches.push({
                name: $(this).data('name'),
                userid: $(this).data('userid')
            });
        }
    });
    
    if (matches.length === 0) {
        hideSuggestions();
        return;
    }
    
    // 显示建议列表
    var html = '';
    matches.slice(0, 10).forEach(function(m) {
        html += '<div class="suggestion-item" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0;" onmousedown="selectSuggestion(\'' + m.userid + '\', \'' + m.name + '\')">';
        html += '<i class="fa fa-user" style="color: #999; margin-right: 8px;"></i>';
        html += '<strong>' + m.name + '</strong>';
        html += '<span style="color: #999; font-size: 11px; margin-left: 8px;">' + m.userid + '</span>';
        html += '</div>';
    });
    
    $('#suggestionsBox').html(html).show();
}

function hideSuggestions() {
    $('#suggestionsBox').hide();
}

function selectSuggestion(userid, name) {
    // 检查是否已选择
    var exists = $('.user-check[value="' + userid + '"]').is(':checked');
    if (!exists) {
        $('.member-item[data-userid="' + userid + '"]').addClass('selected').find('.user-check').prop('checked', true);
        updateSelectedReceivers();
    }
    
    // 清空输入框
    $('#quickInput').val('');
    hideSuggestions();
}

// 成员搜索
$('#memberSearch').on('input', function() {
    var keyword = $(this).val().toLowerCase();
    $('.member-item').each(function() {
        var name = $(this).data('name').toLowerCase();
        var userid = $(this).data('userid').toLowerCase();
        if (name.indexOf(keyword) >= 0 || userid.indexOf(keyword) >= 0) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
});

// 成员选择
$(document).on('click', '.member-item', function() {
    var checkbox = $(this).find('.user-check');
    checkbox.prop('checked', !checkbox.prop('checked'));
    $(this).toggleClass('selected');
    updateSelectedReceivers();
});

// 全选/清空
function selectAllMembers() {
    $('.member-item:visible').addClass('selected').find('.user-check').prop('checked', true);
    updateSelectedReceivers();
}
function clearAllMembers() {
    $('.member-item').removeClass('selected').find('.user-check').prop('checked', false);
    updateSelectedReceivers();
}

// 更新已选接收人显示
function updateSelectedReceivers() {
    var selected = [];
    $('.user-check:checked').each(function() {
        var item = $(this).closest('.member-item');
        selected.push({
            userid: $(this).val(),
            name: item.data('name')
        });
    });
    
    $('#selectedCount').text(selected.length);
    
    if (selected.length === 0) {
        $('#selectedReceivers').html('<span class="text-muted">请从左侧选择成员</span>');
        $('#touserInput').val('');
    } else {
        var html = '';
        var userids = [];
        selected.forEach(function(s) {
            html += '<span class="receiver-tag">' + s.name + ' <span class="remove" onclick="removeReceiver(\'' + s.userid + '\')">&times;</span></span>';
            userids.push(s.userid);
        });
        $('#selectedReceivers').html(html);
        $('#touserInput').val(userids.join('|'));
    }
}

// 移除单个接收人
function removeReceiver(userid) {
    $('.member-item[data-userid="' + userid + '"]').removeClass('selected').find('.user-check').prop('checked', false);
    updateSelectedReceivers();
}

// 更新接收人输入
function updateReceiverInput() {
    if (currentReceiverMode === 'all') {
        $('#msgForm').find('input[name=touser]').val('@all');
    }
}

// 添加图文
function addArticle() {
    articleCount++;
    var html = '<div class="article-item well">' +
        '<h5><i class="fa fa-newspaper-o"></i> 图文 ' + articleCount + '</h5>' +
        '<div class="form-group"><label>标题</label><input type="text" name="article_title[]" class="form-control" oninput="updatePreview()"></div>' +
        '<div class="form-group"><label>描述</label><input type="text" name="article_desc[]" class="form-control" oninput="updatePreview()"></div>' +
        '<div class="form-group"><label>链接 URL</label><input type="text" name="article_url[]" class="form-control" oninput="updatePreview()"></div>' +
        '<div class="form-group"><label>图片 URL</label><input type="text" name="article_pic[]" class="form-control" oninput="updatePreview()"></div>' +
        '<button type="button" class="btn btn-xs btn-danger" onclick="$(this).closest(\'.article-item\').remove(); articleCount--;">删除</button>' +
        '</div>';
    $('#articlesWrap').append(html);
}

// 快捷插入文本
function insertText(text) {
    var textarea = document.getElementById('textContent');
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var value = textarea.value;
    textarea.value = value.substring(0, start) + text + value.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + text.length;
    updatePreview();
}

// 更新预览
function updatePreview() {
    var type = $('#msgType').val();
    var preview = '';
    
    switch(type) {
        case 'text':
            var content = $('#textContent').val();
            preview = content ? '<p style="white-space: pre-wrap;">' + escapeHtml(content) + '</p>' : '<span class="text-muted">请输入消息内容</span>';
            break;
        case 'textcard':
            var title = $('input[name=title]').val();
            var desc = $('textarea[name=description]').val();
            preview = '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">' +
                '<h4 style="margin-top: 0;">' + escapeHtml(title || '标题') + '</h4>' +
                '<p>' + escapeHtml(desc || '描述') + '</p>' +
                '<button class="btn btn-xs btn-primary">详情</button></div>';
            break;
        case 'news':
            preview = '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">';
            $('.article-item').each(function(i) {
                var t = $(this).find('input[name="article_title[]"]').eq(i).val();
                var d = $(this).find('input[name="article_desc[]"]').eq(i).val();
                preview += '<div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee;">' +
                    '<h5 style="margin-top: 0;">' + escapeHtml(t || '标题' + (i+1)) + '</h5>' +
                    '<p>' + escapeHtml(d || '描述') + '</p></div>';
            });
            preview += '</div>';
            break;
        case 'image':
        case 'file':
            var mediaId = $('input[name=media_id]').val();
            preview = mediaId ? '<p><i class="fa fa-check-circle text-success"></i> Media ID: ' + mediaId + '</p>' : '<span class="text-muted">请输入 Media ID</span>';
            break;
    }
    
    $('#previewContent').html(preview);
}

// HTML转义
function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 确认发送
function confirmSend() {
    var preview = $('#previewContent').html();
    $('#confirmPreview').html(preview);
    $('#confirmModal').modal('show');
    return false;
}

// 重置表单
function resetForm() {
    if (confirm('确定要重置表单吗？所有内容将被清空。')) {
        $('#msgForm')[0].reset();
        switchType();
        clearAllMembers();
        updatePreview();
    }
}

// 保存为模板
function saveAsTemplate() {
    $('#templateModal').modal('show');
}

function doSaveTemplate() {
    var name = $('#tplName').val();
    var desc = $('#tplDesc').val();
    if (!name) { alert('请输入模板名称'); return; }

    // 收集当前表单内容
    var msgType = $('#msgType').val();
    var content = {};
    switch (msgType) {
        case 'text':
            content = { content: $('#textContent').val() };
            break;
        case 'textcard':
            content = {
                title: $('input[name=title]').val(),
                description: $('textarea[name=description]').val(),
                url: $('input[name=url]').val(),
                btntxt: $('input[name=btntxt]').val() || '详情'
            };
            break;
        case 'news':
            var articles = [];
            $('.article-item').each(function() {
                var t = $(this).find('input[name="article_title[]"]').val();
                if (t) {
                    articles.push({
                        title: t,
                        description: $(this).find('input[name="article_desc[]"]').val() || '',
                        url: $(this).find('input[name="article_url[]"]').val() || '',
                        picurl: $(this).find('input[name="article_pic[]"]').val() || ''
                    });
                }
            });
            content = { articles: articles };
            break;
        case 'image':
        case 'file':
            content = { media_id: $('input[name=media_id]').val() };
            break;
    }

    // AJAX 保存
    $.post('', {
        action: 'save_template',
        tpl_name: name,
        tpl_desc: desc,
        template_msg_type: msgType,
        template_content: JSON.stringify(content)
    }, function(resp) {
        if (resp.success) {
            alert('模板保存成功！');
            $('#templateModal').modal('hide');
            location.reload();
        } else {
            alert('保存失败: ' + (resp.error || '未知错误'));
        }
    }, 'json').fail(function() {
        alert('请求失败，请检查网络');
    });
}

// 加载模板
function loadTemplate(tpl) {
    if (!confirm('确定要加载模板 "' + tpl.tpl_name + '" 吗？当前内容将被覆盖。')) return;

    // AJAX 加载完整模板内容
    $.get('', { action: 'load_template', id: tpl.id }, function(resp) {
        if (resp.success && resp.template) {
            var t = resp.template;
            // 设置消息类型
            $('#msgType').val(t.msg_type || 'text');
            switchType();

            // 填充内容
            var content = t.content || {};
            switch (t.msg_type) {
                case 'text':
                    $('#textContent').val(content.content || '');
                    break;
                case 'textcard':
                    $('input[name=title]').val(content.title || '');
                    $('textarea[name=description]').val(content.description || '');
                    $('input[name=url]').val(content.url || '');
                    $('input[name=btntxt]').val(content.btntxt || '详情');
                    break;
                case 'news':
                    // 清空现有图文
                    $('#articlesWrap').empty();
                    articleCount = 0;
                    var articles = content.articles || [];
                    if (articles.length > 0) {
                        articles.forEach(function(a) {
                            articleCount++;
                            $('#articlesWrap').append(
                                '<div class="article-item well">' +
                                '<h5><i class="fa fa-newspaper-o"></i> 图文 ' + articleCount + '</h5>' +
                                '<div class="form-group"><label>标题</label><input type="text" name="article_title[]" class="form-control" value="' + escapeHtml(a.title || '') + '" oninput="updatePreview()"></div>' +
                                '<div class="form-group"><label>描述</label><input type="text" name="article_desc[]" class="form-control" value="' + escapeHtml(a.description || '') + '" oninput="updatePreview()"></div>' +
                                '<div class="form-group"><label>链接 URL</label><input type="text" name="article_url[]" class="form-control" value="' + escapeHtml(a.url || '') + '" oninput="updatePreview()"></div>' +
                                '<div class="form-group"><label>图片 URL</label><input type="text" name="article_pic[]" class="form-control" value="' + escapeHtml(a.picurl || '') + '" oninput="updatePreview()"></div>' +
                                '<button type="button" class="btn btn-xs btn-danger" onclick="$(this).closest(\'.article-item\').remove(); articleCount--;">删除</button>' +
                                '</div>'
                            );
                        });
                    } else {
                        // 至少保留一个空图文
                        addArticle();
                    }
                    break;
                case 'image':
                case 'file':
                    $('input[name=media_id]').val(content.media_id || '');
                    break;
            }
            updatePreview();
            alert('模板 "' + tpl.tpl_name + '" 加载成功！');
        } else {
            alert('加载失败: ' + (resp.error || '模板不存在'));
        }
    }, 'json').fail(function() {
        alert('请求失败，请检查网络');
    });
}

// 初始化
$(function() {
    switchType();
    updatePreview();
});
</script>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

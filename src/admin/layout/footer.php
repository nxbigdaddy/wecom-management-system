        </div><!-- /.page-content -->
    </div><!-- /.page-content-wrapper -->
</div><!-- /.page-container -->

<!-- 页脚 -->
<div class="page-footer">
    <div class="page-footer-inner"> 2025 &copy; 企业微信管理系统 WxWork Manager </div>
</div>

<script src="/assets/themes/plugins/jquery.min.js"></script>
<script src="/assets/themes/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="/assets/themes/plugins/uniform/jquery.uniform.min.js"></script>
<script src="/assets/themes/js/app.min.js" type="text/javascript"></script>
<script src="/assets/themes/js/layout.min.js" type="text/javascript"></script>
<script>
// CSRF Token
var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
$(function() {
    // Uniform 美化复选框
    $("body").find(".checkbox").uniform();
    // tooltips
    $('.tooltips').tooltip();
});
function confirmDelete(url, msg) {
    msg = msg || '确定删除？此操作不可恢复。';
    if (!confirm(msg)) return false;
    $.post(url, {_csrf_token: YOUR_SECRET}, function(res){
        if (res.errcode === 0) location.reload();
        else alert('操作失败: ' + (res.errmsg || '未知错误'));
    }, 'json');
    return false;
}
// 全局复制函数
function copyText(text) {
    if (!text) return;
    if (navigator.clipboard) { navigator.clipboard.writeText(text); }
    else {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.cssText = 'position:fixed;left:-9999px';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy');
        document.body.removeChild(ta);
    }
}
</script>
</body>
</html>

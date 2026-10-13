<?php
/**
 * 管理后台通用工具函数
 * 
 * 包含：分页渲染、CSV 导出、空状态提示、搜索表单、确认对话框等
 */

/**
 * 渲染分页导航
 */
function renderPagination(int $total, int $page, int $size, string $baseUrl): string
{
    if ($total <= $size) return '';
    $totalPages = (int)ceil($total / $size);
    $html = '<nav style="margin-top:10px"><ul class="pagination" style="margin:0;display:flex;gap:2px;list-style:none;padding:0">';

    // 上一页
    $prevDisabled = $page <= 1 ? ' style="pointer-events:none;opacity:0.5"' : '';
    $html .= '<li' . $prevDisabled . '><a href="' . buildPageUrl($baseUrl, $page - 1) . '" style="padding:5px 10px;border:1px solid #ddd;border-radius:3px;color:#333;text-decoration:none">&laquo;</a></li>';

    // 页码
    $start = max(1, $page - 3);
    $end = min($totalPages, $page + 3);
    if ($start > 1) {
        $html .= '<li><a href="' . buildPageUrl($baseUrl, 1) . '" style="padding:5px 10px;border:1px solid #ddd;border-radius:3px;color:#333;text-decoration:none">1</a></li>';
        if ($start > 2) $html .= '<li style="padding:5px 8px;color:#999">...</li>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $page ? ' style="background:#337ab7;color:#fff;border-color:#337ab7;padding:5px 10px;border:1px solid #337ab7;border-radius:3px;text-decoration:none"' : ' style="padding:5px 10px;border:1px solid #ddd;border-radius:3px;color:#333;text-decoration:none"';
        $html .= '<li><a href="' . buildPageUrl($baseUrl, $i) . '"' . $active . '>' . $i . '</a></li>';
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<li style="padding:5px 8px;color:#999">...</li>';
        $html .= '<li><a href="' . buildPageUrl($baseUrl, $totalPages) . '" style="padding:5px 10px;border:1px solid #ddd;border-radius:3px;color:#333;text-decoration:none">' . $totalPages . '</a></li>';
    }

    // 下一页
    $nextDisabled = $page >= $totalPages ? ' style="pointer-events:none;opacity:0.5"' : '';
    $html .= '<li' . $nextDisabled . '><a href="' . buildPageUrl($baseUrl, $page + 1) . '" style="padding:5px 10px;border:1px solid #ddd;border-radius:3px;color:#333;text-decoration:none">&raquo;</a></li>';
    $html .= '</ul><span style="font-size:12px;color:#999;margin-left:10px">共 ' . number_format($total) . ' 条，第 ' . $page . '/' . $totalPages . ' 页</span></nav>';

    return $html;
}

function buildPageUrl(string $baseUrl, int $page): string
{
    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    // 替换已有的 page 参数
    if (preg_match('/[?&]page=\d+/', $baseUrl)) {
        return preg_replace('/page=\d+/', 'page=' . $page, $baseUrl);
    }
    return $baseUrl . $separator . 'page=' . $page;
}

/**
 * CSV 导出
 * 在页面顶部调用此函数返回数据流后 exit
 */
function exportCsv(array $headers, array $rows, string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache');

    $fp = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, $headers);

    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $h) {
            $key = strtolower(str_replace([' ', '-', '(', ')'], '_', $h));
            $line[] = (is_array($row) ? ($row[$key] ?? $row[$h] ?? '') : ($row->$key ?? ''));
        }
        fputcsv($fp, $line);
    }
    fclose($fp);
    exit;
}

/**
 * 空状态提示
 */
function renderEmpty(string $icon, string $title, string $desc = '', string $actionUrl = '', string $actionLabel = ''): string
{
    $html = '<div class="text-center" style="padding:50px 20px">';
    $html .= '<i class="' . $icon . '" style="font-size:48px;color:#ddd;margin-bottom:15px"></i>';
    $html .= '<h4 style="color:#888;font-weight:400">' . htmlspecialchars($title) . '</h4>';
    if ($desc) $html .= '<p style="color:#aaa;font-size:13px;margin-top:5px">' . htmlspecialchars($desc) . '</p>';
    if ($actionUrl && $actionLabel) {
        $html .= '<a href="' . htmlspecialchars($actionUrl) . '" class="btn btn-primary btn-sm" style="margin-top:10px">' . htmlspecialchars($actionLabel) . '</a>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * 搜索表单（GET 方式，保留现有参数）
 */
function renderSearchForm(string $placeholder, string $keyword, array $extraFields = []): string
{
    $html = '<form method="get" class="sch-form" style="display:inline-block">';
    foreach ($extraFields as $key => $value) {
        $html .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
    }
    $html .= '<div class="input-group" style="display:inline-flex">';
    $html .= '<input type="text" name="keyword" class="form-control input-sm" placeholder="' . htmlspecialchars($placeholder) . '" value="' . htmlspecialchars($keyword) . '" style="width:200px;border-right:none">';
    $html .= '<span class="input-group-btn"><button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i></button></span>';
    $html .= '</div></form>';
    return $html;
}

/**
 * 确认删除按钮
 */
function confirmDelete(string $url, string $msg = '确定删除？'): string
{
    return 'onclick="if(!confirm(\'' . htmlspecialchars($msg, ENT_QUOTES) . '\'))return false;location.href=\'' . htmlspecialchars($url) . '\'"';
}

/**
 * 复制到剪贴板按钮
 */
function copyBtn(string $text, string $label = ''): string
{
    $id = 'cpy_' . substr(md5($text), 0, 6);
    return '<button class="btn btn-xs btn-default" onclick="copyText(\'' . htmlspecialchars($text, ENT_QUOTES) . '\')" title="复制" style="font-size:10px"><i class="fa fa-copy"></i></button>';
}

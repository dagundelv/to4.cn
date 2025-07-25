<?php
require_once 'config.php';

$shortCode = $_GET['code'] ?? '';

if (empty($shortCode)) {
    header('Location: /');
    exit;
}

$db = Database::getInstance();

// 查找短链接
$shortUrl = $db->fetchOne("SELECT * FROM short_urls WHERE short_code = ? AND status = 1", [$shortCode]);

if (!$shortUrl) {
    http_response_code(404);
    include '404.html';
    exit;
}

// 检查是否过期
if ($shortUrl['expires_at'] && strtotime($shortUrl['expires_at']) < time()) {
    http_response_code(410);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>链接已过期</title></head><body><h1>链接已过期</h1><p>抱歉，该短链接已过期。</p></body></html>';
    exit;
}

// 记录访问统计
$clientIP = getClientIP();
$userAgent = getUserAgent();
$referer = getReferer();

// 解析设备信息
$deviceType = 'desktop';
if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
    if (preg_match('/iPad|Tablet/', $userAgent)) {
        $deviceType = 'tablet';
    } else {
        $deviceType = 'mobile';
    }
}

// 解析浏览器
$browser = 'Unknown';
if (preg_match('/Chrome/i', $userAgent)) {
    $browser = 'Chrome';
} elseif (preg_match('/Firefox/i', $userAgent)) {
    $browser = 'Firefox';
} elseif (preg_match('/Safari/i', $userAgent)) {
    $browser = 'Safari';
} elseif (preg_match('/Edge/i', $userAgent)) {
    $browser = 'Edge';
} elseif (preg_match('/Opera/i', $userAgent)) {
    $browser = 'Opera';
}

// 解析操作系统
$os = 'Unknown';
if (preg_match('/Windows NT/i', $userAgent)) {
    $os = 'Windows';
} elseif (preg_match('/Mac OS X/i', $userAgent)) {
    $os = 'macOS';
} elseif (preg_match('/Linux/i', $userAgent)) {
    $os = 'Linux';
} elseif (preg_match('/Android/i', $userAgent)) {
    $os = 'Android';
} elseif (preg_match('/iOS/i', $userAgent)) {
    $os = 'iOS';
}

// 插入访问记录
$clickData = [
    'short_url_id' => $shortUrl['id'],
    'ip_address' => $clientIP,
    'user_agent' => $userAgent,
    'referer' => $referer ?: null,
    'device_type' => $deviceType,
    'browser' => $browser,
    'os' => $os
];

$db->insert('click_stats', $clickData);

// 更新点击计数
$db->query("UPDATE short_urls SET click_count = click_count + 1 WHERE id = ?", [$shortUrl['id']]);

// 重定向到原始URL
header('Location: ' . $shortUrl['original_url'], true, 302);
exit;
?>
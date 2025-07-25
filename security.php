<?php
// 安全工具函数库

// 设置安全响应头
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net; style-src \'self\' \'unsafe-inline\' cdn.jsdelivr.net; img-src \'self\' data: chart.googleapis.com; font-src \'self\' cdn.jsdelivr.net;');
    
    // 仅在HTTPS时设置HSTS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// 生成CSRF令牌
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_time']) || 
        (time() - $_SESSION['csrf_time']) > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

// 验证CSRF令牌
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && 
           isset($_SESSION['csrf_time']) &&
           (time() - $_SESSION['csrf_time']) <= 3600 &&
           hash_equals($_SESSION['csrf_token'], $token);
}

// 速率限制检查
function checkRateLimit($key, $limit = 10, $window = 60) {
    $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($key);
    $now = time();
    
    $requests = [];
    if (file_exists($cacheFile)) {
        $requests = json_decode(file_get_contents($cacheFile), true) ?: [];
    }
    
    // 清理过期请求
    $requests = array_filter($requests, function($time) use ($now, $window) {
        return ($now - $time) < $window;
    });
    
    // 检查是否超过限制
    if (count($requests) >= $limit) {
        return false;
    }
    
    // 记录当前请求
    $requests[] = $now;
    file_put_contents($cacheFile, json_encode($requests));
    
    return true;
}

// 清理和验证输入
function sanitizeInput($input, $type = 'string') {
    switch ($type) {
        case 'url':
            $input = filter_var($input, FILTER_SANITIZE_URL);
            return filter_var($input, FILTER_VALIDATE_URL) ? $input : false;
        case 'email':
            $input = filter_var($input, FILTER_SANITIZE_EMAIL);
            return filter_var($input, FILTER_VALIDATE_EMAIL) ? $input : false;
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
        case 'string':
        default:
            return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}

// 检查URL白名单
function isUrlAllowed($url) {
    $parsedUrl = parse_url($url);
    if (!$parsedUrl || empty($parsedUrl['host'])) {
        return false;
    }
    
    $host = strtolower($parsedUrl['host']);
    
    // 检查危险协议
    $dangerousSchemes = ['javascript', 'data', 'vbscript', 'file'];
    if (isset($parsedUrl['scheme']) && in_array(strtolower($parsedUrl['scheme']), $dangerousSchemes)) {
        return false;
    }
    
    // 检查本地和私有IP
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    
    // 检查域名黑名单
    $blacklistedDomains = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '::1'
    ];
    
    foreach ($blacklistedDomains as $blocked) {
        if ($host === $blocked || strpos($host, '.' . $blocked) !== false) {
            return false;
        }
    }
    
    return true;
}

// 记录安全事件
function logSecurityEvent($event, $details = []) {
    $logFile = __DIR__ . '/logs/security.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => getClientIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'event' => $event,
        'details' => $details,
        'session_id' => session_id()
    ];
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

// 检测可疑活动
function detectSuspiciousActivity() {
    $ip = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // 检查常见攻击模式
    $suspiciousPatterns = [
        '/\b(union|select|insert|delete|drop|create|alter)\b/i',
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
        '/javascript:/i',
        '/vbscript:/i',
        '/data:text\/html/i'
    ];
    
    $requestData = json_encode($_REQUEST);
    foreach ($suspiciousPatterns as $pattern) {
        if (preg_match($pattern, $requestData)) {
            logSecurityEvent('suspicious_request', [
                'pattern' => $pattern,
                'request_data' => $requestData
            ]);
            return true;
        }
    }
    
    return false;
}

// 阻止恶意请求
function blockMaliciousRequest() {
    if (detectSuspiciousActivity()) {
        http_response_code(403);
        logSecurityEvent('blocked_malicious_request');
        die('Access Forbidden');
    }
}
?>
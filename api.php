<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// 路由处理
switch ($method) {
    case 'POST':
        if ($path === 'shorten') {
            createShortUrl();
        } elseif ($path === 'register') {
            registerUser();
        } elseif ($path === 'login') {
            loginUser();
        }
        break;
    case 'GET':
        if ($path === 'stats') {
            getStats();
        } elseif ($path === 'user-urls') {
            getUserUrls();
        } elseif ($path === 'global-stats') {
            getGlobalStats();
        }
        break;
    case 'DELETE':
        if ($path === 'batch-delete') {
            batchDeleteUrls();
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => '不支持的请求方法']);
}

// 创建短链接
function createShortUrl() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $originalUrl = $input['url'] ?? '';
    $title = $input['title'] ?? '';
    $userId = $_SESSION['user_id'] ?? null;
    
    if (empty($originalUrl)) {
        http_response_code(400);
        echo json_encode(['error' => '请提供有效的URL']);
        return;
    }
    
    if (!isValidUrl($originalUrl)) {
        http_response_code(400);
        echo json_encode(['error' => '无效的URL格式']);
        return;
    }
    
    // 检查域名黑名单
    $domain = parse_url($originalUrl, PHP_URL_HOST);
    $blacklistedDomain = $db->fetchOne("SELECT * FROM domain_blacklist WHERE domain = ?", [$domain]);
    if ($blacklistedDomain) {
        http_response_code(403);
        echo json_encode(['error' => '该域名已被禁止']);
        return;
    }
    
    // 检查关键词黑名单（优化版）
    static $blacklistKeywords = null;
    if ($blacklistKeywords === null) {
        $blacklistKeywords = array_column(
            $db->fetchAll("SELECT keyword FROM keyword_blacklist"), 
            'keyword'
        );
    }
    
    $content = strtolower($originalUrl . ' ' . $title);
    foreach ($blacklistKeywords as $keyword) {
        if (stripos($content, $keyword) !== false) {
            http_response_code(403);
            echo json_encode(['error' => '内容包含被禁止的关键词']);
            return;
        }
    }
    
    // 生成短码
    do {
        $shortCode = generateShortCode();
        $existing = $db->fetchOne("SELECT id FROM short_urls WHERE short_code = ?", [$shortCode]);
    } while ($existing);
    
    // 保存到数据库
    $data = [
        'user_id' => $userId,
        'original_url' => $originalUrl,
        'short_code' => $shortCode,
        'title' => $title
    ];
    
    if ($db->insert('short_urls', $data)) {
        $shortUrl = SITE_URL . '/' . $shortCode;
        echo json_encode([
            'success' => true,
            'short_url' => $shortUrl,
            'short_code' => $shortCode,
            'qr_code' => SITE_URL . '/qr.php?code=' . $shortCode
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '创建短链接失败']);
    }
}

// 用户注册
function registerUser() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => '请填写所有必填字段']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => '无效的邮箱格式']);
        return;
    }
    
    // 检查用户名和邮箱是否已存在
    $existingUser = $db->fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
    if ($existingUser) {
        http_response_code(409);
        echo json_encode(['error' => '用户名或邮箱已存在']);
        return;
    }
    
    $hashedPassword = hashPassword($password);
    $data = [
        'username' => $username,
        'email' => $email,
        'password' => $hashedPassword
    ];
    
    if ($db->insert('users', $data)) {
        echo json_encode(['success' => true, 'message' => '注册成功']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '注册失败']);
    }
}

// 用户登录
function loginUser() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => '请填写用户名和密码']);
        return;
    }
    
    $user = $db->fetchOne("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
    
    if (!$user || !verifyPassword($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => '用户名或密码错误']);
        return;
    }
    
    if ($user['status'] == 0) {
        http_response_code(403);
        echo json_encode(['error' => '账户已被禁用']);
        return;
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['is_admin'] = $user['is_admin'];
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'is_admin' => $user['is_admin']
        ]
    ]);
}

// 获取统计信息
function getStats() {
    global $db;
    
    $shortCode = $_GET['code'] ?? '';
    if (empty($shortCode)) {
        http_response_code(400);
        echo json_encode(['error' => '请提供短码']);
        return;
    }
    
    $shortUrl = $db->fetchOne("SELECT * FROM short_urls WHERE short_code = ?", [$shortCode]);
    if (!$shortUrl) {
        http_response_code(404);
        echo json_encode(['error' => '短链接不存在']);
        return;
    }
    
    // 获取点击统计
    $clickStats = $db->fetchAll("
        SELECT 
            DATE(clicked_at) as date,
            COUNT(*) as clicks,
            COUNT(DISTINCT ip_address) as unique_visitors
        FROM click_stats 
        WHERE short_url_id = ? 
        GROUP BY DATE(clicked_at) 
        ORDER BY date DESC 
        LIMIT 30
    ", [$shortUrl['id']]);
    
    // 获取来源统计
    $refererStats = $db->fetchAll("
        SELECT 
            COALESCE(referer, '直接访问') as referer,
            COUNT(*) as clicks
        FROM click_stats 
        WHERE short_url_id = ? 
        GROUP BY referer 
        ORDER BY clicks DESC 
        LIMIT 10
    ", [$shortUrl['id']]);
    
    // 获取设备统计
    $deviceStats = $db->fetchAll("
        SELECT 
            device_type,
            COUNT(*) as clicks
        FROM click_stats 
        WHERE short_url_id = ? 
        GROUP BY device_type 
        ORDER BY clicks DESC
    ", [$shortUrl['id']]);
    
    echo json_encode([
        'url_info' => $shortUrl,
        'click_stats' => $clickStats,
        'referer_stats' => $refererStats,
        'device_stats' => $deviceStats,
        'total_clicks' => $shortUrl['click_count']
    ]);
}

// 获取用户的短链接列表
function getUserUrls() {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '请先登录']);
        return;
    }
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $urls = $db->fetchAll("
        SELECT 
            id, original_url, short_code, title, click_count, status, created_at
        FROM short_urls 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ", [$_SESSION['user_id'], $limit, $offset]);
    
    $total = $db->fetchOne("SELECT COUNT(*) as count FROM short_urls WHERE user_id = ?", [$_SESSION['user_id']]);
    
    echo json_encode([
        'urls' => $urls,
        'total' => $total['count'],
        'page' => $page,
        'per_page' => $limit
    ]);
}

// 获取全局统计信息
function getGlobalStats() {
    global $db;
    
    // 获取总链接数
    $totalUrls = $db->fetchOne("SELECT COUNT(*) as count FROM short_urls WHERE status = 1");
    
    // 获取总点击数
    $totalClicks = $db->fetchOne("SELECT SUM(click_count) as count FROM short_urls WHERE status = 1");
    
    // 获取总用户数
    $totalUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 1");
    
    echo json_encode([
        'success' => true,
        'total_urls' => $totalUrls['count'] ?? 0,
        'total_clicks' => $totalClicks['count'] ?? 0,
        'total_users' => $totalUsers['count'] ?? 0
    ]);
}

// 批量删除URL
function batchDeleteUrls() {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => '请先登录']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $urlIds = $input['url_ids'] ?? [];
    
    if (empty($urlIds) || !is_array($urlIds)) {
        http_response_code(400);
        echo json_encode(['error' => '请提供要删除的URL ID']);
        return;
    }
    
    $userId = $_SESSION['user_id'];
    $deletedCount = 0;
    
    foreach ($urlIds as $urlId) {
        $urlId = intval($urlId);
        
        // 验证URL所有权
        $url = $db->fetchOne("SELECT id FROM short_urls WHERE id = ? AND user_id = ?", [$urlId, $userId]);
        if ($url) {
            // 删除URL和相关统计
            if ($db->delete('short_urls', 'id = ?', [$urlId])) {
                $db->delete('click_stats', 'short_url_id = ?', [$urlId]);
                $deletedCount++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "成功删除 {$deletedCount} 个链接",
        'deleted_count' => $deletedCount
    ]);
}
?>
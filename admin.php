<?php
require_once 'config.php';

// 检查用户是否已登录且为管理员
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();

// 处理管理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'toggle_user_status':
            $userId = intval($_POST['user_id']);
            $user = $db->fetchOne("SELECT status FROM users WHERE id = ?", [$userId]);
            if ($user) {
                $newStatus = $user['status'] == 1 ? 0 : 1;
                $db->update('users', ['status' => $newStatus], 'id = ?', [$userId]);
                $message = $newStatus == 1 ? '用户已启用' : '用户已禁用';
            }
            break;
            
        case 'delete_user':
            $userId = intval($_POST['user_id']);
            if ($userId != $_SESSION['user_id']) { // 不能删除自己
                // 先删除用户的短链接和统计
                $userUrls = $db->fetchAll("SELECT id FROM short_urls WHERE user_id = ?", [$userId]);
                foreach ($userUrls as $url) {
                    $db->delete('click_stats', 'short_url_id = ?', [$url['id']]);
                }
                $db->delete('short_urls', 'user_id = ?', [$userId]);
                $db->delete('users', 'id = ?', [$userId]);
                $message = '用户删除成功';
            } else {
                $error = '不能删除自己的账户';
            }
            break;
            
        case 'toggle_url_status':
            $urlId = intval($_POST['url_id']);
            $url = $db->fetchOne("SELECT status FROM short_urls WHERE id = ?", [$urlId]);
            if ($url) {
                $newStatus = $url['status'] == 1 ? 0 : 1;
                $db->update('short_urls', ['status' => $newStatus], 'id = ?', [$urlId]);
                $message = $newStatus == 1 ? 'URL已启用' : 'URL已禁用';
            }
            break;
            
        case 'delete_url':
            $urlId = intval($_POST['url_id']);
            $db->delete('click_stats', 'short_url_id = ?', [$urlId]);
            $db->delete('short_urls', 'id = ?', [$urlId]);
            $message = 'URL删除成功';
            break;
            
        case 'add_domain_blacklist':
            $domain = trim($_POST['domain']);
            if (!empty($domain)) {
                $existing = $db->fetchOne("SELECT id FROM domain_blacklist WHERE domain = ?", [$domain]);
                if (!$existing) {
                    $db->insert('domain_blacklist', ['domain' => $domain, 'created_at' => date('Y-m-d H:i:s')]);
                    $message = '域名已添加到黑名单';
                } else {
                    $error = '域名已存在于黑名单中';
                }
            }
            break;
            
        case 'remove_domain_blacklist':
            $domain = $_POST['domain'];
            $db->delete('domain_blacklist', 'domain = ?', [$domain]);
            $message = '域名已从黑名单移除';
            break;
            
        case 'add_keyword_blacklist':
            $keyword = trim($_POST['keyword']);
            if (!empty($keyword)) {
                $existing = $db->fetchOne("SELECT id FROM keyword_blacklist WHERE keyword = ?", [$keyword]);
                if (!$existing) {
                    $db->insert('keyword_blacklist', ['keyword' => $keyword, 'created_at' => date('Y-m-d H:i:s')]);
                    $message = '关键词已添加到黑名单';
                } else {
                    $error = '关键词已存在于黑名单中';
                }
            }
            break;
            
        case 'remove_keyword_blacklist':
            $keyword = $_POST['keyword'];
            $db->delete('keyword_blacklist', 'keyword = ?', [$keyword]);
            $message = '关键词已从黑名单移除';
            break;
    }
}

// 获取系统统计
$systemStats = [
    'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
    'active_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 1")['count'],
    'total_urls' => $db->fetchOne("SELECT COUNT(*) as count FROM short_urls")['count'],
    'active_urls' => $db->fetchOne("SELECT COUNT(*) as count FROM short_urls WHERE status = 1")['count'],
    'total_clicks' => $db->fetchOne("SELECT COALESCE(SUM(click_count), 0) as count FROM short_urls")['count'],
    'today_clicks' => $db->fetchOne("SELECT COUNT(*) as count FROM click_stats WHERE DATE(clicked_at) = CURDATE()")['count'],
];

// 获取最近注册的用户
$recentUsers = $db->fetchAll("
    SELECT id, username, email, status, is_admin, created_at
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 10
");

// 获取最近创建的URL
$recentUrls = $db->fetchAll("
    SELECT su.*, u.username
    FROM short_urls su
    LEFT JOIN users u ON su.user_id = u.id
    ORDER BY su.created_at DESC 
    LIMIT 10
");

// 获取黑名单
$domainBlacklist = $db->fetchAll("SELECT * FROM domain_blacklist ORDER BY created_at DESC");
$keywordBlacklist = $db->fetchAll("SELECT * FROM keyword_blacklist ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
            color: white;
            padding: 2rem 0;
        }
        .stats-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .sidebar {
            background: #f8f9fa;
            min-height: calc(100vh - 200px);
        }
        .nav-pills .nav-link {
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .nav-pills .nav-link.active {
            background-color: #dc3545;
        }
        .tab-content {
            background: white;
            border-radius: 0.375rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .status-badge {
            cursor: pointer;
        }
        .blacklist-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">
                <i class="bi bi-shield-check"></i> <?php echo SITE_NAME; ?> 管理后台
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php">用户控制台</a></li>
                        <li><a class="dropdown-item" href="profile.php">个人资料</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/">返回首页</a></li>
                        <li><a class="dropdown-item" href="logout.php">退出登录</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- 管理后台头部 -->
    <section class="admin-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2"><i class="bi bi-gear"></i> 系统管理后台</h2>
                    <p class="mb-0 opacity-75">管理用户、链接、黑名单和系统设置</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge bg-light text-dark">
                        在线管理员: <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <div class="container-fluid my-4">
        <!-- 显示消息 -->
        <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- 系统统计 -->
        <div class="row mb-4">
            <div class="col-12">
                <h3 class="mb-3"><i class="bi bi-graph-up"></i> 系统概览</h3>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-people-fill display-6 text-primary mb-2"></i>
                        <h4 class="text-primary"><?php echo number_format($systemStats['total_users']); ?></h4>
                        <small class="text-muted">总用户数</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-person-check display-6 text-success mb-2"></i>
                        <h4 class="text-success"><?php echo number_format($systemStats['active_users']); ?></h4>
                        <small class="text-muted">活跃用户</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-link display-6 text-info mb-2"></i>
                        <h4 class="text-info"><?php echo number_format($systemStats['total_urls']); ?></h4>
                        <small class="text-muted">总链接数</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-link-45deg display-6 text-warning mb-2"></i>
                        <h4 class="text-warning"><?php echo number_format($systemStats['active_urls']); ?></h4>
                        <small class="text-muted">活跃链接</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-eye display-6 text-danger mb-2"></i>
                        <h4 class="text-danger"><?php echo number_format($systemStats['total_clicks']); ?></h4>
                        <small class="text-muted">总点击量</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-day display-6 text-secondary mb-2"></i>
                        <h4 class="text-secondary"><?php echo number_format($systemStats['today_clicks']); ?></h4>
                        <small class="text-muted">今日点击</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 管理选项卡 -->
        <div class="row">
            <div class="col-md-3">
                <div class="sidebar p-3">
                    <ul class="nav nav-pills flex-column" id="admin-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="overview-tab" data-bs-toggle="pill" 
                                    data-bs-target="#overview" type="button" role="tab">
                                <i class="bi bi-house"></i> 系统概览
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="users-tab" data-bs-toggle="pill" 
                                    data-bs-target="#users" type="button" role="tab">
                                <i class="bi bi-people"></i> 用户管理
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="urls-tab" data-bs-toggle="pill" 
                                    data-bs-target="#urls" type="button" role="tab">
                                <i class="bi bi-link"></i> 链接管理
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="blacklist-tab" data-bs-toggle="pill" 
                                    data-bs-target="#blacklist" type="button" role="tab">
                                <i class="bi bi-shield-x"></i> 黑名单管理
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="reports-tab" data-bs-toggle="pill" 
                                    data-bs-target="#reports" type="button" role="tab">
                                <i class="bi bi-bar-chart"></i> 统计报表
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="settings-tab" data-bs-toggle="pill" 
                                    data-bs-target="#settings" type="button" role="tab">
                                <i class="bi bi-gear"></i> 系统设置
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="tab-content p-4" id="admin-tabContent">
                    <!-- 系统概览 -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <h4 class="mb-4">系统概览</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">最近注册用户</h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>用户名</th>
                                                        <th>状态</th>
                                                        <th>注册时间</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($recentUsers, 0, 5) as $user): ?>
                                                        <tr>
                                                            <td>
                                                                <?php echo htmlspecialchars($user['username']); ?>
                                                                <?php if ($user['is_admin']): ?>
                                                                    <small class="badge bg-warning">管理员</small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $user['status'] ? 'success' : 'danger'; ?>">
                                                                    <?php echo $user['status'] ? '正常' : '禁用'; ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <small><?php echo date('m-d H:i', strtotime($user['created_at'])); ?></small>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">最近创建链接</h6>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>短码</th>
                                                        <th>创建者</th>
                                                        <th>点击</th>
                                                        <th>时间</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($recentUrls, 0, 5) as $url): ?>
                                                        <tr>
                                                            <td>
                                                                <code><?php echo $url['short_code']; ?></code>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($url['username'] ?: '游客'); ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-primary"><?php echo $url['click_count']; ?></span>
                                                            </td>
                                                            <td>
                                                                <small><?php echo date('m-d H:i', strtotime($url['created_at'])); ?></small>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 用户管理 -->
                    <div class="tab-pane fade" id="users" role="tabpanel">
                        <h4 class="mb-4">用户管理</h4>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>用户名</th>
                                        <th>邮箱</th>
                                        <th>状态</th>
                                        <th>角色</th>
                                        <th>注册时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsers as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_user_status">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-<?php echo $user['status'] ? 'success' : 'danger'; ?> status-badge">
                                                        <?php echo $user['status'] ? '正常' : '禁用'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <?php if ($user['is_admin']): ?>
                                                    <span class="badge bg-warning">管理员</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">普通用户</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <form method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('确定要删除此用户吗？')">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 链接管理 -->
                    <div class="tab-pane fade" id="urls" role="tabpanel">
                        <h4 class="mb-4">链接管理</h4>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>短码</th>
                                        <th>标题</th>
                                        <th>原始URL</th>
                                        <th>创建者</th>
                                        <th>点击量</th>
                                        <th>状态</th>
                                        <th>创建时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUrls as $url): ?>
                                        <tr>
                                            <td><code><?php echo $url['short_code']; ?></code></td>
                                            <td>
                                                <span title="<?php echo htmlspecialchars($url['title']); ?>">
                                                    <?php echo htmlspecialchars(substr($url['title'] ?: '无标题', 0, 20)) . (strlen($url['title']) > 20 ? '...' : ''); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span title="<?php echo htmlspecialchars($url['original_url']); ?>">
                                                    <?php echo htmlspecialchars(substr($url['original_url'], 0, 30)) . '...'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($url['username'] ?: '游客'); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo number_format($url['click_count']); ?></span>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_url_status">
                                                    <input type="hidden" name="url_id" value="<?php echo $url['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-<?php echo $url['status'] ? 'success' : 'danger'; ?> status-badge">
                                                        <?php echo $url['status'] ? '正常' : '禁用'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td><?php echo date('m-d H:i', strtotime($url['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="stats.php?code=<?php echo $url['short_code']; ?>" 
                                                       class="btn btn-outline-info" title="查看统计">
                                                        <i class="bi bi-bar-chart"></i>
                                                    </a>
                                                    <form method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('确定要删除此链接吗？')">
                                                        <input type="hidden" name="action" value="delete_url">
                                                        <input type="hidden" name="url_id" value="<?php echo $url['id']; ?>">
                                                        <button type="submit" class="btn btn-outline-danger" title="删除">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 黑名单管理 -->
                    <div class="tab-pane fade" id="blacklist" role="tabpanel">
                        <h4 class="mb-4">黑名单管理</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">域名黑名单</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" class="mb-3">
                                            <input type="hidden" name="action" value="add_domain_blacklist">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="domain" 
                                                       placeholder="输入要封禁的域名..." required>
                                                <button type="submit" class="btn btn-danger">添加</button>
                                            </div>
                                        </form>
                                        
                                        <div style="max-height: 300px; overflow-y: auto;">
                                            <?php foreach ($domainBlacklist as $domain): ?>
                                                <div class="blacklist-item">
                                                    <span><?php echo htmlspecialchars($domain['domain']); ?></span>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="remove_domain_blacklist">
                                                        <input type="hidden" name="domain" value="<?php echo $domain['domain']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($domainBlacklist)): ?>
                                                <p class="text-muted text-center">暂无域名黑名单</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">关键词黑名单</h6>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" class="mb-3">
                                            <input type="hidden" name="action" value="add_keyword_blacklist">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="keyword" 
                                                       placeholder="输入要封禁的关键词..." required>
                                                <button type="submit" class="btn btn-danger">添加</button>
                                            </div>
                                        </form>
                                        
                                        <div style="max-height: 300px; overflow-y: auto;">
                                            <?php foreach ($keywordBlacklist as $keyword): ?>
                                                <div class="blacklist-item">
                                                    <span><?php echo htmlspecialchars($keyword['keyword']); ?></span>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="remove_keyword_blacklist">
                                                        <input type="hidden" name="keyword" value="<?php echo $keyword['keyword']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <?php if (empty($keywordBlacklist)): ?>
                                                <p class="text-muted text-center">暂无关键词黑名单</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 统计报表 -->
                    <div class="tab-pane fade" id="reports" role="tabpanel">
                        <h4 class="mb-4">统计报表</h4>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">用户注册趋势</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="userChart" style="height: 200px;"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">链接创建趋势</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="urlChart" style="height: 200px;"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 系统设置 -->
                    <div class="tab-pane fade" id="settings" role="tabpanel">
                        <h4 class="mb-4">系统设置</h4>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">基本设置</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">站点名称</label>
                                            <input type="text" class="form-control" value="<?php echo SITE_NAME; ?>" readonly>
                                            <div class="form-text">在 config.php 中修改</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">站点URL</label>
                                            <input type="text" class="form-control" value="<?php echo SITE_URL; ?>" readonly>
                                            <div class="form-text">在 config.php 中修改</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">短码长度</label>
                                            <input type="number" class="form-control" value="<?php echo SHORT_CODE_LENGTH; ?>" readonly>
                                            <div class="form-text">在 config.php 中修改</div>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i>
                                            系统配置需要直接修改 config.php 文件。为了安全考虑，不提供在线修改功能。
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">系统信息</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>PHP版本:</strong> <?php echo PHP_VERSION; ?></p>
                                        <p><strong>服务器时间:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                                        <p><strong>数据库:</strong> MySQL</p>
                                        <p><strong>系统状态:</strong> <span class="badge bg-success">正常运行</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 用户注册趋势图
            const userCtx = document.getElementById('userChart').getContext('2d');
            
            // 模拟数据 - 实际应用中应从服务器获取
            const userLabels = [];
            const userData = [];
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                userLabels.push(date.toLocaleDateString('zh-CN', { month: 'short', day: 'numeric' }));
                userData.push(Math.floor(Math.random() * 10) + 1);
            }
            
            new Chart(userCtx, {
                type: 'line',
                data: {
                    labels: userLabels,
                    datasets: [{
                        label: '新注册用户',
                        data: userData,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // 链接创建趋势图
            const urlCtx = document.getElementById('urlChart').getContext('2d');
            
            const urlLabels = [];
            const urlData = [];
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                urlLabels.push(date.toLocaleDateString('zh-CN', { month: 'short', day: 'numeric' }));
                urlData.push(Math.floor(Math.random() * 20) + 5);
            }
            
            new Chart(urlCtx, {
                type: 'bar',
                data: {
                    labels: urlLabels,
                    datasets: [{
                        label: '新建链接',
                        data: urlData,
                        backgroundColor: 'rgba(108, 117, 125, 0.7)',
                        borderColor: '#6c757d',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 5
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
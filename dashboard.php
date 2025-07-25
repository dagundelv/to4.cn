<?php
require_once 'config.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();

// 获取用户的短链接统计
$userStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_urls,
        COALESCE(SUM(click_count), 0) as total_clicks
    FROM short_urls 
    WHERE user_id = ? AND status = 1
", [$_SESSION['user_id']]);

// 获取最近的短链接
$recentUrls = $db->fetchAll("
    SELECT 
        id, original_url, short_code, title, click_count, created_at
    FROM short_urls 
    WHERE user_id = ? AND status = 1
    ORDER BY created_at DESC 
    LIMIT 10
", [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>控制台 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stats-card {
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .url-item {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        .url-item:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-link-45deg"></i> <?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php">控制台</a></li>
                        <?php if ($_SESSION['is_admin']): ?>
                            <li><a class="dropdown-item" href="admin.php">管理后台</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">退出登录</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-speedometer2"></i> 控制台
                    <small class="text-muted">欢迎回来，<?php echo htmlspecialchars($_SESSION['username']); ?>！</small>
                </h2>
            </div>
        </div>

        <!-- 统计卡片 -->
        <div class="row mb-5">
            <div class="col-md-6">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-link-45deg display-4 text-primary mb-3"></i>
                        <h3 class="text-primary"><?php echo $userStats['total_urls'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">创建的短链接</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up display-4 text-success mb-3"></i>
                        <h3 class="text-success"><?php echo $userStats['total_clicks'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">总点击量</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 快速创建 -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> 快速创建短链接</h5>
                    </div>
                    <div class="card-body">
                        <div id="alert-container"></div>
                        <form id="quickForm">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <input type="url" class="form-control" id="quickUrl" placeholder="输入要缩短的网址..." required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-magic"></i> 生成短链接
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- 最近的短链接 -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> 最近的短链接</h5>
                        <a href="urls.php" class="btn btn-outline-primary btn-sm">查看全部</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentUrls)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox display-4 text-muted"></i>
                                <p class="text-muted mt-3">您还没有创建任何短链接</p>
                                <a href="/" class="btn btn-primary">创建第一个短链接</a>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recentUrls as $url): ?>
                                    <div class="list-group-item url-item">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h6 class="mb-1">
                                                    <?php echo htmlspecialchars($url['title'] ?: '无标题'); ?>
                                                </h6>
                                                <p class="mb-1 text-muted small">
                                                    <?php echo htmlspecialchars(substr($url['original_url'], 0, 80)) . (strlen($url['original_url']) > 80 ? '...' : ''); ?>
                                                </p>
                                                <small class="text-muted">
                                                    创建于 <?php echo date('Y-m-d H:i', strtotime($url['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="input-group">
                                                    <input type="text" class="form-control form-control-sm" 
                                                           value="<?php echo SITE_URL . '/' . $url['short_code']; ?>" readonly>
                                                    <button class="btn btn-outline-secondary btn-sm copy-btn" type="button"
                                                            data-url="<?php echo SITE_URL . '/' . $url['short_code']; ?>">
                                                        <i class="bi bi-clipboard"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <span class="badge bg-primary rounded-pill">
                                                    <i class="bi bi-eye"></i> <?php echo $url['click_count']; ?>
                                                </span>
                                                <div class="btn-group btn-group-sm mt-2">
                                                    <a href="stats.php?code=<?php echo $url['short_code']; ?>" 
                                                       class="btn btn-outline-info btn-sm">
                                                        <i class="bi bi-bar-chart"></i>
                                                    </a>
                                                    <a href="qr.php?code=<?php echo $url['short_code']; ?>" 
                                                       class="btn btn-outline-secondary btn-sm" target="_blank">
                                                        <i class="bi bi-qr-code"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 快速创建表单
        document.getElementById('quickForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const url = document.getElementById('quickUrl').value;
            const alertContainer = document.getElementById('alert-container');
            
            try {
                const response = await fetch('/api/shorten', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        url: url,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('短链接创建成功！', 'success');
                    document.getElementById('quickUrl').value = '';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.error, 'danger');
                }
            } catch (error) {
                showAlert('网络错误，请稍后重试', 'danger');
            }
        });
        
        // 复制功能
        document.querySelectorAll('.copy-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const url = this.dataset.url;
                navigator.clipboard.writeText(url).then(() => {
                    const icon = this.querySelector('i');
                    icon.className = 'bi bi-check';
                    setTimeout(() => {
                        icon.className = 'bi bi-clipboard';
                    }, 2000);
                });
            });
        });
        
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
    </script>
</body>
</html>
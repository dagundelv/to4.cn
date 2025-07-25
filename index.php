<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5rem 0;
        }
        .feature-card {
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .short-url-form {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .qr-code-container {
            text-align: center;
            margin-top: 20px;
        }
        .stats-section {
            background-color: #f8f9fa;
            padding: 3rem 0;
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
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/">首页</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">功能特色</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="dashboard.php">控制台</a></li>
                                <li><a class="dropdown-item" href="profile.php">个人资料</a></li>
                                <?php if ($_SESSION['is_admin']): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="admin.php">管理后台</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">退出登录</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">登录</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">注册</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- 主要内容区域 -->
    <main>
        <!-- 英雄区域 -->
        <section class="hero-section">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="text-center mb-5">
                            <h1 class="display-4 fw-bold mb-3">简单快速的短网址服务</h1>
                            <p class="lead">将长网址转换为简短易记的链接，支持统计分析和二维码生成</p>
                        </div>

                        <!-- 短网址生成表单 -->
                        <div class="short-url-form">
                            <div id="alert-container"></div>
                            
                            <form id="shortenForm">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="originalUrl" class="form-label">
                                            <i class="bi bi-link"></i> 输入要缩短的网址
                                        </label>
                                        <input type="url" class="form-control form-control-lg" 
                                               id="originalUrl" placeholder="https://example.com/very/long/url" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="urlTitle" class="form-label">标题（可选）</label>
                                        <input type="text" class="form-control" id="urlTitle" placeholder="为这个链接起个名字">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-lg w-100">
                                            <i class="bi bi-magic"></i> 生成短网址
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- 结果显示区域 -->
                            <div id="result-section" class="mt-4" style="display: none;">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5>您的短网址</h5>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="shortUrl" readonly>
                                            <button class="btn btn-outline-secondary" type="button" id="copyBtn">
                                                <i class="bi bi-clipboard"></i> 复制
                                            </button>
                                        </div>
                                        <small class="text-muted">点击复制按钮将短网址复制到剪贴板</small>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="qr-code-container">
                                            <h6>二维码</h6>
                                            <img id="qrCode" src="" alt="二维码" class="img-fluid" style="max-width: 150px;">
                                            <br>
                                            <small class="text-muted">扫描分享</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 功能特色 -->
        <section id="features" class="py-5">
            <div class="container">
                <div class="row text-center mb-5">
                    <div class="col-12">
                        <h2 class="fw-bold">功能特色</h2>
                        <p class="lead text-muted">为什么选择我们的短网址服务</p>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card feature-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bi bi-speedometer2 display-4 text-primary"></i>
                                </div>
                                <h5 class="card-title">快速生成</h5>
                                <p class="card-text">瞬间将长网址转换为简短易记的链接，无需等待</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card feature-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bi bi-bar-chart display-4 text-success"></i>
                                </div>
                                <h5 class="card-title">详细统计</h5>
                                <p class="card-text">提供访问量统计、来源分析、设备统计等详细数据</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card feature-card h-100">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="bi bi-qr-code display-4 text-info"></i>
                                </div>
                                <h5 class="card-title">二维码生成</h5>
                                <p class="card-text">自动生成二维码，方便移动端扫描分享</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 统计信息 -->
        <section class="stats-section">
            <div class="container">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <h3 class="display-6 fw-bold text-primary" id="totalUrls">-</h3>
                            <p class="text-muted">累计短网址</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <h3 class="display-6 fw-bold text-success" id="totalClicks">-</h3>
                            <p class="text-muted">累计点击量</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <h3 class="display-6 fw-bold text-info" id="totalUsers">-</h3>
                            <p class="text-muted">注册用户</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- 页脚 -->
    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?php echo SITE_NAME; ?></h5>
                    <p class="mb-0">简单、快速、可靠的短网址服务</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>&copy; 2024 <?php echo SITE_NAME; ?>. All rights reserved.</small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 短网址生成表单处理
        document.getElementById('shortenForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const originalUrl = document.getElementById('originalUrl').value;
            const title = document.getElementById('urlTitle').value;
            const alertContainer = document.getElementById('alert-container');
            
            try {
                const response = await fetch('/api/shorten', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        url: originalUrl,
                        title: title,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // 显示结果
                    document.getElementById('shortUrl').value = data.short_url;
                    document.getElementById('qrCode').src = data.qr_code;
                    document.getElementById('result-section').style.display = 'block';
                    
                    // 清除错误信息
                    alertContainer.innerHTML = '';
                    
                    // 滚动到结果区域
                    document.getElementById('result-section').scrollIntoView({ behavior: 'smooth' });
                } else {
                    showAlert(data.error, 'danger');
                }
            } catch (error) {
                showAlert('网络错误，请稍后重试', 'danger');
            }
        });
        
        // 复制功能
        document.getElementById('copyBtn').addEventListener('click', function() {
            const shortUrl = document.getElementById('shortUrl');
            shortUrl.select();
            document.execCommand('copy');
            
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check"></i> 已复制';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 2000);
        });
        
        // 显示警告信息
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
        
        // 加载统计数据
        async function loadStats() {
            try {
                const response = await fetch('/api/global-stats');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalUrls').textContent = data.total_urls || 0;
                    document.getElementById('totalClicks').textContent = data.total_clicks || 0;
                    document.getElementById('totalUsers').textContent = data.total_users || 0;
                }
            } catch (error) {
                console.log('Failed to load stats');
            }
        }
        
        // 页面加载时获取统计数据
        loadStats();
    </script>
</body>
</html>
<?php
require_once 'config.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// 处理个人资料更新
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        
        $errors = [];
        
        // 验证邮箱
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '请输入有效的邮箱地址';
        }
        
        // 检查邮箱是否已被其他用户使用
        $existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
        if ($existingUser) {
            $errors[] = '该邮箱已被其他用户使用';
        }
        
        // 如果要修改密码，验证当前密码
        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                $errors[] = '请输入当前密码';
            } else {
                $user = $db->fetchOne("SELECT password FROM users WHERE id = ?", [$userId]);
                if (!verifyPassword($currentPassword, $user['password'])) {
                    $errors[] = '当前密码错误';
                }
            }
            
            if (strlen($newPassword) < 6) {
                $errors[] = '新密码长度至少6位';
            }
        }
        
        if (empty($errors)) {
            $updateData = ['email' => $email, 'updated_at' => date('Y-m-d H:i:s')];
            
            if (!empty($newPassword)) {
                $updateData['password'] = hashPassword($newPassword);
            }
            
            if ($db->update('users', $updateData, 'id = :id', ['id' => $userId])) {
                $success = '个人资料更新成功';
            } else {
                $errors[] = '更新失败，请重试';
            }
        }
    }
}

// 获取用户信息
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

// 获取用户统计信息
$userStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_urls,
        COALESCE(SUM(click_count), 0) as total_clicks,
        MAX(created_at) as last_created
    FROM short_urls 
    WHERE user_id = ? AND status = 1
", [$userId]);

// 获取最近30天的活动统计
$recentStats = $db->fetchAll("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as urls_created
    FROM short_urls 
    WHERE user_id = ? AND status = 1 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
", [$userId]);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人资料 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .activity-chart {
            height: 300px;
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
                        <li><a class="dropdown-item" href="profile.php">个人资料</a></li>
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

    <!-- 个人资料头部 -->
    <section class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="me-4">
                            <i class="bi bi-person-circle display-1"></i>
                        </div>
                        <div>
                            <h2 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h2>
                            <p class="mb-1 opacity-75"><?php echo htmlspecialchars($user['email']); ?></p>
                            <small class="opacity-75">
                                注册时间：<?php echo date('Y年m月d日', strtotime($user['created_at'])); ?>
                                <?php if ($user['is_admin']): ?>
                                    <span class="badge bg-warning ms-2">管理员</span>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="bi bi-pencil"></i> 编辑资料
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <!-- 显示消息 -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo $error; ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- 统计概览 -->
        <div class="row mb-5">
            <div class="col-12">
                <h3 class="mb-4"><i class="bi bi-graph-up"></i> 数据概览</h3>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-link-45deg display-4 text-primary mb-3"></i>
                        <h3 class="text-primary"><?php echo $userStats['total_urls'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">创建的短链接</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-eye display-4 text-success mb-3"></i>
                        <h3 class="text-success"><?php echo number_format($userStats['total_clicks'] ?? 0); ?></h3>
                        <p class="text-muted mb-0">总点击量</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar display-4 text-info mb-3"></i>
                        <h3 class="text-info">
                            <?php 
                            if ($userStats['last_created']) {
                                echo date('m-d', strtotime($userStats['last_created']));
                            } else {
                                echo '--';
                            }
                            ?>
                        </h3>
                        <p class="text-muted mb-0">最后创建</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 活动图表 -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> 最近30天活动</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="activityChart" class="activity-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 快捷操作 -->
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="mb-4"><i class="bi bi-lightning"></i> 快捷操作</h3>
            </div>
            <div class="col-md-3">
                <a href="dashboard.php" class="card text-decoration-none">
                    <div class="card-body text-center">
                        <i class="bi bi-plus-circle display-4 text-primary mb-3"></i>
                        <h6>创建短链接</h6>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="urls.php" class="card text-decoration-none">
                    <div class="card-body text-center">
                        <i class="bi bi-list display-4 text-success mb-3"></i>
                        <h6>管理我的链接</h6>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="export.php" class="card text-decoration-none">
                    <div class="card-body text-center">
                        <i class="bi bi-download display-4 text-info mb-3"></i>
                        <h6>导出数据</h6>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="/" class="card text-decoration-none">
                    <div class="card-body text-center">
                        <i class="bi bi-house display-4 text-warning mb-3"></i>
                        <h6>返回首页</h6>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- 编辑资料模态框 -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> 编辑个人资料</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">用户名</label>
                            <input type="text" class="form-control" id="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <div class="form-text">用户名不可修改</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">邮箱地址</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <hr>
                        <h6>修改密码（可选）</h6>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">当前密码</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">新密码</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                            <div class="form-text">至少6位字符，留空表示不修改密码</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">保存修改</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 活动图表
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('activityChart').getContext('2d');
            
            // 准备图表数据
            const chartData = <?php echo json_encode($recentStats); ?>;
            const labels = [];
            const data = [];
            
            // 生成最近30天的日期
            for (let i = 29; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                const dateStr = date.toISOString().split('T')[0];
                labels.push(date.toLocaleDateString('zh-CN', { month: 'short', day: 'numeric' }));
                
                // 查找该日期的数据
                const dayData = chartData.find(item => item.date === dateStr);
                data.push(dayData ? parseInt(dayData.urls_created) : 0);
            }
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: '创建的短链接',
                        data: data,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
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
        });
    </script>
</body>
</html>
<?php
require_once 'config.php';

$shortCode = $_GET['code'] ?? '';
if (empty($shortCode)) {
    header('Location: /');
    exit;
}

$db = Database::getInstance();

// 获取短链接信息
$shortUrl = $db->fetchOne("SELECT * FROM short_urls WHERE short_code = ?", [$shortCode]);
if (!$shortUrl) {
    header('Location: /404.html');
    exit;
}

// 检查访问权限（只有创建者或管理员可以查看详细统计）
$canViewDetails = false;
if (isset($_SESSION['user_id'])) {
    $canViewDetails = $_SESSION['user_id'] == $shortUrl['user_id'] || $_SESSION['is_admin'];
}

// 获取基础统计
$totalClicks = $db->fetchOne("SELECT COUNT(*) as count FROM click_stats WHERE short_url_id = ?", [$shortUrl['id']]);
$uniqueVisitors = $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM click_stats WHERE short_url_id = ?", [$shortUrl['id']]);

// 获取最近30天的点击统计
$dailyStats = $db->fetchAll("
    SELECT 
        DATE(clicked_at) as date,
        COUNT(*) as clicks,
        COUNT(DISTINCT ip_address) as unique_visitors
    FROM click_stats 
    WHERE short_url_id = ? 
    AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(clicked_at) 
    ORDER BY date ASC
", [$shortUrl['id']]);

if ($canViewDetails) {
    // 获取详细统计信息
    
    // 来源统计
    $refererStats = $db->fetchAll("
        SELECT 
            CASE 
                WHEN referer IS NULL OR referer = '' THEN '直接访问'
                WHEN referer LIKE '%google%' THEN 'Google'
                WHEN referer LIKE '%baidu%' THEN '百度'
                WHEN referer LIKE '%bing%' THEN 'Bing'
                WHEN referer LIKE '%facebook%' THEN 'Facebook'
                WHEN referer LIKE '%twitter%' THEN 'Twitter'
                WHEN referer LIKE '%weibo%' THEN '微博'
                WHEN referer LIKE '%qq%' THEN 'QQ'
                WHEN referer LIKE '%wechat%' OR referer LIKE '%weixin%' THEN '微信'
                ELSE '其他网站'
            END as source,
            COUNT(*) as clicks
        FROM click_stats 
        WHERE short_url_id = ? 
        GROUP BY source
        ORDER BY clicks DESC 
        LIMIT 10
    ", [$shortUrl['id']]);

    // 设备类型统计
    $deviceStats = $db->fetchAll("
        SELECT 
            device_type,
            COUNT(*) as clicks
        FROM click_stats 
        WHERE short_url_id = ? 
        GROUP BY device_type 
        ORDER BY clicks DESC
    ", [$shortUrl['id']]);

    // 浏览器统计
    $browserStats = $db->fetchAll("
        SELECT 
            browser,
            COUNT(*) as clicks
        FROM click_stats 
        WHERE short_url_id = ? 
        GROUP BY browser 
        ORDER BY clicks DESC
        LIMIT 8
    ", [$shortUrl['id']]);

    // 操作系统统计
    $osStats = $db->fetchAll("
        SELECT 
            os,
            COUNT(*) as clicks
        FROM click_stats 
        WHERE short_url_id = ? 
        GROUP BY os 
        ORDER BY clicks DESC
        LIMIT 8
    ", [$shortUrl['id']]);

    // 时段统计
    $hourlyStats = $db->fetchAll("
        SELECT 
            HOUR(clicked_at) as hour,
            COUNT(*) as clicks
        FROM click_stats 
        WHERE short_url_id = ? 
        GROUP BY hour 
        ORDER BY hour
    ", [$shortUrl['id']]);

    // 地区统计（根据IP前缀简单判断）
    $locationStats = $db->fetchAll("
        SELECT 
            CASE 
                WHEN ip_address LIKE '127.%' THEN '本地'
                WHEN ip_address LIKE '192.168.%' THEN '内网'
                WHEN ip_address LIKE '10.%' THEN '内网'
                WHEN ip_address LIKE '172.%' THEN '内网'
                ELSE '外网'
            END as location,
            COUNT(*) as clicks
        FROM click_stats 
        WHERE short_url_id = ? 
        GROUP BY location
        ORDER BY clicks DESC
    ", [$shortUrl['id']]);

    // 最近点击记录
    $recentClicks = $db->fetchAll("
        SELECT 
            ip_address,
            user_agent,
            referer,
            device_type,
            browser,
            os,
            clicked_at
        FROM click_stats 
        WHERE short_url_id = ? 
        ORDER BY clicked_at DESC 
        LIMIT 20
    ", [$shortUrl['id']]);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>访问统计 - <?php echo htmlspecialchars($shortUrl['title'] ?: $shortUrl['short_code']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-header {
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
        .chart-container {
            position: relative;
            height: 300px;
        }
        .mini-chart {
            height: 200px;
        }
        .access-record {
            font-size: 0.85rem;
        }
        .progress-label {
            font-size: 0.9rem;
            font-weight: 500;
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
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php">控制台</a></li>
                            <li><a class="dropdown-item" href="urls.php">我的链接</a></li>
                            <li><a class="dropdown-item" href="profile.php">个人资料</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">退出登录</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">登录</a>
                    </li>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- 统计头部 -->
    <section class="stats-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="bi bi-bar-chart"></i> 
                        <?php echo htmlspecialchars($shortUrl['title'] ?: '链接统计'); ?>
                    </h2>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1 opacity-75">
                                <strong>短链接：</strong> 
                                <span class="badge bg-light text-dark"><?php echo SITE_URL . '/' . $shortUrl['short_code']; ?></span>
                            </p>
                            <p class="mb-0 opacity-75">
                                <strong>目标URL：</strong> 
                                <span class="text-truncate d-inline-block" style="max-width: 300px;">
                                    <?php echo htmlspecialchars($shortUrl['original_url']); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <small class="opacity-75">
                                创建时间：<?php echo date('Y年m月d日 H:i', strtotime($shortUrl['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="btn-group">
                        <a href="<?php echo SITE_URL . '/' . $shortUrl['short_code']; ?>" 
                           class="btn btn-light" target="_blank">
                            <i class="bi bi-box-arrow-up-right"></i> 访问链接
                        </a>
                        <a href="qr.php?code=<?php echo $shortUrl['short_code']; ?>" 
                           class="btn btn-outline-light" target="_blank">
                            <i class="bi bi-qr-code"></i> 二维码
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <!-- 基础统计 -->
        <div class="row mb-5">
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-eye display-4 text-primary mb-3"></i>
                        <h3 class="text-primary"><?php echo number_format($totalClicks['count']); ?></h3>
                        <p class="text-muted mb-0">总访问量</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-people display-4 text-success mb-3"></i>
                        <h3 class="text-success"><?php echo number_format($uniqueVisitors['count']); ?></h3>
                        <p class="text-muted mb-0">独立访客</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-graph-up display-4 text-info mb-3"></i>
                        <h3 class="text-info">
                            <?php 
                            $avgClicks = $uniqueVisitors['count'] > 0 ? 
                                round($totalClicks['count'] / $uniqueVisitors['count'], 1) : 0;
                            echo $avgClicks;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">平均点击</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 访问趋势图表 -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> 访问趋势（最近30天）</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($canViewDetails): ?>
            <!-- 详细统计 -->
            <div class="row mb-5">
                <!-- 来源统计 -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-link"></i> 访问来源</h6>
                        </div>
                        <div class="card-body">
                            <div class="mini-chart">
                                <canvas id="refererChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 设备类型 -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-device-hdd"></i> 设备类型</h6>
                        </div>
                        <div class="card-body">
                            <div class="mini-chart">
                                <canvas id="deviceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 浏览器统计 -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-browser-chrome"></i> 浏览器分布</h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($browserStats as $browser): ?>
                                <?php $percentage = $totalClicks['count'] > 0 ? ($browser['clicks'] / $totalClicks['count']) * 100 : 0; ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between progress-label">
                                        <span><?php echo htmlspecialchars($browser['browser']); ?></span>
                                        <span><?php echo $browser['clicks']; ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 操作系统统计 -->
                <div class="col-md-6 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-laptop"></i> 操作系统</h6>
                        </div>
                        <div class="card-body">
                            <?php foreach ($osStats as $os): ?>
                                <?php $percentage = $totalClicks['count'] > 0 ? ($os['clicks'] / $totalClicks['count']) * 100 : 0; ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between progress-label">
                                        <span><?php echo htmlspecialchars($os['os']); ?></span>
                                        <span><?php echo $os['clicks']; ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 时段分析 -->
            <div class="row mb-5">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-clock"></i> 访问时段分析</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="hourlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 最近访问记录 -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-list"></i> 最近访问记录</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0 access-record">
                                    <thead class="table-light">
                                        <tr>
                                            <th>时间</th>
                                            <th>IP地址</th>
                                            <th>设备</th>
                                            <th>浏览器</th>
                                            <th>系统</th>
                                            <th>来源</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentClicks)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4 text-muted">暂无访问记录</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentClicks as $click): ?>
                                                <tr>
                                                    <td><?php echo date('m-d H:i', strtotime($click['clicked_at'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-light text-dark">
                                                            <?php echo htmlspecialchars($click['ip_address']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <i class="bi bi-<?php 
                                                            echo $click['device_type'] == 'mobile' ? 'phone' : 
                                                                ($click['device_type'] == 'tablet' ? 'tablet' : 'laptop'); 
                                                        ?>"></i>
                                                        <?php echo ucfirst($click['device_type']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($click['browser']); ?></td>
                                                    <td><?php echo htmlspecialchars($click['os']); ?></td>
                                                    <td>
                                                        <span class="text-truncate d-inline-block" style="max-width: 150px;">
                                                            <?php echo htmlspecialchars($click['referer'] ?: '直接访问'); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- 权限提示 -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-lock display-4 text-muted mb-3"></i>
                            <h5>详细统计信息需要登录</h5>
                            <p class="text-muted">只有链接创建者可以查看详细的访问统计信息</p>
                            <a href="login.php" class="btn btn-primary">立即登录</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 访问趋势图表
            const trendsCtx = document.getElementById('trendsChart').getContext('2d');
            const dailyData = <?php echo json_encode($dailyStats); ?>;
            
            // 生成最近30天的日期和数据
            const trendLabels = [];
            const trendClicks = [];
            const trendUnique = [];
            
            for (let i = 29; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                const dateStr = date.toISOString().split('T')[0];
                trendLabels.push(date.toLocaleDateString('zh-CN', { month: 'short', day: 'numeric' }));
                
                const dayData = dailyData.find(item => item.date === dateStr);
                trendClicks.push(dayData ? parseInt(dayData.clicks) : 0);
                trendUnique.push(dayData ? parseInt(dayData.unique_visitors) : 0);
            }
            
            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: '总点击',
                        data: trendClicks,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: '独立访客',
                        data: trendUnique,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
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

            <?php if ($canViewDetails): ?>
            // 来源饼图
            const refererCtx = document.getElementById('refererChart').getContext('2d');
            const refererData = <?php echo json_encode($refererStats); ?>;
            
            new Chart(refererCtx, {
                type: 'doughnut',
                data: {
                    labels: refererData.map(item => item.source),
                    datasets: [{
                        data: refererData.map(item => item.clicks),
                        backgroundColor: [
                            '#667eea', '#28a745', '#ffc107', '#dc3545', 
                            '#17a2b8', '#6f42c1', '#fd7e14', '#20c997'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // 设备类型饼图
            const deviceCtx = document.getElementById('deviceChart').getContext('2d');
            const deviceData = <?php echo json_encode($deviceStats); ?>;
            
            new Chart(deviceCtx, {
                type: 'doughnut',
                data: {
                    labels: deviceData.map(item => {
                        const deviceNames = {
                            'desktop': '桌面端',
                            'mobile': '手机端',
                            'tablet': '平板端'
                        };
                        return deviceNames[item.device_type] || item.device_type;
                    }),
                    datasets: [{
                        data: deviceData.map(item => item.clicks),
                        backgroundColor: ['#667eea', '#28a745', '#ffc107']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // 时段分析柱状图
            const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
            const hourlyData = <?php echo json_encode($hourlyStats); ?>;
            
            // 生成24小时数据
            const hourLabels = [];
            const hourClicks = [];
            
            for (let i = 0; i < 24; i++) {
                hourLabels.push(i + ':00');
                const hourData = hourlyData.find(item => parseInt(item.hour) === i);
                hourClicks.push(hourData ? parseInt(hourData.clicks) : 0);
            }
            
            new Chart(hourlyCtx, {
                type: 'bar',
                data: {
                    labels: hourLabels,
                    datasets: [{
                        label: '点击量',
                        data: hourClicks,
                        backgroundColor: 'rgba(102, 126, 234, 0.7)',
                        borderColor: '#667eea',
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
                                stepSize: 1
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
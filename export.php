<?php
require_once 'config.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// 处理导出请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $format = $_POST['format'] ?? '';
    $type = $_POST['type'] ?? '';
    $dateRange = $_POST['date_range'] ?? '';
    
    // 构建时间条件
    $dateCondition = '';
    $dateParams = [];
    
    switch ($dateRange) {
        case '7_days':
            $dateCondition = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case '30_days':
            $dateCondition = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
        case '90_days':
            $dateCondition = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
            break;
        case '1_year':
            $dateCondition = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)';
            break;
    }
    
    if ($type === 'urls') {
        // 导出URL数据
        $data = $db->fetchAll("
            SELECT 
                id,
                title,
                original_url,
                short_code,
                CONCAT(?, '/', short_code) as short_url,
                click_count,
                CASE WHEN status = 1 THEN '启用' ELSE '禁用' END as status_text,
                created_at,
                updated_at
            FROM short_urls 
            WHERE user_id = ? {$dateCondition}
            ORDER BY created_at DESC
        ", array_merge([SITE_URL], [$userId], $dateParams));
        
        $filename = 'my_urls_' . date('Y-m-d');
        $headers = ['ID', '标题', '原始URL', '短码', '短链接', '点击量', '状态', '创建时间', '更新时间'];
        
    } elseif ($type === 'clicks') {
        // 导出点击数据
        $data = $db->fetchAll("
            SELECT 
                cs.id,
                su.title,
                su.short_code,
                CONCAT(?, '/', su.short_code) as short_url,
                cs.ip_address,
                cs.user_agent,
                cs.referer,
                cs.device_type,
                cs.browser,
                cs.os,
                cs.clicked_at
            FROM click_stats cs
            JOIN short_urls su ON cs.short_url_id = su.id
            WHERE su.user_id = ? {$dateCondition}
            ORDER BY cs.clicked_at DESC
        ", array_merge([SITE_URL], [$userId], $dateParams));
        
        $filename = 'click_stats_' . date('Y-m-d');
        $headers = ['ID', '链接标题', '短码', '短链接', 'IP地址', '用户代理', '来源', '设备类型', '浏览器', '操作系统', '点击时间'];
        
    } else {
        $error = '请选择导出类型';
    }
    
    if (isset($data) && !empty($data)) {
        if ($format === 'csv') {
            exportCSV($data, $headers, $filename);
        } elseif ($format === 'json') {
            exportJSON($data, $filename);
        } elseif ($format === 'excel') {
            exportExcel($data, $headers, $filename);
        }
    } elseif (!isset($error)) {
        $error = '没有找到要导出的数据';
    }
}

// CSV导出函数
function exportCSV($data, $headers, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // 添加BOM以支持中文
    fwrite($output, "\xEF\xBB\xBF");
    
    // 写入标题行
    fputcsv($output, $headers);
    
    // 写入数据行
    foreach ($data as $row) {
        fputcsv($output, array_values($row));
    }
    
    fclose($output);
    exit;
}

// JSON导出函数
function exportJSON($data, $filename) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    header('Cache-Control: max-age=0');
    
    echo json_encode([
        'export_time' => date('Y-m-d H:i:s'),
        'total_records' => count($data),
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Excel导出函数（简化版，使用HTML表格格式）
function exportExcel($data, $headers, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo "\xEF\xBB\xBF"; // BOM
    echo '<table border="1">';
    echo '<tr>';
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';
    
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

// 获取用户统计信息
$userStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_urls,
        COALESCE(SUM(click_count), 0) as total_clicks,
        (SELECT COUNT(*) FROM click_stats cs 
         JOIN short_urls su ON cs.short_url_id = su.id 
         WHERE su.user_id = ?) as total_click_records
    FROM short_urls 
    WHERE user_id = ?
", [$userId, $userId]);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据导出 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .export-header {
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
        .export-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .export-option:hover {
            border-color: #667eea;
            background-color: #f8f9ff;
        }
        .export-option.active {
            border-color: #667eea;
            background-color: #f8f9ff;
        }
        .format-card {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .format-card:hover, .format-card.active {
            border-color: #667eea;
            background-color: #f8f9ff;
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
                        <li><a class="dropdown-item" href="urls.php">我的链接</a></li>
                        <li><a class="dropdown-item" href="profile.php">个人资料</a></li>
                        <li><a class="dropdown-item" href="export.php">数据导出</a></li>
                        <?php if ($_SESSION['is_admin']): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="admin.php">管理后台</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">退出登录</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- 导出头部 -->
    <section class="export-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2"><i class="bi bi-download"></i> 数据导出</h2>
                    <p class="mb-0 opacity-75">导出您的链接数据和访问统计，支持多种格式</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="bi bi-arrow-left"></i> 返回控制台
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="container my-5">
        <!-- 显示消息 -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- 数据概览 -->
        <div class="row mb-5">
            <div class="col-12">
                <h3 class="mb-4"><i class="bi bi-graph-up"></i> 数据概览</h3>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-link-45deg display-4 text-primary mb-3"></i>
                        <h3 class="text-primary"><?php echo number_format($userStats['total_urls']); ?></h3>
                        <p class="text-muted mb-0">创建的链接</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-eye display-4 text-success mb-3"></i>
                        <h3 class="text-success"><?php echo number_format($userStats['total_clicks']); ?></h3>
                        <p class="text-muted mb-0">总点击量</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="bi bi-list-ul display-4 text-info mb-3"></i>
                        <h3 class="text-info"><?php echo number_format($userStats['total_click_records']); ?></h3>
                        <p class="text-muted mb-0">访问记录</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 导出表单 -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-gear"></i> 导出设置</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="exportForm">
                            <!-- 导出类型 -->
                            <div class="mb-4">
                                <h6 class="mb-3">选择导出数据类型</h6>
                                <div class="export-option" data-type="urls">
                                    <div class="d-flex align-items-center">
                                        <input type="radio" name="type" value="urls" id="type_urls" class="form-check-input me-3">
                                        <div>
                                            <h6 class="mb-1">链接数据</h6>
                                            <small class="text-muted">
                                                包含您创建的所有短链接信息：标题、原始URL、短码、点击量、状态等
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="export-option" data-type="clicks">
                                    <div class="d-flex align-items-center">
                                        <input type="radio" name="type" value="clicks" id="type_clicks" class="form-check-input me-3">
                                        <div>
                                            <h6 class="mb-1">访问统计</h6>
                                            <small class="text-muted">
                                                包含详细的访问记录：IP地址、设备信息、浏览器、来源、访问时间等
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 时间范围 -->
                            <div class="mb-4">
                                <h6 class="mb-3">选择时间范围</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <select class="form-select" name="date_range">
                                            <option value="">全部时间</option>
                                            <option value="7_days">最近7天</option>
                                            <option value="30_days">最近30天</option>
                                            <option value="90_days">最近90天</option>
                                            <option value="1_year">最近1年</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- 导出格式 -->
                            <div class="mb-4">
                                <h6 class="mb-3">选择导出格式</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="format-card" data-format="csv">
                                            <input type="radio" name="format" value="csv" id="format_csv" class="form-check-input d-none">
                                            <i class="bi bi-filetype-csv display-6 text-success mb-2"></i>
                                            <h6>CSV</h6>
                                            <small class="text-muted">表格文件，可用Excel打开</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="format-card" data-format="excel">
                                            <input type="radio" name="format" value="excel" id="format_excel" class="form-check-input d-none">
                                            <i class="bi bi-file-earmark-excel display-6 text-success mb-2"></i>
                                            <h6>Excel</h6>
                                            <small class="text-muted">Excel格式，保持格式</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="format-card" data-format="json">
                                            <input type="radio" name="format" value="json" id="format_json" class="form-check-input d-none">
                                            <i class="bi bi-filetype-json display-6 text-info mb-2"></i>
                                            <h6>JSON</h6>
                                            <small class="text-muted">程序数据格式</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 导出按钮 -->
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg" id="exportBtn" disabled>
                                    <i class="bi bi-download"></i> 开始导出
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 使用说明 -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-info-circle"></i> 使用说明</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>格式说明</h6>
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-check-circle text-success"></i> <strong>CSV：</strong>通用表格格式，Excel可直接打开</li>
                                    <li><i class="bi bi-check-circle text-success"></i> <strong>Excel：</strong>Microsoft Excel专用格式</li>
                                    <li><i class="bi bi-check-circle text-success"></i> <strong>JSON：</strong>程序数据交换格式</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>注意事项</h6>
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-exclamation-triangle text-warning"></i> 大量数据可能需要较长导出时间</li>
                                    <li><i class="bi bi-shield-check text-info"></i> 导出的数据仅包含您创建的内容</li>
                                    <li><i class="bi bi-download text-primary"></i> 导出文件会自动下载到您的设备</li>
                                </ul>
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
            const exportOptions = document.querySelectorAll('.export-option');
            const formatCards = document.querySelectorAll('.format-card');
            const exportBtn = document.getElementById('exportBtn');
            const form = document.getElementById('exportForm');

            // 导出类型选择
            exportOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const type = this.dataset.type;
                    const radio = this.querySelector('input[type="radio"]');
                    
                    // 清除其他选择
                    exportOptions.forEach(opt => opt.classList.remove('active'));
                    // 激活当前选择
                    this.classList.add('active');
                    radio.checked = true;
                    
                    checkFormValid();
                });
            });

            // 格式选择
            formatCards.forEach(card => {
                card.addEventListener('click', function() {
                    const format = this.dataset.format;
                    const radio = this.querySelector('input[type="radio"]');
                    
                    // 清除其他选择
                    formatCards.forEach(c => c.classList.remove('active'));
                    // 激活当前选择
                    this.classList.add('active');
                    radio.checked = true;
                    
                    checkFormValid();
                });
            });

            // 检查表单是否有效
            function checkFormValid() {
                const typeSelected = document.querySelector('input[name="type"]:checked');
                const formatSelected = document.querySelector('input[name="format"]:checked');
                
                if (typeSelected && formatSelected) {
                    exportBtn.disabled = false;
                } else {
                    exportBtn.disabled = true;
                }
            }

            // 表单提交处理
            form.addEventListener('submit', function(e) {
                exportBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> 正在导出...';
                exportBtn.disabled = true;
                
                // 3秒后恢复按钮状态
                setTimeout(() => {
                    exportBtn.innerHTML = '<i class="bi bi-download"></i> 开始导出';
                    checkFormValid();
                }, 3000);
            });
        });
    </script>
</body>
</html>
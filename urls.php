<?php
require_once 'config.php';

// 检查用户是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// 处理URL操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $urlId = intval($_POST['url_id'] ?? 0);
    
    // 验证URL所有权
    $url = $db->fetchOne("SELECT * FROM short_urls WHERE id = ? AND user_id = ?", [$urlId, $userId]);
    if (!$url) {
        $error = '操作失败：URL不存在或无权限';
    } else {
        switch ($action) {
            case 'toggle_status':
                $newStatus = $url['status'] == 1 ? 0 : 1;
                if ($db->update('short_urls', ['status' => $newStatus], 'id = :id', ['id' => $urlId])) {
                    $success = $newStatus == 1 ? 'URL已启用' : 'URL已禁用';
                } else {
                    $error = '状态更新失败';
                }
                break;
                
            case 'delete_url':
                if ($db->delete('short_urls', 'id = ?', [$urlId])) {
                    // 同时删除相关的点击统计
                    $db->delete('click_stats', 'short_url_id = ?', [$urlId]);
                    $success = 'URL删除成功';
                } else {
                    $error = '删除失败';
                }
                break;
                
            case 'update_title':
                $newTitle = trim($_POST['title'] ?? '');
                if ($db->update('short_urls', ['title' => $newTitle], 'id = :id', ['id' => $urlId])) {
                    $success = '标题更新成功';
                } else {
                    $error = '标题更新失败';
                }
                break;
        }
    }
}

// 获取搜索和筛选参数
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// 构建查询条件
$conditions = ['user_id = ?'];
$params = [$userId];

if (!empty($search)) {
    $conditions[] = '(title LIKE ? OR original_url LIKE ? OR short_code LIKE ?)';
    $searchParam = '%' . $search . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($status !== '') {
    $conditions[] = 'status = ?';
    $params[] = intval($status);
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

// 获取URL列表
$orderBy = in_array($sort, ['created_at', 'click_count', 'title']) ? $sort : 'created_at';
$orderDir = $order === 'ASC' ? 'ASC' : 'DESC';

$urls = $db->fetchAll("
    SELECT 
        id, original_url, short_code, title, click_count, status, created_at,
        (SELECT COUNT(*) FROM click_stats WHERE short_url_id = short_urls.id) as total_clicks
    FROM short_urls 
    {$whereClause}
    ORDER BY {$orderBy} {$orderDir}
    LIMIT {$limit} OFFSET {$offset}
", $params);

// 获取总数
$totalResult = $db->fetchOne("SELECT COUNT(*) as count FROM short_urls {$whereClause}", $params);
$total = $totalResult['count'];

// 计算分页信息
$totalPages = ceil($total / $limit);

// 获取用户统计
$userStats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_urls,
        COUNT(CASE WHEN status = 1 THEN 1 END) as active_urls,
        COUNT(CASE WHEN status = 0 THEN 1 END) as inactive_urls,
        COALESCE(SUM(click_count), 0) as total_clicks
    FROM short_urls 
    WHERE user_id = ?
", [$userId]);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的链接 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .url-item {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .url-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .url-item.disabled {
            border-left-color: #dc3545;
            opacity: 0.7;
        }
        .stats-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .copy-btn {
            cursor: pointer;
        }
        .table th {
            border-top: none;
            font-weight: 600;
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

    <div class="container my-4">
        <!-- 页面标题 -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="bi bi-list"></i> 我的链接管理</h2>
                <p class="text-muted">管理您创建的所有短链接</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> 创建新链接
                </a>
            </div>
        </div>

        <!-- 显示消息 -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- 统计概览 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h4 class="text-primary"><?php echo $userStats['total_urls']; ?></h4>
                        <small class="text-muted">总链接数</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h4 class="text-success"><?php echo $userStats['active_urls']; ?></h4>
                        <small class="text-muted">活跃链接</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h4 class="text-warning"><?php echo $userStats['inactive_urls']; ?></h4>
                        <small class="text-muted">已禁用</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h4 class="text-info"><?php echo number_format($userStats['total_clicks']); ?></h4>
                        <small class="text-muted">总点击量</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 搜索和筛选 -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" 
                               placeholder="搜索标题、URL或短码..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">全部状态</option>
                            <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>已启用</option>
                            <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>已禁用</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="sort">
                            <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>创建时间</option>
                            <option value="click_count" <?php echo $sort === 'click_count' ? 'selected' : ''; ?>>点击量</option>
                            <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>标题</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="order">
                            <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>降序</option>
                            <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>升序</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> 搜索
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- URL列表 -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">链接列表 (共 <?php echo $total; ?> 条)</h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="selectAll()">全选</button>
                    <button class="btn btn-outline-danger" onclick="batchDelete()">批量删除</button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($urls)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-4 text-muted"></i>
                        <p class="text-muted mt-3">没有找到匹配的链接</p>
                        <a href="dashboard.php" class="btn btn-primary">创建第一个链接</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="40"><input type="checkbox" id="selectAllCheckbox"></th>
                                    <th>标题/URL</th>
                                    <th width="120">短码</th>
                                    <th width="80">点击量</th>
                                    <th width="80">状态</th>
                                    <th width="120">创建时间</th>
                                    <th width="160">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($urls as $url): ?>
                                    <tr class="<?php echo $url['status'] == 0 ? 'table-secondary' : ''; ?>">
                                        <td>
                                            <input type="checkbox" class="url-checkbox" value="<?php echo $url['id']; ?>">
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <strong class="text-truncate" style="max-width: 300px;" 
                                                        title="<?php echo htmlspecialchars($url['title'] ?: '无标题'); ?>">
                                                    <?php echo htmlspecialchars($url['title'] ?: '无标题'); ?>
                                                </strong>
                                                <small class="text-muted text-truncate" style="max-width: 300px;" 
                                                       title="<?php echo htmlspecialchars($url['original_url']); ?>">
                                                    <?php echo htmlspecialchars($url['original_url']); ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control" 
                                                       value="<?php echo SITE_URL . '/' . $url['short_code']; ?>" readonly>
                                                <button class="btn btn-outline-secondary copy-btn" 
                                                        data-url="<?php echo SITE_URL . '/' . $url['short_code']; ?>">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary rounded-pill">
                                                <?php echo number_format($url['click_count']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($url['status'] == 1): ?>
                                                <span class="badge bg-success">启用</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">禁用</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('m-d H:i', strtotime($url['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="stats.php?code=<?php echo $url['short_code']; ?>" 
                                                   class="btn btn-outline-info" title="查看统计">
                                                    <i class="bi bi-bar-chart"></i>
                                                </a>
                                                <button class="btn btn-outline-primary" title="编辑标题"
                                                        onclick="editTitle(<?php echo $url['id']; ?>, '<?php echo htmlspecialchars($url['title'], ENT_QUOTES); ?>')">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="url_id" value="<?php echo $url['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-warning" 
                                                            title="<?php echo $url['status'] == 1 ? '禁用' : '启用'; ?>">
                                                        <i class="bi bi-<?php echo $url['status'] == 1 ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                </form>
                                                <button class="btn btn-outline-danger" title="删除"
                                                        onclick="deleteUrl(<?php echo $url['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    $currentUrl = $_SERVER['REQUEST_URI'];
                    $baseUrl = strtok($currentUrl, '?');
                    $queryParams = $_GET;
                    ?>
                    
                    <!-- 上一页 -->
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <?php
                            $queryParams['page'] = $page - 1;
                            $url = $baseUrl . '?' . http_build_query($queryParams);
                            ?>
                            <a class="page-link" href="<?php echo $url; ?>">上一页</a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- 页码 -->
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php
                            $queryParams['page'] = $i;
                            $url = $baseUrl . '?' . http_build_query($queryParams);
                            ?>
                            <a class="page-link" href="<?php echo $url; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <!-- 下一页 -->
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <?php
                            $queryParams['page'] = $page + 1;
                            $url = $baseUrl . '?' . http_build_query($queryParams);
                            ?>
                            <a class="page-link" href="<?php echo $url; ?>">下一页</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- 编辑标题模态框 -->
    <div class="modal fade" id="editTitleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">编辑链接标题</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editTitleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_title">
                        <input type="hidden" name="url_id" id="editUrlId">
                        
                        <div class="mb-3">
                            <label for="editTitle" class="form-label">链接标题</label>
                            <input type="text" class="form-control" id="editTitle" name="title" 
                                   placeholder="为这个链接起个名字..." maxlength="255">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 复制功能
        document.querySelectorAll('.copy-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const url = this.dataset.url;
                navigator.clipboard.writeText(url).then(() => {
                    const icon = this.querySelector('i');
                    const originalClass = icon.className;
                    icon.className = 'bi bi-check';
                    setTimeout(() => {
                        icon.className = originalClass;
                    }, 2000);
                });
            });
        });

        // 编辑标题
        function editTitle(urlId, currentTitle) {
            document.getElementById('editUrlId').value = urlId;
            document.getElementById('editTitle').value = currentTitle;
            new bootstrap.Modal(document.getElementById('editTitleModal')).show();
        }

        // 删除URL
        function deleteUrl(urlId) {
            if (confirm('确定要删除这个链接吗？此操作不可恢复！')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_url">
                    <input type="hidden" name="url_id" value="${urlId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // 全选功能
        function selectAll() {
            const masterCheckbox = document.getElementById('selectAllCheckbox');
            const checkboxes = document.querySelectorAll('.url-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => cb.checked = !allChecked);
            masterCheckbox.checked = !allChecked;
        }

        // 批量删除
        async function batchDelete() {
            const checkedBoxes = document.querySelectorAll('.url-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('请先选择要删除的链接');
                return;
            }
            
            if (confirm(`确定要删除选中的 ${checkedBoxes.length} 个链接吗？此操作不可恢复！`)) {
                const urlIds = Array.from(checkedBoxes).map(cb => parseInt(cb.value));
                
                try {
                    const response = await fetch('/api/batch-delete', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ url_ids: urlIds })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('删除失败: ' + data.error);
                    }
                } catch (error) {
                    alert('网络错误，请稍后重试');
                }
            }
        }

        // 主选择框控制
        document.getElementById('selectAllCheckbox').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.url-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    </script>
</body>
</html>
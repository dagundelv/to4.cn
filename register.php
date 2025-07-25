<?php
require_once 'config.php';

// 如果已经登录，重定向到控制台
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册 - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .register-body {
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="register-card">
                    <div class="register-header">
                        <h2 class="mb-0">
                            <i class="bi bi-person-plus"></i> 用户注册
                        </h2>
                        <p class="mb-0 mt-2">创建您的账户</p>
                    </div>
                    <div class="register-body">
                        <div id="alert-container"></div>
                        
                        <form id="registerForm">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="bi bi-person"></i> 用户名
                                </label>
                                <input type="text" class="form-control" id="username" required 
                                       pattern="[a-zA-Z0-9_]{3,20}" title="3-20个字符，只能包含字母、数字和下划线">
                                <div class="form-text">3-20个字符，只能包含字母、数字和下划线</div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="bi bi-envelope"></i> 邮箱
                                </label>
                                <input type="email" class="form-control" id="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="bi bi-lock"></i> 密码
                                </label>
                                <input type="password" class="form-control" id="password" required 
                                       minlength="6" title="密码至少6个字符">
                                <div class="form-text">密码至少6个字符</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">
                                    <i class="bi bi-lock-fill"></i> 确认密码
                                </label>
                                <input type="password" class="form-control" id="confirmPassword" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="agreeTerms" required>
                                <label class="form-check-label" for="agreeTerms">
                                    我同意 <a href="#" class="text-decoration-none">服务条款</a> 和 <a href="#" class="text-decoration-none">隐私政策</a>
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-person-check"></i> 注册
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-0">已有账户？ <a href="login.php" class="text-decoration-none">立即登录</a></p>
                            <a href="/" class="btn btn-link">返回首页</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const alertContainer = document.getElementById('alert-container');
            
            // 验证密码匹配
            if (password !== confirmPassword) {
                showAlert('两次输入的密码不一致', 'danger');
                return;
            }
            
            try {
                const response = await fetch('/api/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        username: username,
                        email: email,
                        password: password
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('注册成功！请前往登录', 'success');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    showAlert(data.error, 'danger');
                }
            } catch (error) {
                showAlert('网络错误，请稍后重试', 'danger');
            }
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
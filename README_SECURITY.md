# 安全配置改进建议

## 高优先级安全问题

### 1. 更换默认安全密钥 (紧急)
当前 `config.php` 使用默认密钥，生产环境必须更换：

```php
// 将这些默认值替换为随机生成的强密钥
define('JWT_SECRET', 'your-jwt-secret-key-change-this');  // ⚠️ 需要更换
define('PASSWORD_SALT', 'your-password-salt-change-this'); // ⚠️ 需要更换
```

推荐使用以下命令生成安全密钥：
```bash
# 生成 JWT 密钥
openssl rand -base64 32

# 生成密码盐
openssl rand -base64 24
```

### 2. 环境变量配置
将敏感信息移至环境变量，避免硬编码：

```php
// 推荐配置方式
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'url_jp_to4_cn');
define('DB_USER', $_ENV['DB_USER'] ?? 'url_jp_to4_cn');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? '');
```

## 中优先级安全改进

### 3. HTTPS 强制跳转
生产环境强制使用 HTTPS：

```apache
# 在 .htaccess 顶部添加
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 4. 增强会话安全
在 `config.php` 添加会话安全配置：

```php
// 会话安全设置
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
session_start();
```

### 5. 添加 CSRF 保护
为表单添加 CSRF 令牌验证。

### 6. 输入验证增强
- 为用户输入添加更严格的验证
- 实现 URL 白名单机制
- 添加文件上传限制

## 低优先级改进

### 7. 安全头设置
添加安全响应头：

```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
```

### 8. 日志记录
添加安全事件日志记录：
- 登录失败尝试
- 可疑的URL提交
- 黑名单命中记录

### 9. 速率限制
实现 API 调用频率限制，防止滥用。

## 部署检查清单

- [ ] 更换所有默认安全密钥
- [ ] 配置环境变量
- [ ] 启用 HTTPS
- [ ] 设置安全会话配置
- [ ] 验证 .htaccess 安全规则生效
- [ ] 检查文件权限设置
- [ ] 配置防火墙规则
- [ ] 设置数据库访问权限

## 持续安全维护

1. 定期更新 PHP 版本
2. 监控系统日志
3. 定期备份数据库
4. 定期检查安全配置
5. 监控异常访问模式
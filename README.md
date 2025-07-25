# URL 短网址系统

一个功能完整的PHP短网址系统，提供URL缩短、统计分析、用户管理等功能。

## 主要功能

- **URL缩短**: 将长网址转换为短网址
- **用户系统**: 用户注册、登录、个人资料管理
- **统计分析**: 点击量统计、设备分析、地理位置分析
- **管理后台**: 用户管理、链接管理、黑名单管理
- **QR码生成**: 自动生成二维码，支持多服务商备用
- **安全防护**: 域名/关键词黑名单、防刷机制

## 技术栈

- **语言**: PHP 7.4+
- **数据库**: MySQL/MariaDB
- **前端**: Bootstrap 5 + Chart.js
- **服务器**: Apache/Nginx

## 安装部署

1. **环境要求**
   - PHP 7.4 或更高版本
   - MySQL 5.7 或更高版本
   - Apache/Nginx 服务器

2. **数据库配置**
   ```bash
   # 导入数据库结构
   mysql -u username -p database_name < database.sql
   ```

3. **配置文件**
   - 修改 `config.php` 中的数据库连接参数
   - 设置站点URL和其他配置项

4. **权限设置**
   ```bash
   # 设置文件权限
   chmod 755 *.php
   chmod 666 logs/
   ```

## 功能模块

### 用户功能
- 用户注册和登录
- 个人资料管理
- URL创建和管理
- 统计报表查看
- 数据导出功能

### 管理功能
- 用户管理（启用/禁用/删除）
- URL管理（状态控制/删除）
- 域名黑名单
- 关键词过滤
- 系统统计报表

### API接口
- RESTful API设计
- URL缩短接口
- 统计数据接口
- 用户认证接口

## 目录结构

```
/
├── index.php          # 首页
├── login.php          # 登录页面
├── register.php       # 注册页面
├── dashboard.php      # 用户控制台
├── admin.php          # 管理后台
├── api.php            # API接口
├── config.php         # 配置文件
├── database.sql       # 数据库结构
├── redirect.php       # 重定向处理
├── qr.php            # 二维码生成
├── stats.php         # 统计页面
├── profile.php       # 用户资料
├── urls.php          # URL管理
├── export.php        # 数据导出
└── security.php      # 安全函数库
```

## 默认账户

- **管理员账户**: admin / admin123
- 首次登录请及时修改密码

## 安全特性

- SQL注入防护
- XSS攻击防护
- CSRF令牌验证
- 密码加密存储
- 访问日志记录
- 域名黑名单过滤

## 许可证

MIT License

## 贡献

欢迎提交Issue和Pull Request来改进这个项目。

## 联系方式

如有问题或建议，请通过GitHub Issues联系。
<?php
// 会话安全配置必须在session_start()之前
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // HTTP环境设为0，HTTPS环境设为1
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600); // 1小时过期
ini_set('session.name', 'SECURE_SESSIONID');

// 数据库配置
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'url_jp_to4_cn');
define('DB_USER', $_ENV['DB_USER'] ?? 'url_jp_to4_cn');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'Mz6jxEbE8AzMZxRz');
define('DB_CHARSET', 'utf8mb4');

// 站点配置
define('SITE_URL', 'http://url.jp.to4.cn');
define('SITE_NAME', '短网址系统');

// 安全配置
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? '3s7d3wveKCB3vzGsDspSMGTMnN1Y+eeLnSJ62lsA5hw=');
define('PASSWORD_SALT', $_ENV['PASSWORD_SALT'] ?? 'h4+Jxk+zjOnAl2yEf9T098onPPZOpak/');

// 短链接配置
define('SHORT_CODE_LENGTH', 6);
define('SHORT_CODE_CHARS', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');

// 数据库连接类
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("数据库连接失败: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function insert($table, $data) {
        $keys = array_keys($data);
        $placeholders = ':' . implode(', :', $keys);
        $sql = "INSERT INTO {$table} (" . implode(', ', $keys) . ") VALUES ({$placeholders})";
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($data);
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $key) {
            $setParts[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}

// 工具函数
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

function getReferer() {
    return $_SERVER['HTTP_REFERER'] ?? '';
}

function generateShortCode($length = SHORT_CODE_LENGTH) {
    $chars = SHORT_CODE_CHARS;
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// 简单的CSRF保护
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// 启动会话
session_start();
?>
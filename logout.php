<?php
require_once 'config.php';

// 销毁会话
session_destroy();

// 重定向到首页
header('Location: /');
exit;
?>
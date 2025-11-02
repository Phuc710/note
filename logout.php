<?php
require_once 'config.php';

// Hủy tất cả session
session_unset();
session_destroy();

// Xóa cookie session (nếu có)
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Chuyển hướng người dùng về trang đăng nhập
header('Location: login.php');
exit;
?>
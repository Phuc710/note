<?php
// Cấu hình timezone Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Cấu hình database
define('DB_HOST', 'localhost');
define('DB_NAME', 'note');
define('DB_USER', 'root');
define('DB_PASS', '');

// Cấu hình chung
define('SITE_URL', '/');
define('CHARSET', 'utf8mb4');

// Hiển thị lỗi cho development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
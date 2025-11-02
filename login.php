<?php
require_once 'config.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login'; // login or register

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    
    if ($action === 'login') {
        // Handle login
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = 'Vui lòng nhập đầy đủ thông tin';
        } else {
            try {
                require_once 'database.php';
                $conn = getDB();
                
                $stmt = $conn->prepare("SELECT id, username, password, full_name, role, status FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Tài khoản hoặc mật khẩu không đúng';
                }
            } catch (Exception $e) {
                $error = 'Lỗi hệ thống: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'register') {
        // Handle registration
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        
        if (empty($username) || empty($password) || empty($confirm_password) || empty($full_name)) {
            $error = 'Vui lòng nhập đầy đủ thông tin';
        } elseif (strlen($username) < 3) {
            $error = 'Tài khoản phải có ít nhất 3 ký tự';
        } elseif (strlen($password) < 6) {
            $error = 'Mật khẩu phải có ít nhất 6 ký tự';
        } elseif ($password !== $confirm_password) {
            $error = 'Xác nhận mật khẩu không khớp';
        } else {
            try {
                require_once 'database.php';
                $conn = getDB();
                
                // Check if username already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Tài khoản đã tồn tại';
                } else {
                    // Create new account
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, 'user', 'active')");
                    $stmt->execute([$username, $hashed_password, $full_name]);
                    
                    $success = 'Đăng ký thành công! Vui lòng đăng nhập.';
                    $mode = 'login'; // Switch back to login form
                }
            } catch (Exception $e) {
                $error = 'Lỗi hệ thống: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'register' ? 'Đăng ký' : 'Đăng nhập' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="shortcut icon" href="xitrum.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="src/theme.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        .radial-gradient-bg {
            background: radial-gradient(125% 125% at 50% 10%, #ffffff 40%, #0ea5e9 100%);
        }

        /* New Styles for Floating Labels */
        .input-group {
            position: relative;
        }
        .input-group input {
            width: 100%;
            padding: 1.25rem 1rem 0.5rem 1rem; /* Adjust padding for floating label */
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            outline: none;
            transition: all 0.2s ease-in-out;
        }
        .input-group label {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            color: #9ca3af;
            pointer-events: none;
            transition: all 0.2s ease-in-out;
        }
        .input-group input:focus,
        .input-group input:not(:placeholder-shown) {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
        }
        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            top: 0.75rem;
            font-size: 0.75rem;
            color: #0ea5e9;
            font-weight: 600;
        }
        
        /* Transition and animations */
        .form-slide {
            transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1), transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .slide-enter {
            opacity: 0;
            transform: translateX(20px);
        }
        .slide-enter-active {
            opacity: 1;
            transform: translateX(0);
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .rotate-gradient {
            animation: spin 4s linear infinite;
            animation-timing-function: linear;
        }
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active {
            -webkit-text-fill-color: #000 !important;
            -webkit-box-shadow: 0 0 0px 1000px #fff inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        input:-webkit-autofill::first-line {
            font-size: 16px;
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="radial-gradient-bg min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <div class="w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-xl transform hover:scale-105 transition-transform duration-200">
                    <img src="xitrum.png" alt="Logo" class="w-full h-full object-cover">
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2" id="subtitle">
                    <?= $mode === 'register' ? 'Tạo tài khoản mới' : 'Đăng nhập để tiếp tục' ?>
                </h1>
            </div>

            <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
                <div class="flex">
                    <button onclick="switchMode('login')" 
                            class="flex-1 py-4 px-6 text-center font-semibold transition-all duration-200 tab-btn"
                            id="loginTab">
                        <i class="fas fa-sign-in-alt mr-2"></i>Đăng nhập
                    </button>
                    <button onclick="switchMode('register')" 
                            class="flex-1 py-4 px-6 text-center font-semibold transition-all duration-200 tab-btn"
                            id="registerTab">
                        <i class="fas fa-user-plus mr-2"></i>Đăng ký
                    </button>
                </div>
                
                <div class="px-8 pb-8">
                    <?php if ($error): ?>
                        <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg animate-pulse">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                                <span class="text-red-700"><?= htmlspecialchars($error) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg animate-pulse">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                <span class="text-green-700"><?= htmlspecialchars($success) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6 mt-6 form-slide" id="loginForm" style="display: <?= $mode === 'login' ? 'block' : 'none' ?>">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="relative input-group">
                            <input type="text" name="username" id="login_username" required placeholder=" "
                                   class="w-full rounded-lg transition-all duration-200"
                                   value="<?= $mode === 'login' ? htmlspecialchars($_POST['username'] ?? '') : '' ?>">
                            <label for="login_username"><i class="fas fa-user mr-2"></i>Tài khoản</label>
                        </div>

                        <div class="relative input-group">
                            <input type="password" name="password" id="login_password" required placeholder=" "
                                   class="w-full rounded-lg transition-all duration-200 pr-12">
                            <label for="login_password"><i class="fas fa-lock mr-2"></i>Mật khẩu</label>
                            <button type="button" onclick="togglePassword('login_password', 'loginToggleIcon')" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition-colors">
                                <i class="fas fa-eye" id="loginToggleIcon"></i>
                            </button>
                        </div>

                        <button type="submit" class="w-full px-10 py-4 bg-gradient-to-r from-sky-500 to-blue-600 text-white rounded-xl hover:from-sky-600 hover:to-blue-700 transition-all duration-300 hover:scale-105 shadow-lg hover:shadow-xl font-semibold text-lg flex items-center justify-center">
                            <i class="fas fa-sign-in-alt mr-2"></i>Đăng nhập
                        </button>
                    </form>

                    <form method="POST" class="space-y-6 mt-6 form-slide" id="registerForm" style="display: <?= $mode === 'register' ? 'block' : 'none' ?>">
                        <input type="hidden" name="action" value="register">
                        
                        <div class="relative input-group">
                            <input type="text" name="full_name" id="register_full_name" required placeholder=" "
                                   class="w-full rounded-lg transition-all duration-200"
                                   value="<?= $mode === 'register' ? htmlspecialchars($_POST['full_name'] ?? '') : '' ?>">
                            <label for="register_full_name"><i class="fas fa-id-card mr-2"></i>Họ và tên</label>
                        </div>
                        
                        <div class="relative input-group">
                            <input type="text" name="username" id="register_username" required placeholder=" "
                                   class="w-full rounded-lg transition-all duration-200"
                                   value="<?= $mode === 'register' ? htmlspecialchars($_POST['username'] ?? '') : '' ?>">
                            <label for="register_username"><i class="fas fa-user mr-2"></i>Tài khoản</label>
                            <div class="mt-1 text-xs text-gray-500">Ít nhất 3 ký tự</div>
                        </div>

                        <div class="relative input-group">
                            <input type="password" name="password" id="register_password" required placeholder=" "
                                   class="w-full rounded-lg transition-all duration-200 pr-12">
                            <label for="register_password"><i class="fas fa-lock mr-2"></i>Mật khẩu</label>
                            <button type="button" onclick="togglePassword('register_password', 'registerToggleIcon')" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition-colors">
                                <i class="fas fa-eye" id="registerToggleIcon"></i>
                            </button>
                            <div class="mt-1 text-xs text-gray-500">Ít nhất 6 ký tự</div>
                        </div>

                        <div class="relative input-group">
                            <input type="password" name="confirm_password" id="register_confirm_password" required placeholder=" "
                                   class="w-full rounded-lg transition-all duration-200 pr-12">
                            <label for="register_confirm_password"><i class="fas fa-lock mr-2"></i>Xác nhận mật khẩu</label>
                            <button type="button" onclick="togglePassword('register_confirm_password', 'confirmToggleIcon')" 
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 transition-colors">
                                <i class="fas fa-eye" id="confirmToggleIcon"></i>
                            </button>
                        </div>

                        <button type="submit" class="w-full px-10 py-4 bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-xl hover:from-emerald-600 hover:to-teal-700 transition-all duration-300 hover:scale-105 shadow-lg hover:shadow-xl font-semibold text-lg flex items-center justify-center">
                            <i class="fas fa-user-plus mr-2"></i>Đăng ký
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentMode = '<?= $mode ?>';

        function switchMode(mode) {
            if (mode === currentMode) return;
            
            currentMode = mode;
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const loginTab = document.getElementById('loginTab');
            const registerTab = document.getElementById('registerTab');
            const subtitle = document.getElementById('subtitle');
            
            if (mode === 'login') {
                loginTab.className = 'flex-1 py-4 px-6 text-center font-semibold transition-all duration-200 tab-btn bg-gradient-to-r from-sky-50 to-blue-50 text-sky-600 border-b-3 border-sky-500';
                registerTab.className = 'flex-1 py-4 px-6 text-center font-semibold transition-all duration-200 tab-btn text-slate-500 hover:text-slate-700 hover:bg-slate-50';
                subtitle.textContent = 'Đăng nhập để tiếp tục';
                
                registerForm.style.display = 'none';
                loginForm.style.display = 'block';
                loginForm.classList.add('slide-enter');
                setTimeout(() => loginForm.classList.add('slide-enter-active'), 10);
            } else {
                registerTab.className = 'flex-1 py-4 px-6 text-center font-semibold transition-all duration-200 tab-btn bg-gradient-to-r from-emerald-50 to-teal-50 text-emerald-600 border-b-3 border-emerald-500';
                loginTab.className = 'flex-1 py-4 px-6 text-center font-semibold transition-all duration-200 tab-btn text-slate-500 hover:text-slate-700 hover:bg-slate-50';
                subtitle.textContent = 'Tạo tài khoản mới';
                
                loginForm.style.display = 'none';
                registerForm.style.display = 'block';
                registerForm.classList.add('slide-enter');
                setTimeout(() => registerForm.classList.add('slide-enter-active'), 10);
            }
            
            const url = new URL(window.location);
            url.searchParams.set('mode', mode);
            window.history.replaceState(null, '', url);
        }

        function togglePassword(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tabs
            switchMode(currentMode);
            
            // Auto focus
            if (currentMode === 'login') {
                document.getElementById('login_username').focus();
            } else {
                document.getElementById('register_full_name').focus();
            }
        });

        document.addEventListener('input', function(e) {
            if (e.target.name === 'confirm_password') {
                const password = document.getElementById('register_password').value;
                const confirmPassword = e.target.value;
                
                if (confirmPassword && password !== confirmPassword) {
                    e.target.setCustomValidity('Mật khẩu không khớp');
                    e.target.classList.add('border-red-500');
                } else {
                    e.target.setCustomValidity('');
                    e.target.classList.remove('border-red-500');
                }
            }
        });

        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                const form = e.target.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });
    </script>
</body>
</html>
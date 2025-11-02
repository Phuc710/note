<?php
require_once 'config.php';
require_once 'database.php';

// Kiểm tra đăng nhập và quyền admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$conn = getDB();
$message = '';

// Xử lý POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_user':
                $username = trim($_POST['username']);
                $password = $_POST['password'];
                $full_name = trim($_POST['full_name']);
                $role = $_POST['role'];
                
                if (empty($username) || empty($password) || empty($full_name)) {
                    throw new Exception('Vui lòng điền đầy đủ thông tin');
                }
                
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hashedPassword, $full_name, $role]);
                
                $message = "Tạo tài khoản thành công!";
                break;
                
            case 'toggle_status':
                $userId = $_POST['user_id'];
                $stmt = $conn->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?");
                $stmt->execute([$userId]);
                break;
                
            case 'delete_user':
                $userId = $_POST['user_id'];
                if ($userId == $_SESSION['user_id']) {
                    throw new Exception('Không thể xóa tài khoản của chính mình');
                }
                
                // Xóa notes của user trước
                $stmt = $conn->prepare("DELETE FROM notes WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Xóa user
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                
                $message = "Xóa tài khoản thành công!";
                break;
        }
    } catch (Exception $e) {
        $message = "Lỗi: " . $e->getMessage();
    }
}

// Lấy danh sách users
$users = $conn->query("SELECT id, username, full_name, role, status, created_at FROM users ORDER BY created_at DESC")->fetchAll();

// Thống kê
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
        (SELECT COUNT(*) FROM users WHERE status = 'inactive') as inactive_users,
        (SELECT COUNT(*) FROM notes WHERE type = 'note') as total_notes,
        (SELECT COUNT(*) FROM notes WHERE type = 'folder') as total_folders
")->fetch();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị hệ thống - Ghi chú thông minh</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="src/theme.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-secondary">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-md border-b-2 border-blue-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-5">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-sky-400 to-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-cogs text-white text-lg"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-slate-800">Quản trị hệ thống</h1>
                            <p class="text-sm text-slate-600">Chào mừng, <?= htmlspecialchars($_SESSION['full_name']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <?php if ($message): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                    <span class="text-green-700"><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <!-- Thống kê -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-6 rounded-2xl shadow-md border-2 border-emerald-100 hover:shadow-lg transition-all">
                    <div class="flex items-center">
                        <div class="w-14 h-14 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-xl flex items-center justify-center mr-4 shadow-md">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-slate-600 font-medium">Người dùng hoạt động</p>
                            <p class="text-3xl font-bold text-slate-800"><?= $stats['active_users'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-md border-2 border-red-100 hover:shadow-lg transition-all">
                    <div class="flex items-center">
                        <div class="w-14 h-14 bg-gradient-to-br from-red-400 to-rose-500 rounded-xl flex items-center justify-center mr-4 shadow-md">
                            <i class="fas fa-user-slash text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-slate-600 font-medium">Người dùng khóa</p>
                            <p class="text-3xl font-bold text-slate-800"><?= $stats['inactive_users'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-md border-2 border-blue-100 hover:shadow-lg transition-all">
                    <div class="flex items-center">
                        <div class="w-14 h-14 bg-gradient-to-br from-sky-400 to-blue-500 rounded-xl flex items-center justify-center mr-4 shadow-md">
                            <i class="fas fa-file-text text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-slate-600 font-medium">Tổng ghi chú</p>
                            <p class="text-3xl font-bold text-slate-800"><?= $stats['total_notes'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-md border-2 border-amber-100 hover:shadow-lg transition-all">
                    <div class="flex items-center">
                        <div class="w-14 h-14 bg-gradient-to-br from-amber-400 to-orange-500 rounded-xl flex items-center justify-center mr-4 shadow-md">
                            <i class="fas fa-folder text-white text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-slate-600 font-medium">Tổng thư mục</p>
                            <p class="text-3xl font-bold text-slate-800"><?= $stats['total_folders'] ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tạo tài khoản mới -->
            <div class="bg-white rounded-2xl shadow-md border-2 border-blue-100 mb-8">
                <div class="p-6 border-b-2 border-blue-100 bg-gradient-to-r from-blue-50 to-sky-50">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center">
                        <i class="fas fa-user-plus mr-3 text-sky-600"></i>Tạo tài khoản mới
                    </h2>
                </div>
                <div class="p-6">
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <input type="hidden" name="action" value="create_user">
                        <div>
                            <input type="text" name="username" required
                                   class="w-full px-4 py-2.5 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-200 focus:border-sky-500 transition-all"
                                   placeholder="Tài khoản">
                        </div>
                        <div>
                            <input type="password" name="password" required
                                   class="w-full px-4 py-2.5 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-200 focus:border-sky-500 transition-all"
                                   placeholder="Mật khẩu">
                        </div>
                        <div>
                            <input type="text" name="full_name" required
                                   class="w-full px-4 py-2.5 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-200 focus:border-sky-500 transition-all"
                                   placeholder="Họ tên">
                        </div>
                        <div>
                            <select name="role" class="w-full px-4 py-2.5 border-2 border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-200 focus:border-sky-500 transition-all">
                                <option value="user">Người dùng</option>
                                <option value="admin">Quản trị</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" 
                                    class="w-full bg-gradient-to-r from-sky-500 to-blue-600 text-white py-2.5 px-4 rounded-xl hover:from-sky-600 hover:to-blue-700 transition-all font-semibold shadow-md hover:shadow-lg">
                                <i class="fas fa-plus mr-2"></i>Tạo
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Danh sách người dùng -->
            <div class="bg-white rounded-2xl shadow-md border-2 border-emerald-100">
                <div class="p-6 border-b-2 border-emerald-100 bg-gradient-to-r from-emerald-50 to-teal-50">
                    <h2 class="text-xl font-bold text-slate-800 flex items-center">
                        <i class="fas fa-users mr-3 text-emerald-600"></i>Danh sách người dùng
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gradient-to-r from-slate-50 to-slate-100">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">Tài khoản</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">Họ tên</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">Vai trò</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">Trạng thái</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">Ngày tạo</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase tracking-wider">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100">
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-blue-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 bg-gradient-to-br from-sky-400 to-blue-500 rounded-xl flex items-center justify-center mr-3 shadow-sm">
                                                <i class="fas fa-user text-white text-sm"></i>
                                            </div>
                                            <span class="font-semibold text-slate-800"><?= htmlspecialchars($user['username']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-slate-700 font-medium">
                                        <?= htmlspecialchars($user['full_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1.5 text-xs font-bold rounded-lg <?= $user['role'] === 'admin' ? 'bg-gradient-to-r from-purple-100 to-pink-100 text-purple-700' : 'bg-gradient-to-r from-slate-100 to-slate-200 text-slate-700' ?>">
                                            <?= $user['role'] === 'admin' ? 'Quản trị' : 'Người dùng' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1.5 text-xs font-bold rounded-lg <?= $user['status'] === 'active' ? 'bg-gradient-to-r from-emerald-100 to-teal-100 text-emerald-700' : 'bg-gradient-to-r from-red-100 to-rose-100 text-red-700' ?>">
                                            <?= $user['status'] === 'active' ? 'Hoạt động' : 'Khóa' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 font-medium">
                                        <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" 
                                                        class="px-3 py-1.5 <?= $user['status'] === 'active' ? 'bg-gradient-to-r from-red-400 to-rose-500 text-white hover:from-red-500 hover:to-rose-600' : 'bg-gradient-to-r from-emerald-400 to-teal-500 text-white hover:from-emerald-500 hover:to-teal-600' ?> rounded-lg transition-all font-semibold shadow-sm hover:shadow-md">
                                                    <i class="fas <?= $user['status'] === 'active' ? 'fa-lock' : 'fa-unlock' ?> mr-1"></i>
                                                    <?= $user['status'] === 'active' ? 'Khóa' : 'Mở' ?>
                                                </button>
                                            </form>
                                            <form method="POST" class="inline" onsubmit="return confirm('Bạn có chắc muốn xóa tài khoản này?')">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" 
                                                        class="px-3 py-1.5 bg-gradient-to-r from-red-500 to-rose-600 text-white hover:from-red-600 hover:to-rose-700 rounded-lg transition-all font-semibold shadow-sm hover:shadow-md">
                                                    <i class="fas fa-trash mr-1"></i>Xóa
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-sm font-medium">Tài khoản hiện tại</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
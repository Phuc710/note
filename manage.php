<?php
require_once 'config.php';
require_once 'database.php';

// Kiểm tra đăng nhập và quyền admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$conn = getDB();
$currentTab = $_GET['tab'] ?? 'users';

// Xử lý xóa short link
if (isset($_GET['delete_link'])) {
    $linkId = $_GET['delete_link'];
    $stmt = $conn->prepare("DELETE FROM short_links WHERE id = ?");
    $stmt->execute([$linkId]);
    header('Location: manage.php?tab=links');
    exit;
}

// Xử lý xóa image
if (isset($_GET['delete_image'])) {
    $imageId = $_GET['delete_image'];
    $stmt = $conn->prepare("DELETE FROM uploaded_images WHERE id = ?");
    $stmt->execute([$imageId]);
    header('Location: manage.php?tab=images');
    exit;
}

// Lấy dữ liệu users (từ admin.php)
$users = $conn->query("SELECT id, username, full_name, role, status, created_at FROM users ORDER BY created_at DESC")->fetchAll();

// Lấy dữ liệu short links
$shortLinks = $conn->query("
    SELECT sl.*, u.username 
    FROM short_links sl 
    LEFT JOIN users u ON sl.user_id = u.id 
    ORDER BY sl.created_at DESC
")->fetchAll();

// Lấy dữ liệu images
$images = $conn->query("
    SELECT ui.*, u.username 
    FROM uploaded_images ui 
    LEFT JOIN users u ON ui.user_id = u.id 
    ORDER BY ui.created_at DESC
")->fetchAll();

// Thống kê
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
        (SELECT COUNT(*) FROM short_links) as total_links,
        (SELECT SUM(clicks) FROM short_links) as total_clicks,
        (SELECT COUNT(*) FROM uploaded_images) as total_images,
        (SELECT SUM(views) FROM uploaded_images) as total_views
")->fetch();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tổng hợp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="src/theme.css">
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
                            <h1 class="text-xl font-bold text-slate-800">Quản lý tổng hợp</h1>
                            <p class="text-sm text-slate-600">Users, Links & Images</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <a href="index.php" class="px-5 py-2.5 bg-gradient-to-r from-sky-500 to-blue-600 text-white rounded-xl hover:from-sky-600 hover:to-blue-700 transition-all shadow-md hover:shadow-lg font-medium">
                            <i class="fas fa-arrow-left mr-2"></i>Về trang chính
                        </a>
                        <a href="logout.php" class="px-5 py-2.5 bg-gradient-to-r from-red-500 to-rose-600 text-white rounded-xl hover:from-red-600 hover:to-rose-700 transition-all shadow-md hover:shadow-lg font-medium">
                            <i class="fas fa-sign-out-alt mr-2"></i>Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Tabs Navigation -->
            <div class="bg-white rounded-2xl shadow-md border-2 border-blue-100 mb-8 overflow-hidden">
                <div class="flex">
                    <a href="?tab=users" class="flex-1 py-4 px-6 text-center font-bold transition-all <?= $currentTab === 'users' ? 'bg-gradient-to-r from-sky-50 to-blue-50 text-sky-600 border-b-4 border-sky-500' : 'text-slate-500 hover:bg-slate-50' ?>">
                        <i class="fas fa-users mr-2"></i>
                        Người dùng (<?= count($users) ?>)
                    </a>
                    <a href="?tab=links" class="flex-1 py-4 px-6 text-center font-bold transition-all <?= $currentTab === 'links' ? 'bg-gradient-to-r from-emerald-50 to-teal-50 text-emerald-600 border-b-4 border-emerald-500' : 'text-slate-500 hover:bg-slate-50' ?>">
                        <i class="fas fa-link mr-2"></i>
                        Short Links (<?= count($shortLinks) ?>)
                    </a>
                    <a href="?tab=images" class="flex-1 py-4 px-6 text-center font-bold transition-all <?= $currentTab === 'images' ? 'bg-gradient-to-r from-purple-50 to-pink-50 text-purple-600 border-b-4 border-purple-500' : 'text-slate-500 hover:bg-slate-50' ?>">
                        <i class="fas fa-images mr-2"></i>
                        Images (<?= count($images) ?>)
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                <div class="bg-white p-4 rounded-xl shadow-md border-2 border-blue-100">
                    <div class="text-center">
                        <i class="fas fa-users text-3xl text-sky-500 mb-2"></i>
                        <p class="text-sm text-slate-600">Users</p>
                        <p class="text-2xl font-bold text-slate-800"><?= $stats['active_users'] ?></p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-md border-2 border-emerald-100">
                    <div class="text-center">
                        <i class="fas fa-link text-3xl text-emerald-500 mb-2"></i>
                        <p class="text-sm text-slate-600">Links</p>
                        <p class="text-2xl font-bold text-slate-800"><?= $stats['total_links'] ?></p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-md border-2 border-amber-100">
                    <div class="text-center">
                        <i class="fas fa-mouse-pointer text-3xl text-amber-500 mb-2"></i>
                        <p class="text-sm text-slate-600">Clicks</p>
                        <p class="text-2xl font-bold text-slate-800"><?= $stats['total_clicks'] ?? 0 ?></p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-md border-2 border-purple-100">
                    <div class="text-center">
                        <i class="fas fa-images text-3xl text-purple-500 mb-2"></i>
                        <p class="text-sm text-slate-600">Images</p>
                        <p class="text-2xl font-bold text-slate-800"><?= $stats['total_images'] ?></p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-md border-2 border-pink-100">
                    <div class="text-center">
                        <i class="fas fa-eye text-3xl text-pink-500 mb-2"></i>
                        <p class="text-sm text-slate-600">Views</p>
                        <p class="text-2xl font-bold text-slate-800"><?= $stats['total_views'] ?? 0 ?></p>
                    </div>
                </div>
            </div>

            <!-- Content Based on Tab -->
            <?php if ($currentTab === 'users'): ?>
                <!-- Include User Management from admin.php -->
                <iframe src="admin.php" class="w-full h-screen border-0 rounded-2xl shadow-lg"></iframe>
                
            <?php elseif ($currentTab === 'links'): ?>
                <!-- Short Links Management -->
                <div class="bg-white rounded-2xl shadow-md border-2 border-emerald-100">
                    <div class="p-6 border-b-2 border-emerald-100 bg-gradient-to-r from-emerald-50 to-teal-50">
                        <h2 class="text-xl font-bold text-slate-800">
                            <i class="fas fa-link mr-3 text-emerald-600"></i>
                            Quản lý Short Links
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-slate-50 to-slate-100">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">ID</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">URL gốc</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">User</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Clicks</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Ngày tạo</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($shortLinks as $link): ?>
                                <tr class="hover:bg-emerald-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <code class="text-sm font-mono text-blue-600"><?= $link['id'] ?></code>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="<?= htmlspecialchars($link['original_url']) ?>" target="_blank" class="text-sm text-slate-700 hover:text-blue-600 truncate block max-w-xs">
                                            <?= htmlspecialchars($link['original_url']) ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700">
                                        <?= htmlspecialchars($link['username'] ?? 'Guest') ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 bg-amber-100 text-amber-700 rounded-lg text-sm font-bold">
                                            <?= $link['clicks'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        <?= date('d/m/Y H:i', strtotime($link['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="?tab=links&delete_link=<?= $link['id'] ?>" onclick="return confirm('Xóa link này?')" class="px-3 py-1.5 bg-gradient-to-r from-red-500 to-rose-600 text-white rounded-lg hover:from-red-600 hover:to-rose-700 transition-all font-semibold text-sm">
                                            <i class="fas fa-trash mr-1"></i>Xóa
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Images Management -->
                <div class="bg-white rounded-2xl shadow-md border-2 border-purple-100">
                    <div class="p-6 border-b-2 border-purple-100 bg-gradient-to-r from-purple-50 to-pink-50">
                        <h2 class="text-xl font-bold text-slate-800">
                            <i class="fas fa-images mr-3 text-purple-600"></i>
                            Quản lý Images
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gradient-to-r from-slate-50 to-slate-100">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Preview</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Tên file</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">User</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Size</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Views</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Ngày tạo</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($images as $image): ?>
                                <tr class="hover:bg-purple-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <img src="<?= htmlspecialchars($image['filename']) ?>" alt="" class="w-16 h-16 object-cover rounded-lg shadow-sm">
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700 font-medium">
                                        <?= htmlspecialchars($image['original_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700">
                                        <?= htmlspecialchars($image['username'] ?? 'Guest') ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        <?= round($image['file_size'] / 1024, 2) ?> KB
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 bg-pink-100 text-pink-700 rounded-lg text-sm font-bold">
                                            <?= $image['views'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        <?= date('d/m/Y H:i', strtotime($image['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="?tab=images&delete_image=<?= $image['id'] ?>" onclick="return confirm('Xóa ảnh này?')" class="px-3 py-1.5 bg-gradient-to-r from-red-500 to-rose-600 text-white rounded-lg hover:from-red-600 hover:to-rose-700 transition-all font-semibold text-sm">
                                            <i class="fas fa-trash mr-1"></i>Xóa
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

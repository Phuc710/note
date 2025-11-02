<?php
require_once 'config.php';
require_once 'database.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDB();
$currentTab = $_GET['tab'] ?? 'links';

// Xử lý xóa short link
if (isset($_GET['delete_link'])) {
    $linkId = $_GET['delete_link'];
    $stmt = $conn->prepare("DELETE FROM short_links WHERE id = ? AND user_id = ?");
    $stmt->execute([$linkId, $_SESSION['user_id']]);
    header('Location: all.php?tab=links');
    exit;
}

// Xử lý xóa image
if (isset($_GET['delete_image'])) {
    $imageId = $_GET['delete_image'];
    $stmt = $conn->prepare("DELETE FROM uploaded_images WHERE id = ? AND user_id = ?");
    $stmt->execute([$imageId, $_SESSION['user_id']]);
    header('Location: all.php?tab=images');
    exit;
}

// Xử lý xóa share
if (isset($_GET['delete_share'])) {
    $shareId = $_GET['delete_share'];
    // Chỉ cho phép xóa share nếu share thuộc về note của user hiện tại
    $stmt = $conn->prepare("SELECT s.id FROM shares s JOIN notes n ON s.note_id = n.id WHERE s.id = ? AND n.user_id = ?");
    $stmt->execute([$shareId, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $del = $conn->prepare("DELETE FROM shares WHERE id = ?");
        $del->execute([$shareId]);
    }
    header('Location: all.php?tab=shares');
    exit;
}

// Lấy dữ liệu short links của user
$userLinks = [];
$stmt = $conn->prepare("SELECT * FROM short_links WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$userLinks = $stmt->fetchAll();

// Lấy dữ liệu images của user
$userImages = [];
$stmt = $conn->prepare("SELECT * FROM uploaded_images WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$userImages = $stmt->fetchAll();

// Lấy dữ liệu shares của user
$userShares = [];
$stmt = $conn->prepare("SELECT s.*, n.title FROM shares s JOIN notes n ON s.note_id = n.id WHERE n.user_id = ? ORDER BY s.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$userShares = $stmt->fetchAll();

// Thống kê
$uid = (int)$_SESSION['user_id'];
$stats = $conn->query("\n    SELECT \n        (SELECT COUNT(*) FROM short_links WHERE user_id = $uid) as total_links,\n        (SELECT SUM(clicks) FROM short_links WHERE user_id = $uid) as total_clicks,\n        (SELECT COUNT(*) FROM uploaded_images WHERE user_id = $uid) as total_images,\n        (SELECT SUM(views) FROM uploaded_images WHERE user_id = $uid) as total_views,\n        (SELECT COUNT(*) FROM shares s JOIN notes n ON s.note_id = n.id WHERE n.user_id = $uid) as total_shares,\n        (SELECT SUM(s.views) FROM shares s JOIN notes n ON s.note_id = n.id WHERE n.user_id = $uid) as total_share_views\n")->fetch();
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
    <link rel="stylesheet" href="src/index.css">
</head>
<body class="bg-slate-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-md border-b-2 border-blue-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-5">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-th-large text-white text-lg"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-slate-800">Quản lý tổng hợp</h1>
                            <p class="text-sm text-slate-600">Links, Images & Shares của bạn</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button id="themeToggle" class="px-4 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl transition-all shadow-md hover:shadow-lg font-medium">
                            <i class="fas fa-moon"></i>
                        </button>
                        <a href="index.php" class="px-5 py-2.5 bg-gradient-to-r from-sky-500 to-blue-600 text-white rounded-xl hover:from-sky-600 hover:to-blue-700 transition-all shadow-md hover:shadow-lg font-medium">
                            <i class="fas fa-arrow-left mr-2"></i>Về trang chính
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Tabs Navigation -->
            <div class="bg-white rounded-2xl shadow-md border-2 border-blue-100 mb-8 overflow-hidden">
                <div class="flex flex-col sm:flex-row">
                    <a href="?tab=links" class="flex-1 py-4 px-6 text-center font-bold transition-all <?= $currentTab === 'links' ? 'bg-gradient-to-r from-blue-50 to-sky-50 text-blue-600 border-b-4 border-blue-500' : 'text-slate-500 hover:bg-slate-50' ?>">
                        <i class="fas fa-link mr-2"></i>
                        <span class="hidden sm:inline">Short Links</span>
                        <span class="sm:hidden">Links</span>
                        <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-600 rounded-lg text-xs font-bold"><?= count($userLinks) ?></span>
                    </a>
                    <a href="?tab=images" class="flex-1 py-4 px-6 text-center font-bold transition-all <?= $currentTab === 'images' ? 'bg-gradient-to-r from-purple-50 to-pink-50 text-purple-600 border-b-4 border-purple-500' : 'text-slate-500 hover:bg-slate-50' ?>">
                        <i class="fas fa-images mr-2"></i>
                        <span class="hidden sm:inline">Images</span>
                        <span class="sm:hidden">Ảnh</span>
                        <span class="ml-2 px-2 py-1 bg-purple-100 text-purple-600 rounded-lg text-xs font-bold"><?= count($userImages) ?></span>
                    </a>
                    <a href="?tab=shares" class="flex-1 py-4 px-6 text-center font-bold transition-all <?= $currentTab === 'shares' ? 'bg-gradient-to-r from-green-50 to-emerald-50 text-green-600 border-b-4 border-green-500' : 'text-slate-500 hover:bg-slate-50' ?>">
                        <i class="fas fa-share-alt mr-2"></i>
                        <span class="hidden sm:inline">Shares</span>
                        <span class="sm:hidden">Share</span>
                        <span class="ml-2 px-2 py-1 bg-green-100 text-green-600 rounded-lg text-xs font-bold"><?= count($userShares) ?></span>
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
                <div class="bg-white p-4 rounded-xl shadow-md border-2 border-blue-100">
                    <div class="text-center">
                        <i class="fas fa-link text-3xl text-blue-500 mb-2"></i>
                        <p class="text-sm text-slate-600">Links</p>
                        <p class="text-2xl font-bold text-slate-800"><?= $stats['total_links'] ?? 0 ?></p>
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
                        <p class="text-2xl font-bold text-slate-800"><?= $stats['total_images'] ?? 0 ?></p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-md border-2 border-pink-100">
                    <div class="text-center">
                        <i class="fas fa-eye text-3xl text-pink-500 mb-2"></i>
                        <p class="text-sm text-slate-600">Img Views</p>
                        <p class="text-2xl font-bold text-slate-800"><?= $stats['total_views'] ?? 0 ?></p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-md border-2 border-green-100">
                    <div class="text-center">
                        <i class="fas fa-share-alt text-3xl text-green-500 mb-2"></i>
                        <p class="text-sm text-slate-600">Shares</p>
                        <p class="text-2xl font-bold text-slate-800"><?= $stats['total_shares'] ?? 0 ?></p>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-md border-2 border-teal-100">
                    <div class="text-center">
                        <i class="fas fa-eye text-3xl text-teal-500 mb-2"></i>
                        <p class="text-sm text-slate-600">Share Views</p>
                        <p class="text-2xl font-bold text-slate-800"><?= $stats['total_share_views'] ?? 0 ?></p>
                    </div>
                </div>
            </div>

            <!-- Content Based on Tab -->
            <?php if ($currentTab === 'links'): ?>
                <!-- Short Links Management -->
                <div class="bg-white rounded-2xl shadow-md border-2 border-blue-100">
                    <div class="p-6 border-b-2 border-blue-100 bg-gradient-to-r from-blue-50 to-sky-50">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="text-xl font-bold text-slate-800 mb-4 sm:mb-0">
                                <i class="fas fa-link mr-3 text-blue-600"></i>
                                Short Links của bạn
                            </h2>
                            <a href="shortener/" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-sky-600 text-white rounded-xl hover:from-blue-600 hover:to-sky-700 transition-all font-semibold">
                                <i class="fas fa-plus mr-2"></i>Tạo link mới
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($userLinks)): ?>
                        <div class="p-8 text-center">
                            <div class="w-20 h-20 bg-gradient-to-br from-blue-100 to-sky-100 rounded-3xl flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-link text-blue-500 text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Chưa có link nào</h3>
                            <p class="text-slate-600 mb-6">Tạo link rút gọn đầu tiên của bạn</p>
                            <a href="shortener/" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-sky-600 text-white rounded-xl hover:from-blue-600 hover:to-sky-700 transition-all font-bold shadow-md hover:shadow-lg">
                                <i class="fas fa-plus mr-2"></i>Tạo link mới
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gradient-to-r from-slate-50 to-slate-100">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">ID</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">URL gốc</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Clicks</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Ngày tạo</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($userLinks as $link): ?>
                                    <tr class="hover:bg-blue-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <code class="text-sm font-mono text-blue-600 bg-blue-50 px-2 py-1 rounded"><?= $link['id'] ?></code>
                                        </td>
                                        <td class="px-6 py-4">
                                            <a href="<?= htmlspecialchars($link['original_url']) ?>" target="_blank" class="text-sm text-slate-700 hover:text-blue-600 truncate block max-w-xs">
                                                <?= htmlspecialchars($link['original_url']) ?>
                                            </a>
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
                                            <div class="flex items-center space-x-2">
                                                <button onclick="copyToClipboard('<?= SITE_URL ?>shortener/s/<?= $link['id'] ?>')" class="px-3 py-1.5 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all font-semibold text-sm">
                                                    <i class="fas fa-copy mr-1"></i>Copy
                                                </button>
                                                <a href="?tab=links&delete_link=<?= $link['id'] ?>" onclick="return confirm('Xóa link này?')" class="px-3 py-1.5 bg-gradient-to-r from-red-500 to-rose-600 text-white rounded-lg hover:from-red-600 hover:to-rose-700 transition-all font-semibold text-sm">
                                                    <i class="fas fa-trash mr-1"></i>Xóa
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($currentTab === 'images'): ?>
                <!-- Images Management -->
                <div class="bg-white rounded-2xl shadow-md border-2 border-purple-100">
                    <div class="p-6 border-b-2 border-purple-100 bg-gradient-to-r from-purple-50 to-pink-50">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="text-xl font-bold text-slate-800 mb-4 sm:mb-0">
                                <i class="fas fa-images mr-3 text-purple-600"></i>
                                Images của bạn
                            </h2>
                            <a href="uploader/" class="px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-xl hover:from-purple-600 hover:to-pink-700 transition-all font-semibold">
                                <i class="fas fa-plus mr-2"></i>Upload ảnh mới
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($userImages)): ?>
                        <div class="p-8 text-center">
                            <div class="w-20 h-20 bg-gradient-to-br from-purple-100 to-pink-100 rounded-3xl flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-images text-purple-500 text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Chưa có ảnh nào</h3>
                            <p class="text-slate-600 mb-6">Upload ảnh đầu tiên của bạn lên Imgur</p>
                            <a href="uploader/" class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-xl hover:from-purple-600 hover:to-pink-700 transition-all font-bold shadow-md hover:shadow-lg">
                                <i class="fas fa-cloud-upload-alt mr-2"></i>Upload ảnh mới
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="p-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                <?php foreach ($userImages as $image): ?>
                                <div class="bg-white p-4 rounded-2xl border-2 border-purple-100 shadow-sm hover:shadow-md transition-all">
                                    <div class="aspect-square bg-gradient-to-br from-purple-100 to-pink-100 rounded-xl mb-4 flex items-center justify-center overflow-hidden">
                                        <img src="<?= htmlspecialchars($image['filename']) ?>" alt="<?= htmlspecialchars($image['original_name']) ?>" 
                                             class="w-full h-full object-cover">
                                    </div>
                                    <h3 class="font-bold text-slate-800 mb-2 truncate text-sm"><?= htmlspecialchars($image['original_name']) ?></h3>
                                    <p class="text-xs text-slate-600 mb-4">
                                        <?= round($image['file_size'] / 1024, 2) ?> KB • 
                                        <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded-lg text-xs font-bold">
                                            <?= $image['views'] ?> views
                                        </span>
                                    </p>
                                    <div class="space-y-2">
                                        <input type="text" value="<?= htmlspecialchars($image['filename']) ?>" readonly 
                                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono">
                                        <div class="flex space-x-2">
                                            <button onclick="copyToClipboard('<?= htmlspecialchars($image['filename']) ?>')" 
                                                    class="flex-1 px-3 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all font-semibold text-sm">
                                                <i class="fas fa-copy mr-1"></i>Copy
                                            </button>
                                            <a href="?tab=images&delete_image=<?= $image['id'] ?>" onclick="return confirm('Xóa ảnh này?')" 
                                               class="flex-1 px-3 py-2 bg-gradient-to-r from-red-500 to-rose-600 text-white rounded-lg hover:from-red-600 hover:to-rose-700 transition-all font-semibold text-sm text-center">
                                                <i class="fas fa-trash mr-1"></i>Xóa
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($currentTab === 'shares'): ?>
                <!-- Shares Management -->
                <div class="bg-white rounded-2xl shadow-md border-2 border-green-100">
                    <div class="p-6 border-b-2 border-green-100 bg-gradient-to-r from-green-50 to-emerald-50">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="text-xl font-bold text-slate-800 mb-4 sm:mb-0">
                                <i class="fas fa-share-alt mr-3 text-green-600"></i>
                                Shares của bạn
                            </h2>
                            <a href="index.php" class="px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all font-semibold">
                                <i class="fas fa-plus mr-2"></i>Tạo share mới
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($userShares)): ?>
                        <div class="p-8 text-center">
                            <div class="w-20 h-20 bg-gradient-to-br from-green-100 to-emerald-100 rounded-3xl flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-share-alt text-green-500 text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-2">Chưa có share nào</h3>
                            <p class="text-slate-600 mb-6">Chia sẻ ghi chú đầu tiên của bạn</p>
                            <a href="index.php" class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all font-bold shadow-md hover:shadow-lg">
                                <i class="fas fa-share-alt mr-2"></i>Tạo share mới
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gradient-to-r from-slate-50 to-slate-100">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">ID</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Tiêu đề</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Mô tả</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Views</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Ngày tạo</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-700 uppercase">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($userShares as $share): ?>
                                    <tr class="hover:bg-green-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <code class="text-sm font-mono text-green-600 bg-green-50 px-2 py-1 rounded"><?= $share['id'] ?></code>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-700 font-medium">
                                            <?= htmlspecialchars($share['title'] ?? 'Untitled') ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600 max-w-xs truncate">
                                            <?= htmlspecialchars($share['description'] ?? '') ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="px-3 py-1 bg-teal-100 text-teal-700 rounded-lg text-sm font-bold">
                                                <?= $share['views'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-600">
                                            <?= date('d/m/Y H:i', strtotime($share['created_at'])) ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center space-x-2">
                                                <button onclick="copyToClipboard('<?= SITE_URL ?>share.php?id=<?= $share['id'] ?>')" class="px-3 py-1.5 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all font-semibold text-sm">
                                                    <i class="fas fa-copy mr-1"></i>Copy
                                                </button>
                                                <a href="share.php?id=<?= $share['id'] ?>" target="_blank" class="px-3 py-1.5 bg-gradient-to-r from-blue-500 to-sky-600 text-white rounded-lg hover:from-blue-600 hover:to-sky-700 transition-all font-semibold text-sm">
                                                    <i class="fas fa-external-link-alt mr-1"></i>Xem
                                                </a>
                                                <a href="?tab=shares&delete_share=<?= $share['id'] ?>" onclick="return confirm('Xóa share này?')" class="px-3 py-1.5 bg-gradient-to-r from-red-500 to-rose-600 text-white rounded-lg hover:from-red-600 hover:to-rose-700 transition-all font-semibold text-sm">
                                                    <i class="fas fa-trash mr-1"></i>Xóa
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Dark mode toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        
        // Load saved theme
        if (localStorage.getItem('theme') === 'dark') {
            html.classList.add('dark-mode');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
        
        themeToggle.addEventListener('click', () => {
            html.classList.toggle('dark-mode');
            
            if (html.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                localStorage.setItem('theme', 'light');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            }
        });
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            // Tạo toast notification
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            toast.innerHTML = '<i class="fas fa-check mr-2"></i>Đã copy!';
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 2000);
        }
    </script>
</body>
</html>

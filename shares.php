<?php 
require_once 'config.php';
require_once 'database.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDB();
$userId = $_SESSION['user_id'];

// Lấy danh sách shares của user
$sql = "SELECT s.id, s.note_id, s.description, s.views, s.created_at, n.title as note_title
        FROM shares s 
        JOIN notes n ON s.note_id = n.id 
        WHERE n.user_id = ? 
        ORDER BY s.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([$userId]);
$shares = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý chia sẻ - Note</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="xitrum.png" type="image/x-icon">
    <link rel="stylesheet" href="src/theme.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        .progress-bordered {
           width: 134.4px;
           height: 24.6px;
           border-radius: 22.4px;
           color: #256176;
           border: 2.2px solid;
           position: relative;
        }
        .progress-bordered::before {
           content: "";
           position: absolute;
           margin: 2.2px;
           inset: 0 100% 0 0;
           border-radius: inherit;
           background: currentColor;
           animation: progress-pf82op 2.0s infinite;
        }
        @keyframes progress-pf82op {
           100% {
              inset: 0;
           }
        }

        .share-card {
            transition: all 0.3s ease;
            border: 2px solid #e0f2fe;
        }
        .share-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(14, 165, 233, 0.15);
            border-color: #7dd3fc;
        }

        .copy-btn {
            transition: all 0.2s ease;
        }
        .copy-btn:hover {
            transform: scale(1.05);
        }

        .empty-state {
            animation: fadeIn 0.6s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-secondary min-h-screen">
    <div class="container mx-auto px-6 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-4">
                <a href="index.php" class="p-4 bg-white rounded-2xl shadow-md hover:shadow-xl transition-all text-sky-600 hover:text-sky-700 hover:scale-105 border-2 border-sky-100">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-4xl font-bold text-slate-800">Quản lý chia sẻ</h1>
                    <p class="text-slate-600 mt-2 text-lg">Theo dõi và quản lý các ghi chú đã chia sẻ</p>
                </div>
            </div>
        </div>

        <?php if (empty($shares)): ?>
        <!-- Empty State -->
        <div class="empty-state text-center py-20">
            <div class="w-32 h-32 bg-gradient-to-br from-sky-100 to-blue-200 rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-lg">
                <i class="fas fa-share-alt text-5xl text-sky-600"></i>
            </div>
            <h3 class="text-3xl font-bold text-slate-800 mb-3">Chưa có ghi chú nào được chia sẻ</h3>
            <p class="text-slate-600 mb-8 text-lg">Bắt đầu chia sẻ ghi chú của bạn với mọi người</p>
            <a href="index.php" class="inline-flex items-center space-x-3 px-8 py-4 bg-gradient-to-r from-sky-500 to-blue-600 text-white rounded-2xl hover:from-sky-600 hover:to-blue-700 transition-all shadow-lg hover:shadow-xl font-semibold text-lg hover:scale-105">
                <i class="fas fa-plus text-lg"></i>
                <span>Tạo ghi chú mới</span>
            </a>
        </div>
        <?php else: ?>
        <!-- Shares Grid -->
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($shares as $share): ?>
            <div class="share-card bg-white rounded-2xl p-6 shadow-lg">
                <!-- Share Header -->
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h3 class="font-bold text-slate-800 text-xl mb-2 line-clamp-2"><?= htmlspecialchars($share['note_title']) ?></h3>
                        <?php if (!empty($share['description'])): ?>
                        <p class="text-slate-600 text-sm font-medium"><?= htmlspecialchars($share['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center space-x-2 ml-4">
                        <button onclick="copyShareLink('<?= $share['id'] ?>')" class="copy-btn p-3 bg-sky-50 text-sky-600 rounded-xl hover:bg-sky-100 transition-all shadow-sm hover:shadow-md" title="Sao chép link">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button onclick="deleteShare('<?= $share['id'] ?>')" class="p-3 bg-red-50 text-red-600 rounded-xl hover:bg-red-100 transition-all shadow-sm hover:shadow-md" title="Xóa chia sẻ">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Share Stats -->
                <div class="flex items-center justify-between text-sm mb-4 bg-gradient-to-r from-slate-50 to-slate-100 p-3 rounded-xl">
                    <div class="flex items-center space-x-4">
                        <span class="flex items-center text-slate-700 font-semibold">
                            <i class="fas fa-eye mr-2 text-sky-500"></i>
                            <?= $share['views'] ?> lượt xem
                        </span>
                        <span class="flex items-center text-slate-700 font-semibold">
                            <i class="fas fa-calendar mr-2 text-emerald-500"></i>
                            <?= date('d/m/Y', strtotime($share['created_at'])) ?>
                        </span>
                    </div>
                </div>
                
                <!-- Share Link -->
                <div class="bg-gradient-to-r from-sky-50 to-blue-50 rounded-xl p-4 border-2 border-sky-100">
                    <div class="flex items-center space-x-3">
                        <input type="text" 
                               value="<?= SITE_URL ?>share.php?id=<?= $share['id'] ?>" 
                               class="flex-1 text-sm text-slate-700 bg-transparent border-none outline-none font-medium" 
                               readonly 
                               id="shareLink<?= $share['id'] ?>">
                        <button onclick="copyShareLink('<?= $share['id'] ?>')" class="text-sky-600 hover:text-sky-700 transition-colors p-2 hover:bg-sky-100 rounded-lg">
                            <i class="fas fa-external-link-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl p-8 text-center">
            <div class="progress-bordered mx-auto mb-4"></div>
            <p class="text-gray-600">Đang xử lý...</p>
        </div>
    </div>

    <script>
        function copyShareLink(shareId) {
            const input = document.getElementById('shareLink' + shareId);
            input.select();
            input.setSelectionRange(0, 99999);
            
            try {
                document.execCommand('copy');
                showNotification('Đã sao chép link chia sẻ!', 'success');
            } catch (err) {
                showNotification('Không thể sao chép link', 'error');
            }
        }

        async function deleteShare(shareId) {
            if (!confirm('Bạn có chắc muốn xóa chia sẻ này không?\n\nLink chia sẻ sẽ không còn hoạt động.')) {
                return;
            }
            
            showLoading(true);
            
            try {
                const response = await fetch('api.php?action=delete_share&id=' + shareId, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    showNotification('Xóa chia sẻ thành công', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    throw new Error(data.error || 'Có lỗi xảy ra');
                }
            } catch (error) {
                showNotification('Lỗi: ' + error.message, 'error');
            } finally {
                showLoading(false);
            }
        }

        function showLoading(show) {
            document.getElementById('loadingModal').classList.toggle('hidden', !show);
        }

        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center space-x-2">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>
<?php
require_once 'config.php';
require_once 'database.php';

$shareId = $_GET['id'] ?? null;
// Tạo tên cookie độc nhất cho từng liên kết chia sẻ
$cookieName = 'viewed_share_' . $shareId;

if (!$shareId) {
    header('Location: expired.html');
    exit;
}

try {
    $conn = getDB();
    $shareInfo = null;

    // Kiểm tra xem cookie đã tồn tại chưa
    if (!isset($_COOKIE[$cookieName])) {
        // Lấy thông tin ghi chú từ bảng shares
        $stmt = $conn->prepare("SELECT note_id, description, views, created_at FROM shares WHERE id = ?");
        $stmt->execute([$shareId]);
        $shareInfo = $stmt->fetch();

        if (!$shareInfo) {
            header('Location: expired.html');
            exit;
        }

        // Tăng lượt xem
        $stmt = $conn->prepare("UPDATE shares SET views = views + 1 WHERE id = ?");
        $stmt->execute([$shareId]);
        $shareInfo['views']++; // Cập nhật biến để hiển thị đúng
        
        // Thiết lập cookie để ghi nhớ đã xem trong 12h
        setcookie($cookieName, 'true', time() + (43200), "/");

    } else {
        // Nếu đã xem, chỉ lấy thông tin mà không tăng views
        $stmt = $conn->prepare("SELECT note_id, description, views, created_at FROM shares WHERE id = ?");
        $stmt->execute([$shareId]);
        $shareInfo = $stmt->fetch();
    }

    if (!$shareInfo) {
        header('Location: expired.html');
        exit;
    }

    // Lấy nội dung ghi chú
    $stmt = $conn->prepare("SELECT title, content, created_at FROM notes WHERE id = ?");
    $stmt->execute([$shareInfo['note_id']]);
    $note = $stmt->fetch();

    if (!$note) {
        header('Location: expired.html');
        exit;
    }

} catch (Exception $e) {
    // Nếu có lỗi, chuyển hướng đến trang lỗi
    header('Location: expired.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chia sẻ: <?= htmlspecialchars($note['title']) ?></title>
    <link rel="shortcut icon" href="xitrum.png" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="src/theme.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .glass-effect {
            backdrop-filter: blur(16px) saturate(180%);
            background-color: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .content-area {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .content-area::-webkit-scrollbar {
            width: 6px;
        }
        
        .content-area::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 3px;
        }
        
        .content-area::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.5);
            border-radius: 3px;
        }
        
        .content-area img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 12px 0;
        }
        
        .content-area h1, .content-area h2, .content-area h3 {
            margin-top: 20px;
            margin-bottom: 10px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .content-area p {
            margin-bottom: 12px;
            line-height: 1.6;
        }
        
        .content-area ul, .content-area ol {
            margin: 12px 0;
            padding-left: 24px;
        }
        
        .content-area li {
            margin-bottom: 6px;
        }
        
        .content-area blockquote {
            border-left: 4px solid #6366f1;
            padding-left: 16px;
            margin: 16px 0;
            font-style: italic;
            color: #64748b;
            background: rgba(99, 102, 241, 0.05);
            padding: 12px 16px;
            border-radius: 0 8px 8px 0;
        }
        
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(3deg); }
        }
        
        .btn-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .copy-animation {
            animation: copyPulse 0.6s ease;
        }
        
        @keyframes copyPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); background-color: #10b981; }
            100% { transform: scale(1); }
        }
        
        .floating-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }
        
        .shape {
            position: absolute;
            opacity: 0.1;
        }
        
        .shape-1 {
            top: 10%;
            left: 10%;
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }
        
        .shape-2 {
            top: 20%;
            right: 15%;
            width: 80px;
            height: 80px;
            background: linear-gradient(45deg, #f59e0b, #ef4444);
            border-radius: 20px;
            animation: float 10s ease-in-out infinite reverse;
        }
        
        .shape-3 {
            bottom: 20%;
            left: 20%;
            width: 40px;
            height: 40px;
            background: linear-gradient(45deg, #10b981, #3b82f6);
            transform: rotate(45deg);
            animation: float 7s ease-in-out infinite;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Gradient Background -->
    <div class="min-h-screen w-full relative">
        <div 
            class="absolute inset-0 z-0"
            style="background: radial-gradient(125% 125% at 50% 10%, #ffffff 40%, #0ea5e9 100%);"
        ></div>
        
        <!-- Floating Shapes -->
        <div class="floating-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>
        
        <!-- Content -->
        <div class="relative z-10 min-h-screen py-8 px-4">
            <div class="max-w-4xl mx-auto">
                <!-- Main Card -->
                <div class="glass-effect rounded-3xl shadow-2xl overflow-hidden mb-8 border-2 border-white">
                    <!-- Note Header -->
                    <div class="bg-gradient-to-r from-sky-500 to-blue-600 p-8 text-white">
                        <div class="flex items-start justify-between flex-wrap gap-4">
                            <div class="flex-1 min-w-0">
                                <h2 class="text-3xl font-bold mb-3 drop-shadow-md"><?= htmlspecialchars($note['title']) ?></h2>
                                <?php if (!empty($shareInfo['description'])): ?>
                                    <p class="text-white/90 text-lg"><?= htmlspecialchars($shareInfo['description']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="text-right text-sm text-white/90 bg-white/10 backdrop-blur-sm rounded-xl p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-eye mr-2"></i>
                                    <span class="font-semibold"><?= number_format($shareInfo['views'] + 1) ?> lượt xem</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-calendar mr-2"></i>
                                    <span class="font-semibold"><?= date('d/m/Y', strtotime($note['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Share Link Section -->
                    <div class="p-8 bg-gradient-to-r from-sky-50 to-blue-50 border-b-2 border-blue-100">
                        <label class="block text-base font-bold text-slate-800 mb-4">
                            <i class="fas fa-link mr-2 text-sky-600"></i>
                            Liên kết chia sẻ
                        </label>
                        <div class="flex gap-3">
                            <input 
                                type="text" 
                                id="shareUrl" 
                                value="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>"
                                class="flex-1 px-5 py-3.5 border-2 border-blue-200 rounded-xl bg-white focus:ring-2 focus:ring-sky-200 focus:border-sky-500 text-sm font-medium text-slate-700 shadow-sm"
                                readonly
                            >
                            <button 
                                onclick="copyLink()" 
                                id="copyBtn"
                                class="px-8 py-3.5 bg-gradient-to-r from-sky-500 to-blue-600 text-white rounded-xl hover:from-sky-600 hover:to-blue-700 transition-all duration-200 font-semibold shadow-md hover:shadow-lg hover:scale-105"
                            >
                                <i class="fas fa-copy mr-2"></i>
                                Sao chép
                            </button>
                        </div>
                        <p class="text-sm text-slate-600 mt-3 font-medium">
                            <i class="fas fa-info-circle mr-1 text-sky-500"></i>
                            Liên kết này có thể được chia sẻ công khai
                        </p>
                    </div>
                    
                    <!-- Content -->
                    <div class="content-area p-6">
                        <?php if (empty($note['content']) || trim(strip_tags($note['content'])) === ''): ?>
                            <div class="text-center py-12 text-gray-500">
                                <i class="fas fa-file-text text-4xl mb-4 opacity-50"></i>
                                <p class="text-lg">Ghi chú này chưa có nội dung.</p>
                            </div>
                        <?php else: ?>
                            <div class="prose prose-lg max-w-none">
                                <?= $note['content'] ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="text-center space-y-6">
                    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                        <a 
                            href="login.php?mode=register" 
                            class="btn-hover inline-flex items-center space-x-3 px-10 py-5 bg-white text-sky-600 rounded-2xl shadow-xl font-bold hover:shadow-2xl border-2 border-sky-100 hover:border-sky-200 text-lg"
                        >
                            <i class="fas fa-user-plus text-xl"></i>
                            <span>Tạo tài khoản</span>
                        </a>
                        
                        <a 
                            href="login.php" 
                            class="btn-hover inline-flex items-center space-x-3 px-10 py-5 bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-2xl shadow-xl font-bold hover:from-emerald-600 hover:to-teal-700 text-lg"
                        >
                            <i class="fas fa-sticky-note text-xl"></i>
                            <span>Tạo ghi chú của bạn</span>
                        </a>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
        <i class="fas fa-check mr-2"></i>
        <span>Đã sao chép liên kết!</span>
    </div>

    <script>
        function copyLink() {
            const input = document.getElementById('shareUrl');
            const btn = document.getElementById('copyBtn');
            const toast = document.getElementById('toast');
            
            // Select and copy
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Animate button
            btn.classList.add('copy-animation');
            btn.innerHTML = '<i class="fas fa-check mr-2"></i>Đã sao chép!';
            
            // Show toast
            toast.classList.remove('translate-x-full');
            
            setTimeout(() => {
                btn.classList.remove('copy-animation');
                btn.innerHTML = '<i class="fas fa-copy mr-2"></i>Sao chép';
                toast.classList.add('translate-x-full');
            }, 2000);
        }
        
        // Auto-select URL on click
        document.getElementById('shareUrl').addEventListener('click', function() {
            this.select();
        });
    </script>
</body>
</html>
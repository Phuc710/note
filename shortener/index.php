<?php
session_start();
require_once '../config.php';
require_once '../database.php';

// Tạo bảng nếu chưa có
try {
    $conn = getDB();
    $conn->exec("CREATE TABLE IF NOT EXISTS short_links (
        id VARCHAR(10) PRIMARY KEY,
        original_url TEXT NOT NULL,
        custom_alias VARCHAR(50),
        clicks INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        user_id INT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {
    // Ignore if table exists
}

// Xử lý tạo link rút gọn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'shorten') {
    header('Content-Type: application/json');
    
    $originalUrl = trim($_POST['url'] ?? '');
    $customAlias = trim($_POST['alias'] ?? '');
    
    if (empty($originalUrl)) {
        echo json_encode(['success' => false, 'error' => 'URL không được để trống']);
        exit;
    }
    
    // Validate URL
    if (!filter_var($originalUrl, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'error' => 'URL không hợp lệ']);
        exit;
    }
    
    try {
        $conn = getDB();
        
        // Tạo ID ngẫu nhiên (kiểu Twitter)
        if (!empty($customAlias)) {
            // Kiểm tra alias đã tồn tại chưa
            $stmt = $conn->prepare("SELECT id FROM short_links WHERE custom_alias = ?");
            $stmt->execute([$customAlias]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Tên tùy chỉnh đã được sử dụng']);
                exit;
            }
            $shortId = $customAlias;
        } else {
            do {
                $shortId = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 7);
                $stmt = $conn->prepare("SELECT id FROM short_links WHERE id = ?");
                $stmt->execute([$shortId]);
            } while ($stmt->fetch());
        }
        
        // Lưu vào database
        $userId = $_SESSION['user_id'] ?? null;
        $stmt = $conn->prepare("INSERT INTO short_links (id, original_url, custom_alias, user_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$shortId, $originalUrl, $customAlias, $userId]);
        
        // Tạo full URL với domain
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'];
        $basePath = str_replace('/shortener/index.php', '', $_SERVER['SCRIPT_NAME']);
        $shortUrl = $protocol . $domain . $basePath . '/shortener/s/' . $shortId;
        
        echo json_encode([
            'success' => true,
            'short_url' => $shortUrl,
            'short_id' => $shortId
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Lỗi: ' . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rút gọn link</title>
    <script>
        window.process = { env: { NODE_ENV: 'production' } };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../src/theme.css">
    <link rel="stylesheet" href="../src/index.css">
    <style>
        body {
            overflow-y: auto !important;
            height: auto !important;
        }
    </style>
</head>
<body class="bg-secondary">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Back Button -->
            <div class="mb-6">
            <a href="../index.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-all shadow-md hover:shadow-lg border-2 border-blue-600 hover:border-blue-700">
                <i class="fas fa-arrow-left mr-2"></i>
                <span class="font-semibold text-white">Về trang chủ</span>
            </a>
            </div>
            
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-gradient-to-br from-sky-400 to-blue-600 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                    <i class="fas fa-link text-white text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-slate-800 mb-4">Rút gọn link miễn phí & cực nhanh</h1>
                <p class="text-slate-600">Nhanh - Đẹp - Không quảng cáo - Đầy đủ thống kê!</p>
            </div>

            <!-- Form -->
            <div class="bg-white rounded-3xl shadow-2xl p-8 mb-8 border-2 border-slate-100">
                <form id="shortenForm" class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">
                            <i class="fas fa-link mr-2 text-blue-500"></i>
                            URL cần rút gọn
                        </label>
                        <input 
                            type="url" 
                            name="url" 
                            id="urlInput"
                            placeholder="https://example.com/very-long-url..."
                            required
                            class="w-full px-6 py-4 border-2 border-slate-200 rounded-xl focus:border-blue-500 focus:ring-0 transition-all text-lg text-slate-800"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">
                            <i class="fas fa-edit mr-2 text-green-500"></i>
                            Tên tùy chỉnh (tùy chọn)
                        </label>
                        <input 
                            type="text" 
                            name="alias" 
                            id="aliasInput"
                            placeholder="my-custom-name"
                            class="w-full px-6 py-4 border-2 border-slate-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all text-lg text-slate-800"
                        >
                        <p class="text-sm text-slate-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Để trống để tạo ID ngẫu nhiên
                        </p>
                    </div>
                    
                    <button 
                        type="submit"
                        class="w-full py-4 bg-gradient-to-r from-sky-500 to-blue-600 text-white rounded-xl hover:from-sky-600 hover:to-blue-700 transition-all font-bold text-lg shadow-lg hover:shadow-xl"
                    >
                        <i class="fas fa-magic mr-2"></i>
                        Rút gọn link
                    </button>
                </form>
            </div>

            <!-- Result -->
            <div id="resultPanel" class="bg-white rounded-3xl shadow-2xl p-8 hidden mb-8 border-2 border-green-100">
                <div class="text-center">
                    <div class="w-16 h-16 bg-gradient-to-br from-green-400 to-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                        <i class="fas fa-check text-white text-xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-800 mb-6">Link của bạn:</h2>
                    <div class="flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4">
                        <input 
                            type="text" 
                            id="shortUrlInput"
                            readonly
                            class="flex-1 px-6 py-4 bg-slate-50 border-2 border-green-200 rounded-xl font-mono text-lg font-semibold text-blue-600"
                        >
                        <button 
                            onclick="copyShortUrl()"
                            class="px-6 py-4 bg-green-500 hover:bg-green-600 text-white rounded-xl transition-all font-bold shadow-md hover:shadow-lg"
                        >
                            <i class="fas fa-copy mr-2"></i>
                            Sao chép
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Dark mode
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
        }

        document.getElementById('shortenForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'shorten');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('shortUrlInput').value = data.short_url;
                    document.getElementById('resultPanel').classList.remove('hidden');
                    document.getElementById('urlInput').value = '';
                    document.getElementById('aliasInput').value = '';
                } else {
                    alert('Lỗi: ' + data.error);
                }
            } catch (error) {
                alert('Có lỗi xảy ra: ' + error.message);
            }
        });

        function copyShortUrl() {
            const input = document.getElementById('shortUrlInput');
            input.select();
            navigator.clipboard.writeText(input.value);
            
            // Toast notification
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
<?php
// SIMPLE IMAGE UPLOADER - NO DATABASE NEEDED
// Upload được xử lý bởi upload.php
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload ảnh</title>
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
                <div class="w-20 h-20 bg-gradient-to-br from-purple-400 to-pink-600 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                    <i class="fas fa-cloud-upload-alt text-white text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-slate-800 mb-4">Upload ảnh với nén tự động</h1>
                <p class="text-slate-600">Upload ảnh, tự động nén giảm dung lượng, nhận tất cả link formats (có thể chọn nhiều ảnh)</p>
            </div>

            <!-- Upload Form -->
            <div class="glass-card rounded-3xl shadow-2xl p-8 mb-8">
                <div class="border-2 border-dashed border-purple-300 rounded-2xl p-8 text-center hover:border-purple-400 transition-colors" 
                     ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragenter="handleDragEnter(event)" ondragleave="handleDragLeave(event)">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-100 to-pink-100 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-cloud-upload-alt text-purple-500 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-2">Kéo thả ảnh vào đây</h3>
                    <p class="text-slate-600 mb-6">Hoặc click để chọn file (có thể chọn nhiều ảnh)</p>
                    <input type="file" id="imageInput" accept="image/*" multiple class="hidden" onchange="handleFiles(this.files)">
                    <button onclick="document.getElementById('imageInput').click()" 
                            class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-xl hover:from-purple-600 hover:to-pink-700 transition-all font-bold shadow-lg hover:shadow-xl">
                        <i class="fas fa-folder-open mr-2"></i>
                        Chọn ảnh
                    </button>
                    <p class="text-sm text-slate-500 mt-4">
                        <i class="fas fa-info-circle mr-1"></i>
                        Hỗ trợ: JPG, PNG, GIF, WEBP (tối đa 10MB mỗi ảnh)
                    </p>
                </div>
            </div>

            <!-- Loading -->
            <div id="loadingPanel" class="glass-card rounded-3xl shadow-2xl p-8 hidden mb-8">
                <div class="flex items-center space-x-4">
                    <div class="spinner border-4 border-purple-200 border-t-purple-600 rounded-full w-12 h-12 animate-spin"></div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-800">Đang upload...</h3>
                        <p class="text-slate-600">Vui lòng đợi trong giây lát</p>
                    </div>
                </div>
            </div>

            <!-- Result -->
            <div id="resultPanel" class="glass-card rounded-3xl shadow-2xl p-8 hidden mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-slate-800">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                        Link ảnh đã upload
                    </h3>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">URL:</label>
                        <div class="flex flex-col sm:flex-row items-start space-y-2 sm:space-y-0 sm:space-x-2">
                            <textarea id="directLinkInput" readonly rows="3" class="flex-1 px-4 py-3 bg-white border-2 border-slate-200 rounded-xl font-mono text-sm text-slate-800 resize-none"></textarea>
                            <button onclick="copyUrl('directLinkInput')" class="px-4 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-xl transition-all">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">HTML:</label>
                        <div class="flex flex-col sm:flex-row items-start space-y-2 sm:space-y-0 sm:space-x-2">
                            <textarea id="htmlInput" readonly rows="3" class="flex-1 px-4 py-3 bg-white border-2 border-slate-200 rounded-xl font-mono text-sm text-slate-800 resize-none"></textarea>
                            <button onclick="copyUrl('htmlInput')" class="px-4 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-xl transition-all">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <script>
        // Dark mode
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
        }

        function handleDragOver(e) {
            e.preventDefault();
        }

        function handleDragEnter(e) {
            e.preventDefault();
            e.currentTarget.classList.add('border-purple-500', 'bg-purple-50');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('border-purple-500', 'bg-purple-50');
        }

        function handleDrop(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('border-purple-500', 'bg-purple-50');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFiles(files);
            }
        }

        async function handleFiles(files) {
            if (files.length === 0) return;
            
            // Validate all files
            for (let file of files) {
                if (!file.type.startsWith('image/')) {
                    alert('Vui lòng chỉ chọn file ảnh');
                    return;
                }
            }

            // Show loading
            document.getElementById('loadingPanel').classList.remove('hidden');
            document.getElementById('resultPanel').classList.add('hidden');

            let allDirectLinks = [];
            let allHTMLs = [];

            // Upload từng file
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const formData = new FormData();
                formData.append('image', file);

                try {
                    const response = await fetch('../upload.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        allDirectLinks.push(data.links.direct);
                        allHTMLs.push(data.links.html);
                        
                        // Hiển thị thông tin nén
                        console.log(`${file.name}: ${formatBytes(data.original_size)} → ${formatBytes(data.compressed_size)} (giảm ${data.compression_ratio}%)`);
                    } else {
                        alert('Lỗi upload ' + file.name + ': ' + data.error);
                    }
                } catch (error) {
                    alert('Lỗi upload ' + file.name + ': ' + error.message);
                }
            }

            document.getElementById('loadingPanel').classList.add('hidden');
            
            if (allDirectLinks.length > 0) {
                document.getElementById('directLinkInput').value = allDirectLinks.join('\n');
                document.getElementById('htmlInput').value = allHTMLs.join('\n');
                document.getElementById('resultPanel').classList.remove('hidden');
            }
        }

        function copyUrl(inputId) {
            const input = document.getElementById(inputId);
            input.select();
            navigator.clipboard.writeText(input.value);
            showToast('Đã copy!');
        }

        function showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
            toast.innerHTML = '<i class="fas fa-check mr-2"></i>' + message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 2000);
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
    </script>
</body>
</html>
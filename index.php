<?php
require_once 'config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Kiểm tra kết nối database
try {
    require_once 'database.php';
    $conn = getDB();
    $conn->query("SELECT 1 FROM notes LIMIT 1");
} catch (Exception $e) {
    header('Location: create_tables.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Note</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="shortcut icon" href="xitrum.png" type="image/x-icon">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="src/index.css">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill-image-resize-module@3.0.0/image-resize.min.js"></script>
    
</head>
<body class="bg-secondary">
    <div class="app-container">
        <div id="sidebar" class="sidebar bg-white shadow-2xl">
            <div class="sidebar-content">
                <div class="p-6 border-b border-slate-200 bg-gradient-to-r from-sky-400 to-blue-500">
                    <div class="flex items-center justify-between text-white">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-white/30 rounded-xl flex items-center justify-center backdrop-blur-sm shadow-md">
                                <i class="fas fa-sticky-note text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-xl font-bold drop-shadow-sm"><?= htmlspecialchars($_SESSION['full_name']) ?></h1>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button onclick="toggleSidebar()" class="p-2 hover:bg-white/20 rounded-lg transition-all duration-200 md:hidden">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                            <button onclick="toggleSidebar()" class="p-2 hover:bg-white/20 rounded-lg transition-all duration-200 hidden md:block">
                                <i class="fas fa-chevron-left text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="p-4 border-b border-slate-200 bg-white">
                    <div class="flex space-x-3">
                        <button onclick="addNote()" class="flex-1 flex items-center justify-center space-x-2 px-4 py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-white rounded-xl hover:from-sky-600 hover:to-blue-700 transition-all duration-200 text-sm font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                            <i class="fas fa-plus"></i>
                            <span>Ghi chú</span>
                        </button>
                        <button onclick="addFolder()" class="flex-1 flex items-center justify-center space-x-2 px-4 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-xl hover:from-emerald-600 hover:to-teal-700 transition-all duration-200 text-sm font-medium shadow-md hover:shadow-lg transform hover:scale-105">
                            <i class="fas fa-folder-plus"></i>
                            <span>Thư mục</span>
                        </button>
                    </div>
                </div>
                
                <div id="notesList" class="flex-1 overflow-y-auto p-4">
                    <div class="text-center text-slate-400 mt-8">
                        <div class="progress-bordered mx-auto mb-4"></div>
                        <p>Đang tải...</p>
                    </div>
                </div>
                
                <div class="p-4 border-t border-slate-200 bg-slate-50">
                    <div class="flex flex-col space-y-2 text-sm text-slate-600 mb-3">
                        <!-- Tools Dropdown -->
                        <div class="tools-dropdown relative">
                            <button class="tools-btn flex items-center justify-between w-full px-3 py-2 hover:bg-blue-100 rounded-lg transition-colors group">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-tools text-blue-600"></i>
                                    <span class="font-medium">Công cụ</span>
                                </div>
                                <i class="fas fa-chevron-down text-xs transition-transform tools-chevron"></i>
                            </button>
                            <div class="tools-menu hidden bg-white rounded-lg shadow-lg border border-gray-200 mt-1">
                                <a href="shortener/" class="flex items-center space-x-2 px-3 py-2 pl-8 hover:bg-blue-50 transition-colors">
                                    <i class="fas fa-link text-blue-600 text-sm"></i>
                                    <span class="text-sm">Rút gọn link</span>
                                </a>
                                <a href="uploader/" class="flex items-center space-x-2 px-3 py-2 pl-8 hover:bg-indigo-50 transition-colors">
                                    <i class="fas fa-cloud-upload-alt text-indigo-600 text-sm"></i>
                                    <span class="text-sm">Upload ảnh</span>
                                </a>
                            </div>
                        </div>
                        
                        <a href="all.php" class="flex items-center space-x-2 px-3 py-2 hover:bg-green-100 rounded-lg transition-colors group">
                            <i class="fas fa-th-large text-green-600 group-hover:scale-110 transition-transform"></i>
                            <span class="font-medium group-hover:text-slate-900 transition-colors">Quản lý ALL</span>
                        </a>
                    </div>
                    <div class="border-t border-slate-200 pt-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <a href="manage.php" class="flex items-center space-x-2 px-3 py-2 hover:bg-slate-200 rounded-lg transition-colors">
                                        <i class="fas fa-cog"></i>
                                        <span>Quản lý</span>
                                    </a>
                                <?php endif; ?>                                
                                <button onclick="toggleDarkMode()" class="flex items-center space-x-2 px-3 py-2 hover:bg-slate-200 rounded-lg transition-colors">
                                    <i id="darkModeIcon" class="fas fa-moon"></i>
                                    <span id="darkModeText">Tối</span>
                                </button>
                            </div>
                            <a href="logout.php" class="flex items-center space-x-2 px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <i class="fas fa-sign-out-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="sidebar-mini">
                <button onclick="toggleSidebar()" class="mini-button tooltip" data-tooltip="Mở menu">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="h-px bg-white/20 w-6 my-3"></div>
                <button onclick="addNote()" class="mini-button tooltip" data-tooltip="Thêm ghi chú">
                    <i class="fas fa-plus"></i>
                </button>
                <button onclick="addFolder()" class="mini-button tooltip" data-tooltip="Thêm thư mục">
                    <i class="fas fa-folder-plus"></i>
                </button>
            </div>
        </div>
        
        <div class="main-content">
            <div class="mobile-header" style="z-index: 100;">
                <button onclick="toggleSidebar()" class="p-2 text-slate-600 hover:text-slate-900 transition-colors" style="z-index: 101; position: relative;">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-lg font-semibold text-slate-800 flex-1 text-center font-size-lg text-xl font-medium">Ghi chú</h1>
                <div class="w-10"></div>
            </div>
            
            <div id="noteEditor" class="note-editor hidden">
                <div class="note-header">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div class="flex-1">
                            <h2 id="noteTitle" class="text-2xl font-bold text-slate-800 mb-2"></h2>
                            <p id="noteTime" class="text-sm text-slate-500 flex items-center">
                                <span></span>
                            </p>
                        </div>
                        <div class="flex items-center space-x-4">
                            <button id="saveBtn" onclick="saveNote()" class="flex items-center space-x-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-all duration-200 shadow-md hover:shadow-lg">
                                <i class="fas fa-save"></i>
                                <span>Lưu</span>
                            </button>
                            <button onclick="deleteCurrentNote()" class="flex items-center space-x-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-all duration-200 shadow-md hover:shadow-lg">
                                <i class="fas fa-trash"></i>
                                <span>Xóa</span>
                            </button>
                            <button id="shareBtn" onclick="openShareModal()" class="flex items-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all duration-200 shadow-md hover:shadow-lg">
                                <i class="fas fa-share-alt"></i>
                                <span>Chia sẻ</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="note-content-area">
                    <div id="editor-container"></div>
                </div>
            </div>
            
            <div id="welcomeScreen" class="welcome-screen">
    <div class="text-center max-w-lg mx-auto px-6">
        <!-- Icon with subtle animation -->
        <div class="w-24 h-24 bg-gradient-to-br from-sky-100 to-blue-200 rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-lg border-2 border-white">
            <i class="fas fa-feather-alt text-3xl text-blue-600"></i>
        </div>
        
        <!-- Welcome title -->
        <h2 class="text-4xl font-bold text-slate-800 mb-4">Chào mừng đến với Notes</h2>
        <p class="text-lg text-slate-600 mb-10">Nơi lưu giữ những ý tưởng tuyệt vời của bạn</p>
        
        <!-- Inspirational quote -->
        <div class="relative mb-10 p-8 bg-gradient-to-br from-white to-blue-50 backdrop-blur-sm rounded-2xl border-2 border-blue-100 shadow-xl">
            <div class="absolute -top-3 -left-3 w-8 h-8 text-blue-500 text-4xl font-serif leading-none">"</div>
            <p class="text-slate-800 italic text-xl leading-relaxed font-medium">
                Mỗi trang sách mở ra <br>là một cánh cửa dẫn đến vô vàn thế giới.
            </p>
            <div class="absolute -bottom-3 -right-3 w-8 h-8 text-blue-500 text-4xl font-serif leading-none rotate-180">"</div>
        </div>
        
        <!-- CTA Button -->
        <button onclick="addNote()" class="inline-flex items-center space-x-3 px-10 py-5 bg-gradient-to-r from-sky-500 to-blue-600 text-white rounded-2xl hover:from-sky-600 hover:to-blue-700 transition-all duration-300 shadow-lg hover:shadow-xl hover:scale-105 font-semibold text-lg">
            <i class="fas fa-plus text-lg"></i>
            <span>Bắt đầu viết</span>
        </button>
    </div>
</div>

    <!-- Create Modal -->
    <div id="createModal" class="create-modal-overlay">
        <div class="create-modal-content">
            <div class="create-modal-header">
                <div id="createModalIcon" class="create-modal-icon note-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h3 id="createModalTitle" class="create-modal-title">Tạo ghi chú mới</h3>
                <p id="createModalSubtitle" class="create-modal-subtitle">Đặt tên cho ghi chú của bạn</p>
            </div>
            <div class="create-modal-body">
                <label class="create-modal-label">Tên</label>
                <input type="text" id="createModalInput" class="create-modal-input" placeholder="Nhập tên...">
            </div>
            <div class="create-modal-footer">
                <button onclick="closeCreateModal()" class="create-modal-btn create-modal-btn-cancel">
                    <i class="fas fa-times"></i>
                    Hủy
                </button>
                <button id="createModalConfirm" onclick="confirmCreate()" class="create-modal-btn create-modal-btn-confirm">
                    <i class="fas fa-check"></i>
                    Tạo
                </button>
            </div>
        </div>
    </div>

    <div id="shareModal" class="modal-overlay">
        <div class="modal-content bg-white rounded-2xl shadow-2xl border-2 border-blue-100">
            <h3 class="text-2xl font-bold mb-4 text-slate-800 flex items-center">
                <i class="fas fa-share-alt mr-3 text-sky-500"></i>
                Chia sẻ ghi chú
            </h3>
            <p class="text-slate-600 mb-6 text-base">Tạo một liên kết công khai để chia sẻ ghi chú này.</p>
            <div class="mb-6">
                <label for="shareDescription" class="block text-sm font-semibold text-slate-700 mb-2">Mô tả chia sẻ</label>
                <textarea id="shareDescription" rows="2" class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-sky-500 focus:ring-2 focus:ring-sky-100 transition-all bg-white text-slate-700 text-base resize-none"></textarea>
            </div>
            <div class="flex items-center space-x-3 mb-6" id="shareLinkContainer" style="display: none;">
                <input type="text" id="shareLinkInput" class="flex-1 px-4 py-3 border-2 border-slate-200 rounded-xl bg-slate-50 text-slate-700 font-medium text-sm" readonly>
                <button onclick="copyShareLink()" class="p-3 bg-sky-500 hover:bg-sky-600 rounded-xl text-white transition-all shadow-md hover:shadow-lg">
                    <i class="fas fa-copy text-lg"></i>
                </button>
            </div>
            <div class="flex justify-end space-x-3">
                <button onclick="closeShareModal()" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-xl hover:bg-slate-200 transition-all font-semibold">Hủy</button>
                <button id="createShareLinkBtn" onclick="createShareLink()" class="px-6 py-3 bg-gradient-to-r from-sky-500 to-blue-600 text-white rounded-xl hover:from-sky-600 hover:to-blue-700 transition-all font-semibold shadow-md hover:shadow-lg">Tạo liên kết</button>
            </div>
        </div>
    </div>

    <!-- Rename Modal -->
    <div id="renameModal" class="modal-overlay">
        <div class="modal-content bg-white rounded-2xl shadow-2xl border-2 border-amber-100">
            <h3 class="text-2xl font-bold mb-6 text-slate-800 flex items-center">
                <i class="fas fa-edit mr-3 text-amber-500"></i>
                Đổi tên
            </h3>
            <input type="text" id="renameInput" class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition-all bg-white text-slate-700 text-base mb-6" placeholder="Nhập tên mới">
            <div class="flex justify-end space-x-3">
                <button onclick="closeRenameModal()" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-xl hover:bg-slate-200 transition-all font-semibold">Hủy</button>
                <button onclick="confirmRename()" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-xl hover:from-emerald-600 hover:to-teal-700 transition-all font-semibold shadow-md hover:shadow-lg">Lưu</button>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-content bg-white rounded-2xl shadow-2xl border-2 border-red-100">
            <h3 class="text-2xl font-bold mb-4 text-slate-800 flex items-center">
                <i class="fas fa-trash mr-3 text-red-500"></i>
                Xóa ghi chú
            </h3>
            <p class="text-slate-600 mb-6 text-base">Bạn có chắc chắn muốn xóa ghi chú này? Hành động này không thể hoàn tác.</p>
            <div class="flex justify-end space-x-3">
                <button onclick="closeDeleteModal()" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-xl hover:bg-slate-200 transition-all font-semibold">Hủy</button>
                <button onclick="confirmDelete()" class="px-6 py-3 bg-gradient-to-r from-red-500 to-rose-600 text-white rounded-xl hover:from-red-600 hover:to-rose-700 transition-all font-semibold shadow-md hover:shadow-lg">Xóa</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script src="src/index.js"></script>
</body>
</html>
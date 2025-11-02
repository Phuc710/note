let notes = [];
let activeNoteId = null;
let saveTimeout;
let isLoading = false;
let quill;
let expandedFolders = new Set();
let isDarkMode = false;
let draggedNote = null;
let renameNoteId = null;
let deleteNoteId = null;
let dropTarget = null;

function handleDragStart(e, noteId) {
    draggedNote = noteId;
    e.target.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', noteId);
}

function handleDragOver(e, noteId) {
    e.preventDefault();
    const target = e.target.closest('.note-item');
    if (target && target.dataset.type === 'folder') {
        dropTarget = noteId;
        target.classList.add('drag-over');
    }
}

function handleDragLeave(e) {
    e.preventDefault();
    const target = e.target.closest('.note-item');
    if (target) {
        target.classList.remove('drag-over');
    }
}

function handleDrop(e, targetId) {
    e.preventDefault();
    const target = e.target.closest('.note-item');
    if (target) {
        target.classList.remove('drag-over');
    }
    
    if (draggedNote && targetId && draggedNote !== targetId) {
        moveNote(draggedNote, targetId);
    }
    
    draggedNote = null;
    dropTarget = null;
}

function handleDragEnd(e) {
    e.target.classList.remove('dragging');
    const items = document.querySelectorAll('.note-item');
    items.forEach(item => item.classList.remove('drag-over'));
    draggedNote = null;
    dropTarget = null;
}

async function moveNote(noteId, targetFolderId) {
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'move_note',
                note_id: noteId,
                target_folder_id: targetFolderId
            })
        });
        
        if (response.ok) {
            await loadNotes();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// File Upload Handlers
let dropZone = null;

function initializeFileUpload() {
    dropZone = document.getElementById('editor-container');
    if (!dropZone) return;

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });

    dropZone.addEventListener('drop', handleDrop, false);
}

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlight(e) {
    dropZone.classList.add('drag-highlight');
}

function unhighlight(e) {
    dropZone.classList.remove('drag-highlight');
}

async function handleDrop(e) {
    const files = e.dataTransfer.files;
    const maxSize = 8 * 1024 * 1024; // 8MB

    for (let file of files) {
        if (file.size > maxSize) {
            showToast('File quá lớn. Giới hạn là 8MB', 'error');
            continue;
        }

        try {
            await uploadFile(file);
        } catch (error) {
            console.error('Upload error:', error);
            showToast('Lỗi tải lên file: ' + error.message, 'error');
        }
    }
}

async function uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch('upload.php', {
        method: 'POST',
        body: formData
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Upload failed');
    }

    const data = await response.json();
    if (data.success) {
        insertFileToEditor(data.file);
        showToast('Tải lên thành công: ' + file.name, 'success');
    }
}

function insertFileToEditor(file) {
    let content = '';
    
    // Nếu là ảnh, chèn thẻ img
    if (file.type.startsWith('image/')) {
        content = `<img src="${file.url}" alt="${file.name}">`;
    } else {
        // Với các file khác, tạo link download
        content = `<a href="${file.url}" target="_blank" download>${file.name}</a>`;
    }
    
    const range = quill.getSelection(true);
    quill.insertEmbed(range.index, 'text', content);
    quill.setSelection(range.index + 1);
}

document.addEventListener('DOMContentLoaded', function() {
    initializeFileUpload();
    initializeNotesListDropZone();
    const toolbarOptions = [
        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'color': [] }, { 'background': [] }],
        [{ 'align': [] }],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        [{ 'indent': '-1'}, { 'indent': '+1' }],
        ['link', 'image'],
        ['clean'],
        ['upper', 'lower'],
        ['copy']
    ];

    const icons = Quill.import('ui/icons');
    icons['upper'] = '<i class="fas fa-arrow-up"></i>';
    icons['lower'] = '<i class="fas fa-arrow-down"></i>';
    icons['copy'] = '<i class="fas fa-copy"></i>';

    quill = new Quill('#editor-container', {
        theme: 'snow',
        modules: {
            toolbar: {
                container: toolbarOptions,
                handlers: {
                    'upper': function() { changeCase('upper'); },
                    'lower': function() { changeCase('lower'); },
                    'copy': function() { copyContent(); }
                }
            }
        }
    });

    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        toggleDarkMode();
    }

    loadNotes();
    
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            saveNote();
        }
        
        if (e.key === 'Escape') {
            if (window.innerWidth < 768) closeSidebar();
            closeCreateModal();
            closeRenameModal();
            closeDeleteModal();
            closeShareModal();
        }
        
        if (e.key === 'Enter') {
            if (document.getElementById('createModal').classList.contains('open')) {
                confirmCreate();
            } else if (document.getElementById('renameModal').classList.contains('open')) {
                confirmRename();
            }
        }
    });

    quill.on('text-change', function() {
        if (activeNoteId) {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(saveNote, 5000);
        }
    });

    quill.on('editor-change', (eventName) => {
        if (eventName !== 'selection-change' && activeNoteId) {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(saveNote, 100);
        }
    });
    
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        if (window.innerWidth >= 768) sidebar.classList.remove('expanded');
    });
});

function toggleDarkMode() {
    const body = document.body;
    const icon = document.getElementById('darkModeIcon');
    const text = document.getElementById('darkModeText');
    
    body.classList.toggle('dark-mode');
    isDarkMode = body.classList.contains('dark-mode');
    
    if (isDarkMode) {
        localStorage.setItem('theme', 'dark');
        icon.classList.remove('fa-moon');
        icon.classList.add('fa-sun');
        text.textContent = 'Sáng';
    } else {
        localStorage.setItem('theme', 'light');
        icon.classList.remove('fa-sun');
        icon.classList.add('fa-moon');
        text.textContent = 'Tối';
    }
}

function changeCase(type) {
    if (!activeNoteId) {
        showAlert('Vui lòng chọn một ghi chú', 'error');
        return;
    }

    const range = quill.getSelection();
    if (!range || range.length === 0) {
        showAlert('Vui lòng bôi đen văn bản', 'error');
        return;
    }

    const text = quill.getText(range.index, range.length);
    const newText = type === 'upper' ? text.toUpperCase() : text.toLowerCase();

    quill.deleteText(range.index, range.length);
    quill.insertText(range.index, newText);
}

function copyContent() {
    if (!activeNoteId) {
        showAlert('Vui lòng chọn một ghi chú để sao chép', 'error');
        return;
    }
    
    const content = quill.getText();
    const tempTextArea = document.createElement('textarea');
    tempTextArea.value = content;
    document.body.appendChild(tempTextArea);
    tempTextArea.select();
    document.execCommand('copy');
    document.body.removeChild(tempTextArea);
    showAlert('Đã sao chép nội dung!');
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth >= 768) {
        sidebar.classList.toggle('collapsed');
    } else {
        sidebar.classList.toggle('expanded');
    }
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('expanded');
}

function showAlert(message, type = 'success') {
    const alert = document.createElement('div');
    alert.className = `alert ${type}`;
    alert.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
        ${message}
    `;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
}

function toggleFolderExpansion(folderId, event) {
    event.stopPropagation();
    
    if (expandedFolders.has(folderId)) {
        expandedFolders.delete(folderId);
    } else {
        expandedFolders.add(folderId);
    }

    apiCall(`api.php?action=toggle&id=${folderId}`, { method: 'PUT' }).catch(console.error);
    renderNotes();
}

async function apiCall(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: { 'Content-Type': 'application/json', ...options.headers },
            ...options
        });
        
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'API Error');
        return data;
    } catch (error) {
        console.error('API Error:', error);
        showAlert('Lỗi: ' + error.message, 'error');
        throw error;
    }
}

async function loadNotes() {
    if (isLoading) return;
    isLoading = true;
    
    try {
        const data = await apiCall('api.php?action=notes');
        notes = data;
        renderNotes();
    } catch (error) {
        console.error('Lỗi load notes:', error);
        document.getElementById('notesList').innerHTML = `
            <div class="text-center text-red-500 mt-8">
                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                <p>Không thể tải dữ liệu</p>
                <button onclick="loadNotes()" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">Thử lại</button>
            </div>
        `;
        if (parentId) {
            expandedFolders.add(parentId);
            renderNotes();
        }
    } finally {
        isLoading = false;
    }
}

async function addNote(parentId = null) {
    openCreateModal('note', parentId);
}

async function addFolder() {
    openCreateModal('folder');
}

async function updateTitle(id, title) {
    try {
        await apiCall(`api.php?action=update&id=${id}`, {
            method: 'PUT',
            body: JSON.stringify({ title })
        });
        
        const note = findNoteById(id);
        if (note) {
            note.title = title;
            note.updated_at = new Date().toISOString();
            renderNotes();
        }
        showAlert('Cập nhật tiêu đề thành công');
    } catch (error) {
        console.error('Lỗi update title:', error);
    }
}

async function saveNote() {
    if (!activeNoteId) return;
    
    const btn = document.getElementById('saveBtn');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<div class="loading"></div> <span>Đang lưu...</span>';
    btn.disabled = true;
    
    try {
        const content = quill.root.innerHTML;
        await apiCall(`api.php?action=update&id=${activeNoteId}`, {
            method: 'PUT',
            body: JSON.stringify({ content })
        });
        
        const note = findNoteById(activeNoteId);
        if (note) {
            note.content = content;
            note.updated_at = new Date().toISOString();
            updateNoteInfo(note);
        }
        
        btn.innerHTML = '<i class="fas fa-check"></i> <span>Đã lưu!</span>';
        btn.classList.replace('bg-emerald-600', 'bg-emerald-500');
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.classList.replace('bg-emerald-500', 'bg-emerald-600');
            btn.disabled = false;
        }, 2000);
        
        renderNotes();
    } catch (error) {
        console.error('Lỗi save note:', error);
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

async function deleteNote(id) {
    try {
        await apiCall(`api.php?action=delete&id=${id}`, { method: 'DELETE' });
        
        function removeNote(items) {
            for (let i = 0; i < items.length; i++) {
                if (items[i].id == id) {
                    items.splice(i, 1);
                    return true;
                }
                if (items[i].children && removeNote(items[i].children)) return true;
            }
            return false;
        }
        
        removeNote(notes);
        
        if (activeNoteId == id) {
            activeNoteId = null;
            showWelcomeScreen();
        }
        
        renderNotes();
        const note = findNoteById(id);
        const noteType = note ? (note.type === 'folder' ? 'thư mục' : 'ghi chú') : 'mục';
        showAlert(`Xóa ${noteType} thành công`);
    } catch (error) {
        console.error('Lỗi delete note:', error);
    }
}

async function deleteCurrentNote() {
    if (activeNoteId) openDeleteModal(activeNoteId);
}

function renderNotes() {
    const container = document.getElementById('notesList');
    container.innerHTML = '';
    
    if (notes.length === 0) {
        container.innerHTML = `
            <div class="text-center text-slate-400 mt-8 p-6">
                <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-file-text text-2xl opacity-50"></i>
                </div>
                <h3 class="text-lg font-semibold mb-2">Chưa có ghi chú nào</h3>
                <p class="text-sm mb-4 text-slate-500">Tạo ghi chú đầu tiên để bắt đầu!</p>
                <button onclick="addNote()" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-200 text-sm font-medium shadow-lg hover:shadow-xl transform hover:scale-105">
                    <i class="fas fa-plus mr-2"></i>Tạo ngay
                </button>
            </div>
        `;
        return;
    }
    
    notes.forEach(note => container.appendChild(renderNoteItem(note, 0)));
}

function renderNoteItem(note, level) {
    const div = document.createElement('div');
    div.className = 'mb-1';
    
    const isActive = activeNoteId == note.id;
    const activeClass = isActive ? 'bg-blue-50 border-l-4 border-blue-500 shadow-sm' : '';
    
    const isExpanded = expandedFolders.has(note.id);
    const folderIcon = note.type === 'folder' ? 
        (isExpanded ? 'fa-folder-open text-amber-600' : 'fa-folder text-amber-600') : 
        'fa-file-text text-slate-500';
    
    const toggleIcon = note.type === 'folder' ? 
        (isExpanded ? 'fa-chevron-down' : 'fa-chevron-right') : '';

    const itemDiv = document.createElement('div');
    itemDiv.className = `note-item flex items-center p-3 cursor-pointer group transition-all duration-200 ${activeClass} ${note.type === 'folder' ? 'folder-item' : ''}`;
    itemDiv.style.marginLeft = `${level * 20}px`;
    itemDiv.draggable = true;
    itemDiv.dataset.noteId = note.id;
    itemDiv.dataset.noteType = note.type;
    
    // Drag events
    itemDiv.addEventListener('dragstart', (e) => handleDragStart(e, note.id));
    itemDiv.addEventListener('dragend', handleDragEnd);
    itemDiv.addEventListener('dragover', (e) => handleDragOver(e, note.id));
    itemDiv.addEventListener('dragleave', handleDragLeave);
    itemDiv.addEventListener('drop', (e) => handleDrop(e, note.id));
    
    if (note.type === 'folder') {
        itemDiv.addEventListener('click', (e) => toggleFolderExpansion(note.id, e));
    } else {
        itemDiv.addEventListener('click', () => selectNote(note.id));
    }

    itemDiv.innerHTML = `
        ${note.type === 'folder' ? 
            `<div class="flex items-center mr-3">
                <i class="fas ${toggleIcon} text-xs text-slate-400 mr-3 folder-toggle ${isExpanded ? 'rotated' : ''} transition-transform duration-200"></i>
                <i class="fas ${folderIcon} text-lg"></i>
            </div>` :
            `<i class="fas ${folderIcon} mr-3 text-lg text-slate-500"></i>`
        }
        
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <h4 class="text-sm font-semibold text-slate-800 truncate">${escapeHtml(note.title)}</h4>
                </div>
                <div class="opacity-0 group-hover:opacity-100 flex space-x-1 ml-3 transition-opacity duration-200">
                    ${note.type === 'folder' ? 
                        `<button class="p-2 hover:bg-blue-100 rounded-lg text-blue-600 transition-colors tooltip add-note-btn" data-tooltip="Thêm ghi chú" data-parent-id="${note.id}">
                            <i class="fas fa-plus text-xs"></i>
                        </button>` : ''
                    }
                    <button class="p-2 hover:bg-amber-100 rounded-lg text-amber-600 transition-colors tooltip edit-btn" data-tooltip="Sửa" data-note-id="${note.id}">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    <button class="p-2 hover:bg-red-100 rounded-lg text-red-600 transition-colors tooltip delete-btn" data-tooltip="Xóa" data-note-id="${note.id}">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    const actionButtons = itemDiv.querySelector('.opacity-0');
    if (actionButtons) {
        actionButtons.addEventListener('click', (e) => e.stopPropagation());
        
        const addBtn = actionButtons.querySelector('.add-note-btn');
        if (addBtn) {
            addBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                addNote(parseInt(addBtn.dataset.parentId));
            });
        }
        
        const editBtn = actionButtons.querySelector('.edit-btn');
        if (editBtn) {
            editBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                openRenameModal(parseInt(editBtn.dataset.noteId));
            });
        }
        
        const deleteBtn = actionButtons.querySelector('.delete-btn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                openDeleteModal(parseInt(deleteBtn.dataset.noteId));
            });
        }
    }
    
    div.appendChild(itemDiv);
    
    if (note.type === 'folder' && note.children && note.children.length > 0) {
        const childContainer = document.createElement('div');
        childContainer.className = isExpanded ? 'folder-expanded' : 'folder-collapsed';
        
        if (isExpanded) {
            note.children.forEach(child => childContainer.appendChild(renderNoteItem(child, level + 1)));
        }
        div.appendChild(childContainer);
    }
    
    return div;
}

function selectNote(id) {
    activeNoteId = id;
    const note = findNoteById(id);
    if (note && note.type === 'note') {
        showNoteEditor(note);
        renderNotes();
        if (window.innerWidth < 768) closeSidebar();
    }
}

function findNoteById(id) {
    function search(items) {
        for (const item of items) {
            if (item.id == id) return item;
            if (item.children) {
                const found = search(item.children);
                if (found) return found;
            }
        }
        return null;
    }
    return search(notes);
}

function showNoteEditor(note) {
    document.getElementById('welcomeScreen').style.display = 'none';
    document.getElementById('noteEditor').classList.remove('hidden');
    document.getElementById('noteTitle').textContent = note.title;
    quill.root.innerHTML = note.content || '';
    updateNoteInfo(note);
}

function updateNoteInfo(note) {
    const createdAt = new Date(note.created_at);
    let timeText = `Ngày tạo: ${formatDateTime(createdAt)}`;
    
    if (note.updated_at && note.updated_at !== note.created_at) {
        const updatedAt = new Date(note.updated_at);
        timeText += `  •  Cập nhật: ${formatDateTime(updatedAt)}`;
    }
    
    document.getElementById('noteTime').innerHTML = timeText;
}

function showWelcomeScreen() {
    document.getElementById('noteEditor').classList.add('hidden');
    document.getElementById('welcomeScreen').style.display = 'flex';
}

function formatDateTime(date) {
    const options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' };
    return date.toLocaleString('vi-VN', options);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function stripHtml(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

window.addEventListener('beforeunload', function(e) {
    if (activeNoteId && quill.root.innerHTML) {
        const note = findNoteById(activeNoteId);
        if (note && stripHtml(note.content || '') !== stripHtml(quill.root.innerHTML)) {
            e.preventDefault();
            e.returnValue = 'Bạn có thay đổi chưa được lưu. Bạn có muốn rời khỏi trang?';
        }
    }
});

/* === DRAG AND DROP FUNCTIONS === */
function handleDragStart(e) {
    const noteId = parseInt(e.currentTarget.dataset.noteId);
    draggedNote = findNoteById(noteId);
    e.currentTarget.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', e.currentTarget.innerHTML);
}

function handleDragEnd(e) {
    e.currentTarget.classList.remove('dragging');
    document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
    draggedNote = null;
}

function handleDragOver(e) {
    if (!draggedNote) return;
    
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    
    const targetElement = e.currentTarget;
    const targetId = parseInt(targetElement.dataset.noteId);
    const targetType = targetElement.dataset.noteType;
    
    // Không cho kéo vào chính nó
    if (draggedNote.id === targetId) return;
    
    // Không cho kéo folder vào note
    if (draggedNote.type === 'folder' && targetType === 'note') return;
    
    // Không cho kéo folder cha vào folder con của nó
    if (draggedNote.type === 'folder' && isChildFolder(targetId, draggedNote.id)) return;
    
    targetElement.classList.add('drag-over');
}

function handleDragLeave(e) {
    e.currentTarget.classList.remove('drag-over');
}

async function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const targetElement = e.currentTarget;
    targetElement.classList.remove('drag-over');
    
    if (!draggedNote) return;
    
    const targetId = parseInt(targetElement.dataset.noteId);
    const targetType = targetElement.dataset.noteType;
    
    if (draggedNote.id === targetId) return;
    
    let newParentId = null;
    
    // Nếu thả vào folder
    if (targetType === 'folder') {
        newParentId = targetId;
        expandedFolders.add(targetId); // Tự động mở folder
    }
    // Nếu thả vào note, lấy parent của note đó
    else {
        const targetNote = findNoteById(targetId);
        newParentId = targetNote.parent_id;
    }
    
    // Cập nhật parent_id
    try {
        await apiCall(`api.php?action=update&id=${draggedNote.id}`, {
            method: 'PUT',
            body: JSON.stringify({ 
                parent_id: newParentId,
                title: draggedNote.title
            })
        });
        
        await loadNotes();
        showAlert('Di chuyển thành công!');
    } catch (error) {
        console.error('Lỗi di chuyển:', error);
        showAlert('Không thể di chuyển!', 'error');
    }
}

function isChildFolder(childId, parentId) {
    const child = findNoteById(childId);
    if (!child || !child.parent_id) return false;
    if (child.parent_id === parentId) return true;
    return isChildFolder(child.parent_id, parentId);
}

// Initialize drop zone for notes list (to drop items to root level)
function initializeNotesListDropZone() {
    const notesList = document.getElementById('notesList');
    if (!notesList) return;
    
    notesList.addEventListener('dragover', function(e) {
        // Only handle if dragging over empty space (not over a note item)
        if (!e.target.closest('.note-item')) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        }
    });
    
    notesList.addEventListener('drop', async function(e) {
        // Only handle if dropping on empty space (not on a note item)
        if (!e.target.closest('.note-item') && draggedNote) {
            e.preventDefault();
            e.stopPropagation();
            
            // Move to root level (parent_id = null)
            try {
                await apiCall(`api.php?action=update&id=${draggedNote.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({ 
                        parent_id: null,
                        title: draggedNote.title
                    })
                });
                
                await loadNotes();
                showAlert('Đã di chuyển ra ngoài thành công!');
            } catch (error) {
                console.error('Lỗi di chuyển:', error);
                showAlert('Không thể di chuyển!', 'error');
            }
        }
    });
}

/* === RENAME MODAL FUNCTIONS === */
function openRenameModal(noteId) {
    renameNoteId = noteId;
    const note = findNoteById(noteId);
    if (!note) return;
    
    document.getElementById('renameInput').value = note.title;
    document.getElementById('renameModal').classList.add('open');
    setTimeout(() => {
        document.getElementById('renameInput').focus();
        document.getElementById('renameInput').select();
    }, 100);
}

function closeRenameModal() {
    document.getElementById('renameModal').classList.remove('open');
    renameNoteId = null;
}

async function confirmRename() {
    if (!renameNoteId) return;
    
    const newTitle = document.getElementById('renameInput').value.trim();
    if (!newTitle) {
        showAlert('Tiêu đề không được để trống!', 'error');
        return;
    }
    
    try {
        await updateTitle(renameNoteId, newTitle);
        closeRenameModal();
    } catch (error) {
        console.error('Lỗi đổi tên:', error);
    }
}

/* === DELETE MODAL FUNCTIONS === */
function openDeleteModal(noteId) {
    deleteNoteId = noteId;
    document.getElementById('deleteModal').classList.add('open');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('open');
    deleteNoteId = null;
}

async function confirmDelete() {
    if (!deleteNoteId) return;
    
    try {
        await deleteNote(deleteNoteId);
        closeDeleteModal();
    } catch (error) {
        console.error('Lỗi xóa:', error);
    }
}

/* === CREATE MODAL FUNCTIONS === */
let createModalType = null;
let createModalParentId = null;

function openCreateModal(type, parentId = null) {
    createModalType = type;
    createModalParentId = parentId;
    
    const modal = document.getElementById('createModal');
    const icon = document.getElementById('createModalIcon');
    const title = document.getElementById('createModalTitle');
    const subtitle = document.getElementById('createModalSubtitle');
    const input = document.getElementById('createModalInput');
    const confirmBtn = document.getElementById('createModalConfirm');
    
    if (type === 'note') {
        icon.className = 'create-modal-icon note-icon';
        icon.innerHTML = '<i class="fas fa-file-alt"></i>';
        title.textContent = 'Tạo ghi chú mới';
        subtitle.textContent = 'Đặt tên cho ghi chú của bạn';
        input.placeholder = 'Nhập tên ghi chú...';
        input.value = 'Ghi chú mới';
        confirmBtn.className = 'create-modal-btn create-modal-btn-confirm';
        confirmBtn.innerHTML = '<i class="fas fa-check"></i> Tạo ghi chú';
    } else {
        icon.className = 'create-modal-icon folder-icon';
        icon.innerHTML = '<i class="fas fa-folder"></i>';
        title.textContent = 'Tạo thư mục mới';
        subtitle.textContent = 'Đặt tên cho thư mục của bạn';
        input.placeholder = 'Nhập tên thư mục...';
        input.value = 'Thư mục mới';
        confirmBtn.className = 'create-modal-btn create-modal-btn-confirm folder';
        confirmBtn.innerHTML = '<i class="fas fa-check"></i> Tạo thư mục';
    }
    
    modal.classList.add('open');
    setTimeout(() => {
        input.focus();
        input.select();
    }, 100);
}

function closeCreateModal() {
    document.getElementById('createModal').classList.remove('open');
    createModalType = null;
    createModalParentId = null;
}

async function confirmCreate() {
    const title = document.getElementById('createModalInput').value.trim();
    
    if (!title) {
        showAlert('Tên không được để trống!', 'error');
        return;
    }
    
    try {
        if (createModalType === 'note') {
            const data = await apiCall('api.php?action=create', {
                method: 'POST',
                body: JSON.stringify({ 
                    title: title,
                    content: '',
                    parent_id: createModalParentId
                })
            });
            
            await loadNotes();
            
            if (createModalParentId) {
                expandedFolders.add(createModalParentId);
                renderNotes();
            }
            
            selectNote(data.id);
            showAlert('Tạo ghi chú thành công');
        } else {
            await apiCall('api.php?action=create', {
                method: 'POST',
                body: JSON.stringify({ 
                    title: title, 
                    type: 'folder' 
                })
            });
            await loadNotes();
            showAlert('Tạo thư mục thành công');
        }
        
        closeCreateModal();
    } catch (error) {
        console.error('Lỗi tạo:', error);
        showAlert('Không thể tạo!', 'error');
    }
}

/* === SHARE MODAL FUNCTIONS === */
async function openShareModal() {
    if (!activeNoteId) {
        showAlert('Vui lòng chọn một ghi chú để chia sẻ', 'error');
        return;
    }
    document.getElementById('shareModal').classList.add('open');
    document.getElementById('shareLinkContainer').style.display = 'none';
    document.getElementById('createShareLinkBtn').style.display = 'inline-block';
    document.getElementById('shareDescription').value = '';

    try {
        const existingLink = await apiCall(`api.php?action=get_share_link&note_id=${activeNoteId}`);
        if (existingLink && existingLink.id) {
            const shareUrl = `${window.location.protocol}//${window.location.host}/share.php?id=${existingLink.id}`;
            document.getElementById('shareLinkInput').value = shareUrl;
            document.getElementById('shareDescription').value = existingLink.description || '';
            document.getElementById('shareLinkContainer').style.display = 'flex';
            document.getElementById('createShareLinkBtn').style.display = 'none';
        }
    } catch (error) {
        console.log('No existing share link.');
    }
}

function closeShareModal() {
    document.getElementById('shareModal').classList.remove('open');
}

async function createShareLink() {
    const description = document.getElementById('shareDescription').value;
    const btn = document.getElementById('createShareLinkBtn');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<div class="progress-bordered"></div>';
    btn.disabled = true;

    try {
        const data = await apiCall('api.php?action=create_share_link', {
            method: 'POST',
            body: JSON.stringify({
                note_id: activeNoteId,
                description: description
            })
        });

        const shareUrl = `${window.location.protocol}//${window.location.host}/share.php?id=${data.id}`;
        document.getElementById('shareLinkInput').value = shareUrl;
        document.getElementById('shareLinkContainer').style.display = 'flex';
        btn.style.display = 'none';
        showAlert('Tạo liên kết chia sẻ thành công!');
    } catch (error) {
        console.error('Lỗi tạo link chia sẻ:', error);
        showAlert('Lỗi: Không thể tạo liên kết chia sẻ.', 'error');
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    }
}

function copyShareLink() {
    const linkInput = document.getElementById('shareLinkInput');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999);
    document.execCommand('copy');
    showAlert('Đã sao chép liên kết!');
}

/* === DARK MODE FUNCTIONALITY === */
// Initialize dark mode from localStorage
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }
});

// Toggle dark mode function (already exists but ensure it saves to localStorage)
function toggleDarkMode() {
    const body = document.body;
    const icon = document.getElementById('darkModeIcon');
    const text = document.getElementById('darkModeText');
    
    body.classList.toggle('dark-mode');
    isDarkMode = body.classList.contains('dark-mode');
    
    if (isDarkMode) {
        localStorage.setItem('theme', 'dark');
        if (icon) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }
        if (text) {
            text.textContent = 'Sáng';
        }
    } else {
        localStorage.setItem('theme', 'light');
        if (icon) {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
        if (text) {
            text.textContent = 'Tối';
        }
    }
}

// Tools Dropdown Toggle
document.addEventListener('DOMContentLoaded', function() {
    const toolsBtn = document.querySelector('.tools-btn');
    const toolsMenu = document.querySelector('.tools-menu');
    const chevron = document.querySelector('.tools-chevron');
    
    if (toolsBtn && toolsMenu) {
        toolsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toolsMenu.classList.toggle('hidden');
            if (chevron) {
                chevron.style.transform = toolsMenu.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!toolsBtn.contains(e.target) && !toolsMenu.contains(e.target)) {
                toolsMenu.classList.add('hidden');
                if (chevron) {
                    chevron.style.transform = 'rotate(0deg)';
                }
            }
        });
    }
});
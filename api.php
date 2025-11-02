<?php
// Ngăn chặn output trước JSON
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'database.php';
require_once 'config.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Chưa đăng nhập'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    switch ($method) {
        case 'GET':
            if ($action === 'notes') {
                getNotes($conn, $userId);
            } elseif ($action === 'note' && isset($_GET['id'])) {
                getNote($conn, $_GET['id'], $userId);
            } elseif ($action === 'get_share_link' && isset($_GET['note_id'])) {
                getShareLink($conn, $_GET['note_id'], $userId);
            } elseif ($action === 'shares') {
                getShares($conn, $userId);
            } elseif ($action === 'note_content' && isset($_GET['id'])) {
                getNoteContent($conn, $_GET['id'], $userId, $_GET['chunk_start'] ?? 0, $_GET['chunk_limit'] ?? 1);
            } else {
                throw new Exception('Invalid GET action');
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if ($action === 'create') {
                createNote($conn, $data, $userId);
            } elseif ($action === 'create_share_link') {
                createShareLink($conn, $data, $userId);
            } elseif ($action === 'move_note') {
                moveNote($conn, $data, $userId);
            } else {
                throw new Exception('Invalid POST action');
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if ($action === 'update' && isset($_GET['id'])) {
                updateNote($conn, $_GET['id'], $data, $userId);
            } elseif ($action === 'toggle' && isset($_GET['id'])) {
                toggleFolder($conn, $_GET['id'], $userId);
            } else {
                throw new Exception('Invalid PUT action');
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete' && isset($_GET['id'])) {
                deleteNote($conn, $_GET['id'], $userId);
            } elseif ($action === 'delete_share' && isset($_GET['id'])) {
                deleteShare($conn, $_GET['id'], $userId);
            } else {
                throw new Exception('Invalid DELETE action');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function getNotes($conn, $userId) {
    $sql = "SELECT id, title, content, type, parent_id, expanded, views,
                   DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at,
                   DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') as updated_at
            FROM notes 
            WHERE user_id = ?
            ORDER BY type DESC, created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $notes = $stmt->fetchAll();
    
    // Tổ chức dạng tree
    $result = [];
    $notesById = [];
    
    foreach ($notes as $note) {
        $note['children'] = [];
        $notesById[$note['id']] = $note;
    }
    
    foreach ($notesById as $note) {
        if ($note['parent_id']) {
            if (isset($notesById[$note['parent_id']])) {
                $notesById[$note['parent_id']]['children'][] = &$notesById[$note['id']];
            }
        } else {
            $result[] = &$notesById[$note['id']];
        }
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

function getNote($conn, $id, $userId) {
    $sql = "SELECT id, title, content, type, parent_id, expanded, views,
                   DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at,
                   DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') as updated_at
            FROM notes WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id, $userId]);
    $note = $stmt->fetch();
    
    if (!$note) {
        throw new Exception('Note not found');
    }
    
    // Tăng views khi xem note
    $updateViews = $conn->prepare("UPDATE notes SET views = views + 1 WHERE id = ?");
    $updateViews->execute([$id]);
    $note['views']++;
    
    echo json_encode($note, JSON_UNESCAPED_UNICODE);
}

function createNote($conn, $data, $userId) {
    $title = $data['title'] ?? 'Ghi chú mới';
    $content = $data['content'] ?? '';
    $type = $data['type'] ?? 'note';
    $parent_id = $data['parent_id'] ?? null;
    
    // Kiểm tra parent_id thuộc về user hiện tại
    if ($parent_id) {
        $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ? AND type = 'folder'");
        $stmt->execute([$parent_id, $userId]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid parent folder');
        }
    }
    
    $sql = "INSERT INTO notes (title, content, type, parent_id, user_id) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$title, $content, $type, $parent_id, $userId]);
    
    $newId = $conn->lastInsertId();
    getNote($conn, $newId, $userId);
}

function updateNote($conn, $id, $data, $userId) {
    // Kiểm tra quyền sở hữu
    $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Note not found or access denied');
    }
    
    $updates = [];
    $params = [];
    
    if (isset($data['title'])) {
        $updates[] = "title = ?";
        $params[] = $data['title'];
    }
    
    if (isset($data['content'])) {
        $updates[] = "content = ?";
        $params[] = $data['content'];
    }
    
    // HỖ TRỢ CÂP NHẬT PARENT_ID - QUAN TRỌNG CHO DRAG & DROP
    if (array_key_exists('parent_id', $data)) {
        // Cho phép parent_id = null (kéo ra ngoài root)
        if ($data['parent_id'] === null) {
            $updates[] = "parent_id = NULL";
        } else {
            // Kiểm tra parent_id có hợp lệ không
            $checkStmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ? AND type = 'folder'");
            $checkStmt->execute([$data['parent_id'], $userId]);
            if ($checkStmt->fetch()) {
                $updates[] = "parent_id = ?";
                $params[] = $data['parent_id'];
            } else {
                throw new Exception('Invalid parent folder');
            }
        }
    }
    
    if (empty($updates)) {
        throw new Exception('No data to update');
    }
    
    $updates[] = "updated_at = NOW()";
    
    $sql = "UPDATE notes SET " . implode(', ', $updates) . " WHERE id = ?";
    $params[] = $id;
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute($params);
    
    if (!$result) {
        throw new Exception('Update failed');
    }
    
    echo json_encode(['success' => true, 'message' => 'Cập nhật thành công'], JSON_UNESCAPED_UNICODE);
}

function toggleFolder($conn, $id, $userId) {
    // Kiểm tra quyền sở hữu
    $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ? AND type = 'folder'");
    $stmt->execute([$id, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Folder not found or access denied');
    }
    
    $sql = "UPDATE notes SET expanded = NOT expanded WHERE id = ? AND type = 'folder'";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$id]);
    
    if (!$result) {
        throw new Exception('Toggle failed');
    }
    
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
}

function deleteNote($conn, $id, $userId) {
    // Kiểm tra quyền sở hữu
    $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Note not found or access denied');
    }
    
    // Xóa tất cả children trước (CASCADE sẽ tự động xóa)
    $sql = "DELETE FROM notes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$id]);
    
    if (!$result) {
        throw new Exception('Delete failed');
    }
    
    echo json_encode(['success' => true, 'message' => 'Xóa thành công'], JSON_UNESCAPED_UNICODE);
}

function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

function createShareLink($conn, $data, $userId) {
    $noteId = $data['note_id'] ?? null;
    $description = $data['description'] ?? '';

    if (!$noteId) {
        throw new Exception('Note ID is required');
    }

    // Check ownership of the note
    $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$noteId, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Note not found or access denied');
    }

    // Check if link already exists for this note
    $stmt = $conn->prepare("SELECT id FROM shares WHERE note_id = ?");
    $stmt->execute([$noteId]);
    $existing = $stmt->fetch();
    if ($existing) {
        echo json_encode(['id' => $existing['id']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $shareId = generateRandomString(10);
    $sql = "INSERT INTO shares (id, note_id, user_id, description) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$shareId, $noteId, $userId, $description]);

    echo json_encode(['id' => $shareId], JSON_UNESCAPED_UNICODE);
}

function getShareLink($conn, $noteId, $userId) {
    // Check ownership
    $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$noteId, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Note not found or access denied');
    }

    $stmt = $conn->prepare("SELECT id, description FROM shares WHERE note_id = ?");
    $stmt->execute([$noteId]);
    $link = $stmt->fetch();
    
    if (!$link) {
        throw new Exception('No share link found for this note');
    }

    echo json_encode($link, JSON_UNESCAPED_UNICODE);
}

function getShares($conn, $userId) {
    $sql = "SELECT s.id, s.note_id, s.description, s.views, 
                   DATE_FORMAT(s.created_at, '%Y-%m-%d %H:%i:%s') as created_at,
                   n.title as note_title
            FROM shares s 
            JOIN notes n ON s.note_id = n.id 
            WHERE n.user_id = ? 
            ORDER BY s.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $shares = $stmt->fetchAll();
    
    echo json_encode($shares, JSON_UNESCAPED_UNICODE);
}

function deleteShare($conn, $shareId, $userId) {
    // Kiểm tra quyền sở hữu thông qua note
    $stmt = $conn->prepare("SELECT s.id FROM shares s JOIN notes n ON s.note_id = n.id WHERE s.id = ? AND n.user_id = ?");
    $stmt->execute([$shareId, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Share not found or access denied');
    }
    
    $sql = "DELETE FROM shares WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$shareId]);
    
    if (!$result) {
        throw new Exception('Delete share failed');
    }
    
    echo json_encode(['success' => true, 'message' => 'Xóa chia sẻ thành công'], JSON_UNESCAPED_UNICODE);
}

function getNoteContent($conn, $noteId, $userId, $chunkStart = 0, $chunkLimit = 1) {
    // Kiểm tra quyền sở hữu của note
    $stmt = $conn->prepare("SELECT id, content_length FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$noteId, $userId]);
    $note = $stmt->fetch();
    
    if (!$note) {
        throw new Exception('Note not found or access denied');
    }
    
    // Lấy các chunk nội dung
    $sql = "SELECT chunk_index, content FROM content_chunks 
            WHERE note_id = ? AND chunk_index >= ? 
            ORDER BY chunk_index 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$noteId, $chunkStart, $chunkLimit]);
    $chunks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response = [
        'note_id' => $noteId,
        'chunk_start' => $chunkStart,
        'chunk_count' => count($chunks),
        'total_chunks' => ceil($note['content_length'] / (1024 * 1024)), // Giả sử mỗi chunk 1MB
        'content' => implode('', array_column($chunks, 'content'))
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

function moveNote($conn, $data, $userId) {
    $noteId = $data['note_id'] ?? null;
    $targetFolderId = $data['target_folder_id'] ?? null;
    
    if (!$noteId) {
        throw new Exception('Note ID is required');
    }
    
    // Kiểm tra quyền sở hữu của note
    $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$noteId, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Note not found or access denied');
    }
    
    // Kiểm tra target folder tồn tại và thuộc sở hữu của user
    if ($targetFolderId) {
        $stmt = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ? AND type = 'folder'");
        $stmt->execute([$targetFolderId, $userId]);
        if (!$stmt->fetch()) {
            throw new Exception('Target folder not found or invalid');
        }
    }
    
    // Di chuyển note
    $sql = "UPDATE notes SET parent_id = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$targetFolderId, $noteId]);
    
    if (!$result) {
        throw new Exception('Move note failed');
    }
    
    echo json_encode(['success' => true, 'message' => 'Di chuyển ghi chú thành công'], JSON_UNESCAPED_UNICODE);
}
?>
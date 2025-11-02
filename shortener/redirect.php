<?php
// File redirect cho short links
require_once '../database.php';

$shortId = $_GET['id'] ?? '';

if (empty($shortId)) {
    header('Location: ../index.php');
    exit;
}

try {
    $conn = getDB();
    
    // Lấy URL gốc
    $stmt = $conn->prepare("SELECT original_url FROM short_links WHERE id = ? OR custom_alias = ?");
    $stmt->execute([$shortId, $shortId]);
    $link = $stmt->fetch();
    
    if ($link) {
        // Tăng số lượt click
        $stmt = $conn->prepare("UPDATE short_links SET clicks = clicks + 1 WHERE id = ? OR custom_alias = ?");
        $stmt->execute([$shortId, $shortId]);
        
        // Redirect
        header('Location: ' . $link['original_url']);
        exit;
    } else {
        header('Location: ../expired.html');
        exit;
    }
} catch (Exception $e) {
    header('Location: ../expired.html');
    exit;
}
?>

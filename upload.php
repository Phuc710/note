<?php
/**
 * UPLOAD API - Upload và nén ảnh tự động
 * POST /upload.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Kiểm tra file upload
if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['image'];

// Validate upload error
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload error: ' . $file['error']]);
    exit;
}

try {
    // 1. Dò MIME thực bằng finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes)) {
        throw new Exception('Invalid image type: ' . $mimeType);
    }
    
    // 2. Lấy kích thước ảnh
    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        throw new Exception('Cannot read image dimensions');
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $originalSize = filesize($file['tmp_name']);
    
    // 3. Tạo thư mục uploads nếu chưa có
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Cannot create upload directory');
        }
    }
    
    // 4. Xử lý và nén ảnh
    $processedImage = processAndCompressImage($file['tmp_name'], $mimeType, $width, $height);
    if (!$processedImage) {
        throw new Exception('Cannot process image');
    }
    
    // 5. Tạo tên file unique (ngắn gọn)
    // Chuyển PNG sang JPG để giảm dung lượng (trừ khi có transparency)
    $hasTransparency = false;
    if ($mimeType === 'image/png') {
        $hasTransparency = checkPngTransparency($file['tmp_name']);
    }
    
    // Nếu PNG không có transparency, chuyển sang JPG
    if ($mimeType === 'image/png' && !$hasTransparency) {
        $mimeType = 'image/jpeg';
    }
    
    $extension = getExtensionFromMime($mimeType);
    $filename = uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // 6. Lưu ảnh đã nén
    $saved = saveImage($processedImage['resource'], $filepath, $mimeType);
    imagedestroy($processedImage['resource']);
    
    if (!$saved) {
        throw new Exception('Cannot save image to disk');
    }
    
    $compressedSize = filesize($filepath);
    $compressionRatio = round((1 - $compressedSize / $originalSize) * 100, 1);
    
    // 7. Tạo URL công khai
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $domain = $_SERVER['HTTP_HOST'];
    $basePath = dirname($_SERVER['SCRIPT_NAME']);
    $publicUrl = $protocol . $domain . $basePath . '/uploads/' . $filename;
    
    // 8. Lưu thông tin vào database
    $imageId = generateImageId();
    $userId = $_SESSION['user_id'] ?? null; // Lấy user_id nếu đã đăng nhập
    
    try {
        $conn = getDB();
        $sql = "INSERT INTO uploaded_images (id, filename, original_name, file_size, mime_type, user_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $imageId,
            $filename,
            $file['name'],
            $compressedSize,
            $mimeType,
            $userId
        ]);
    } catch (Exception $dbError) {
        // Nếu lỗi database, vẫn trả về link ảnh (không block upload)
        error_log("Database error: " . $dbError->getMessage());
    }
    
    // 9. Trả JSON với tất cả link formats
    echo json_encode([
        'success' => true,
        'id' => $imageId,
        'filename' => $filename,
        'original_size' => $originalSize,
        'compressed_size' => $compressedSize,
        'compression_ratio' => $compressionRatio,
        'width' => $processedImage['width'],
        'height' => $processedImage['height'],
        'links' => [
            'direct' => $publicUrl,
            'view' => $publicUrl,
            'markdown' => "![image]({$publicUrl})",
            'html' => "<img src=\"{$publicUrl}\" alt=\"image\">",
            'bbcode' => "[img]{$publicUrl}[/img]"
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Xử lý và nén ảnh: resize nếu quá lớn, giữ tỷ lệ
 */
function processAndCompressImage($sourcePath, $mimeType, $width, $height) {
    // Đọc ảnh
    switch ($mimeType) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$image) {
        return false;
    }
    
    // Resize nếu quá lớn (giảm dung lượng)
    $maxWidth = 1920;
    $maxHeight = 1920;
    
    if ($width > $maxWidth || $height > $maxHeight) {
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)round($width * $ratio);
        $newHeight = (int)round($height * $ratio);
        
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Giữ transparency cho PNG/GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        
        return ['resource' => $resized, 'width' => $newWidth, 'height' => $newHeight];
    }
    
    return ['resource' => $image, 'width' => $width, 'height' => $height];
}

/**
 * Lưu ảnh ra disk với compression cao
 */
function saveImage($imageResource, $targetPath, $mimeType) {
    switch ($mimeType) {
        case 'image/jpeg':
            return imagejpeg($imageResource, $targetPath, 85); // Giảm chất lượng xuống 85%
        case 'image/png':
            imagealphablending($imageResource, false);
            imagesavealpha($imageResource, true);
            return imagepng($imageResource, $targetPath, 7); // Nén level 7
        case 'image/gif':
            return imagegif($imageResource, $targetPath);
        case 'image/webp':
            return imagewebp($imageResource, $targetPath, 85); // WebP quality 85%
    }
    return false;
}

/**
 * Lấy extension từ MIME type
 */
function getExtensionFromMime($mimeType) {
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    return $map[$mimeType] ?? 'jpg';
}

/**
 * Kiểm tra PNG có transparency không
 */
function checkPngTransparency($filePath) {
    $image = @imagecreatefrompng($filePath);
    if (!$image) {
        return false;
    }
    
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Check một số pixel ngẫu nhiên
    $sampleSize = min(100, $width * $height);
    for ($i = 0; $i < $sampleSize; $i++) {
        $x = rand(0, $width - 1);
        $y = rand(0, $height - 1);
        $rgba = imagecolorat($image, $x, $y);
        $alpha = ($rgba & 0x7F000000) >> 24;
        
        if ($alpha > 0) {
            imagedestroy($image);
            return true; // Có transparency
        }
    }
    
    imagedestroy($image);
    return false; // Không có transparency
}

/**
 * Tạo ID ngẫu nhiên cho ảnh (10 ký tự)
 */
function generateImageId($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
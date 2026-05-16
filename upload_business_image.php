<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Create uploads directory
$uploadDir = '../backend/uploads/images/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!isset($_POST['business_id']) || empty($_POST['business_id'])) {
    echo json_encode(['success' => false, 'message' => 'Business ID required']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please select an image']);
    exit;
}

$business_id = $_POST['business_id'];
$is_primary = isset($_POST['is_primary']) ? $_POST['is_primary'] === 'true' : false;
$image_title = $_POST['image_title'] ?? '';

$file = $_FILES['image'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid image type. Allowed: JPG, PNG, GIF, WEBP']);
    exit;
}

// Compress and resize image
$imagePath = $uploadDir . 'business_' . $business_id . '_' . time() . '_' . uniqid() . '.' . $fileExtension;

// Process image based on type
switch($fileExtension) {
    case 'jpg':
    case 'jpeg':
        $src = imagecreatefromjpeg($file['tmp_name']);
        break;
    case 'png':
        $src = imagecreatefrompng($file['tmp_name']);
        break;
    case 'gif':
        $src = imagecreatefromgif($file['tmp_name']);
        break;
    default:
        $src = imagecreatefromjpeg($file['tmp_name']);
}

// Get dimensions
$width = imagesx($src);
$height = imagesy($src);

// Resize if too large (max 1200px)
$maxWidth = 1200;
if ($width > $maxWidth) {
    $newWidth = $maxWidth;
    $newHeight = ($height / $width) * $newWidth;
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($resized, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($src);
    $src = $resized;
}

// Save image
if ($fileExtension === 'png') {
    imagepng($src, $imagePath, 8);
} else {
    imagejpeg($src, $imagePath, 85);
}
imagedestroy($src);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // If this is primary, remove primary flag from other images
    if ($is_primary) {
        $removeQuery = "UPDATE business_images SET is_primary = 0 WHERE business_id = :business_id";
        $removeStmt = $db->prepare($removeQuery);
        $removeStmt->execute([':business_id' => $business_id]);
    }
    
    // Insert image record
    $query = "INSERT INTO business_images (business_id, image_url, image_title, is_primary, uploaded_by) 
              VALUES (:business_id, :image_url, :image_title, :is_primary, :uploaded_by)";
    $stmt = $db->prepare($query);
    
    $imageUrl = 'uploads/images/' . basename($imagePath);
    
    if ($stmt->execute([
        ':business_id' => $business_id,
        ':image_url' => $imageUrl,
        ':image_title' => $image_title,
        ':is_primary' => $is_primary ? 1 : 0,
        ':uploaded_by' => $_SESSION['user_id']
    ])) {
        echo json_encode([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'image_id' => $db->lastInsertId(),
            'image_url' => $imageUrl
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save image record']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
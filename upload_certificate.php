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

$uploadDir = '../backend/uploads/certificates/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!isset($_POST['business_id']) || empty($_POST['business_id'])) {
    echo json_encode(['success' => false, 'message' => 'Business ID required']);
    exit;
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please select a file to upload']);
    exit;
}

$file = $_FILES['document'];
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, JPG, PNG']);
    exit;
}

if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Max 10MB']);
    exit;
}

$newFilename = 'cert_' . $_POST['business_id'] . '_' . time() . '_' . uniqid() . '.' . $ext;
$uploadPath = $uploadDir . $newFilename;

if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "INSERT INTO certification_documents 
                  (business_id, document_title, document_type, file_path, file_name, 
                   file_size, mime_type, certificate_number, issue_date, expiry_date, 
                   certification_body_id, verification_status, uploaded_by, uploaded_at) 
                  VALUES 
                  (:business_id, :document_title, 'certificate', :file_path, :file_name,
                   :file_size, :mime_type, :certificate_number, :issue_date, :expiry_date,
                   :certification_body_id, 'pending', :uploaded_by, NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':business_id' => $_POST['business_id'],
            ':document_title' => $_POST['document_title'] ?? 'Halal Certificate',
            ':file_path' => 'uploads/certificates/' . $newFilename,
            ':file_name' => $file['name'],
            ':file_size' => $file['size'],
            ':mime_type' => $file['type'],
            ':certificate_number' => $_POST['certificate_number'] ?? '',
            ':issue_date' => $_POST['issue_date'] ?? null,
            ':expiry_date' => $_POST['expiry_date'] ?? null,
            ':certification_body_id' => $_POST['certification_body_id'] ?? null,
            ':uploaded_by' => $_SESSION['user_id']
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Document uploaded successfully! Waiting for verification.',
            'document_id' => $db->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}
?>
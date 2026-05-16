<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Check if admin is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login first.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Use POST.']);
    exit;
}

// Create uploads directory if not exists
$uploadDir = '../backend/uploads/certificates/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Validate required fields
if (!isset($_POST['business_id']) || empty($_POST['business_id'])) {
    echo json_encode(['success' => false, 'message' => 'Business ID is required']);
    exit;
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please select a file to upload']);
    exit;
}

$business_id = $_POST['business_id'];
$document_title = $_POST['document_title'] ?? 'Halal Certificate';
$certificate_number = $_POST['certificate_number'] ?? '';
$issue_date = $_POST['issue_date'] ?? null;
$expiry_date = $_POST['expiry_date'] ?? null;
$certification_body_id = $_POST['certification_body_id'] ?? null;

// Handle file upload
$file = $_FILES['document'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, JPG, PNG, DOC']);
    exit;
}

// Check file size (max 10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Max 10MB']);
    exit;
}

// Generate unique filename
$newFilename = 'cert_' . $business_id . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
$uploadPath = $uploadDir . $newFilename;

if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Insert document record
        $query = "INSERT INTO certification_documents 
                  (business_id, document_title, document_type, file_path, file_name, 
                   file_size, mime_type, certificate_number, certification_body_id, 
                   issue_date, expiry_date, verification_status, uploaded_by) 
                  VALUES 
                  (:business_id, :document_title, :document_type, :file_path, :file_name,
                   :file_size, :mime_type, :certificate_number, :certification_body_id,
                   :issue_date, :expiry_date, 'pending', :uploaded_by)";
        
        $stmt = $db->prepare($query);
        
        $document_type = $_POST['document_type'] ?? 'certificate';
        
        $result = $stmt->execute([
            ':business_id' => $business_id,
            ':document_title' => $document_title,
            ':document_type' => $document_type,
            ':file_path' => 'uploads/certificates/' . $newFilename,
            ':file_name' => $file['name'],
            ':file_size' => $file['size'],
            ':mime_type' => $file['type'],
            ':certificate_number' => $certificate_number,
            ':certification_body_id' => $certification_body_id,
            ':issue_date' => $issue_date,
            ':expiry_date' => $expiry_date,
            ':uploaded_by' => $_SESSION['user_id']
        ]);
        
        if ($result) {
            // Log activity
            $logQuery = "INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) 
                         VALUES (:user_id, :action, :table_name, :record_id, :ip)";
            $logStmt = $db->prepare($logQuery);
            $logStmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':action' => 'Uploaded certificate',
                ':table_name' => 'certification_documents',
                ':record_id' => $db->lastInsertId(),
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Document uploaded successfully! Waiting for verification.',
                'document_id' => $db->lastInsertId(),
                'file_path' => 'uploads/certificates/' . $newFilename
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save document record']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}
?>
<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin only']);
    exit;
}

// Create upload directory if not exists
$uploadDir = '../backend/uploads/documents/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Validate input
if (!isset($_POST['business_id']) || empty($_POST['business_id'])) {
    echo json_encode(['success' => false, 'message' => 'Business ID is required']);
    exit;
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'Please select a file to upload';
    if (isset($_FILES['document']['error'])) {
        switch ($_FILES['document']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $errorMessage = 'File too large (server limit)';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = 'File too large (form limit)';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = 'File only partially uploaded';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = 'No file selected';
                break;
        }
    }
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit;
}

$business_id = $_POST['business_id'];
$document_title = $_POST['document_title'] ?? 'Halal Certificate';
$document_type = $_POST['document_type'] ?? 'certificate';
$certificate_number = $_POST['certificate_number'] ?? '';
$issue_date = $_POST['issue_date'] ?? null;
$expiry_date = $_POST['expiry_date'] ?? null;

// Validate file
$file = $_FILES['document'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, JPG, PNG, DOC']);
    exit;
}

if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large. Max 10MB']);
    exit;
}

// Generate unique filename
$newFilename = 'admin_doc_' . $business_id . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
$uploadPath = $uploadDir . $newFilename;

if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        
        $query = "INSERT INTO admin_uploaded_documents 
                  (business_id, document_title, document_type, file_path, file_name, 
                   certificate_number, issue_date, expiry_date, verification_status, 
                   submitted_by, created_at) 
                  VALUES 
                  (:business_id, :document_title, :document_type, :file_path, :file_name,
                   :certificate_number, :issue_date, :expiry_date, 'pending', 
                   :submitted_by, NOW())";
        
        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            ':business_id' => $business_id,
            ':document_title' => $document_title,
            ':document_type' => $document_type,
            ':file_path' => 'uploads/documents/' . $newFilename,
            ':file_name' => $file['name'],
            ':certificate_number' => $certificate_number,
            ':issue_date' => $issue_date,
            ':expiry_date' => $expiry_date,
            ':submitted_by' => $_SESSION['user_id']
        ]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Document uploaded successfully! Waiting for JHA approval.',
                'document_id' => $db->lastInsertId()
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
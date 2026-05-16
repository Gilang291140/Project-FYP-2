<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Check if user is logged in and is JHA verifier
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in', 'data' => []]);
    exit;
}

if ($_SESSION['role'] !== 'verifier') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - JHA Verifier only', 'data' => []]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed', 'data' => []]);
        exit;
    }
    
    // Query untuk mendapatkan dokumen dari Admin yang pending
    $query = "SELECT 
                d.id, 
                d.business_id,
                d.document_title, 
                d.document_type, 
                d.file_path, 
                d.file_name, 
                d.certificate_number, 
                d.issue_date, 
                d.expiry_date, 
                d.verification_status, 
                d.submitted_by,
                d.created_at,
                b.business_name,
                b.address,
                b.city,
                b.phone,
                u.full_name as submitted_by_name
              FROM admin_uploaded_documents d
              LEFT JOIN halal_businesses b ON d.business_id = b.id
              LEFT JOIN users u ON d.submitted_by = u.id
              WHERE d.verification_status = 'pending'
              ORDER BY d.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $documents,
        'total' => count($documents)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'data' => []
    ]);
}
?>
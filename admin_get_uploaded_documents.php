<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'User not logged in',
        'data' => []
    ]);
    exit;
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin') {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - Admin only',
        'data' => []
    ]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database connection failed',
            'data' => []
        ]);
        exit;
    }
    
    // Query to get documents uploaded by current admin
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
                d.rejection_reason,
                d.submitted_by,
                d.verified_by,
                d.verified_at,
                d.created_at,
                COALESCE(b.business_name, 'Unknown Business') as business_name,
                COALESCE(u.full_name, 'Unknown') as submitted_by_name,
                COALESCE(v.full_name, '') as verified_by_name
              FROM admin_uploaded_documents d
              LEFT JOIN halal_businesses b ON d.business_id = b.id
              LEFT JOIN users u ON d.submitted_by = u.id
              LEFT JOIN users v ON d.verified_by = v.id
              WHERE d.submitted_by = :user_id
              ORDER BY d.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no documents, return empty array
    if (!$documents) {
        $documents = [];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $documents,
        'total' => count($documents)
    ]);
    
} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'data' => []
    ]);
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'data' => []
    ]);
}
?>
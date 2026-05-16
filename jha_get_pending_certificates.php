<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'verifier') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => []]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Hanya ambil data dengan cert_status = 'pending' (yang dikirim Admin)
    $query = "SELECT b.id, b.business_name, b.category, b.address, b.city, 
                     b.cert_number, b.cert_issue_date, b.cert_expiry_date, b.cert_issued_by,
                     b.cert_status, b.created_at, 
                     u.full_name as submitted_by
              FROM halal_businesses b
              LEFT JOIN users u ON b.created_by = u.id
              WHERE b.cert_status = 'pending' 
              AND b.cert_number IS NOT NULL
              AND b.cert_issue_date IS NOT NULL
              AND b.cert_expiry_date IS NOT NULL
              AND b.cert_issued_by IS NOT NULL
              ORDER BY b.created_at ASC";
    
    $stmt = $db->query($query);
    $certificates = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $certificates]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}
?>
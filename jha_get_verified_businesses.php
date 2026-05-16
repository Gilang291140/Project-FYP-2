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
    
    $query = "SELECT b.id, b.business_name, b.category, b.address, b.city, 
                     b.cert_number, b.cert_issue_date, b.cert_expiry_date, b.cert_issued_by,
                     b.cert_status, b.halal_status
              FROM halal_businesses b
              WHERE b.halal_status = 'certified' 
              AND b.cert_status = 'verified'
              ORDER BY b.cert_verified_at DESC";
    
    $stmt = $db->query($query);
    $businesses = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $businesses]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}
?>
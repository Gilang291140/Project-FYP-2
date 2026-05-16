<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => []]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // SEMUA data tetap muncul di Manage Restaurants (tidak difilter)
    $query = "SELECT b.*,
              CASE 
                  WHEN b.cert_status = 'pending' AND b.cert_number IS NOT NULL THEN 'pending_cert'
                  WHEN b.cert_status = 'none' OR b.cert_number IS NULL THEN 'no_cert'
                  ELSE b.halal_status
              END as display_status
              FROM halal_businesses b
              ORDER BY b.created_at DESC";
    
    $stmt = $db->query($query);
    $businesses = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $businesses,
        'total' => count($businesses)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}
?>
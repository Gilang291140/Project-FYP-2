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
    
    // Get restaurants that are pending or have recent updates
    $query = "SELECT b.*, 
              (SELECT COUNT(*) FROM admin_uploaded_documents WHERE business_id = b.id AND verification_status = 'pending') as pending_certs,
              a.action, a.created_at as notification_date, u.full_name as updated_by
              FROM halal_businesses b
              LEFT JOIN activity_logs a ON a.record_id = b.id AND a.table_name = 'halal_businesses' 
                 AND a.action IN ('NEW_RESTAURANT_PENDING', 'RESTAURANT_UPDATED_PENDING')
              LEFT JOIN users u ON a.user_id = u.id
              WHERE b.halal_status IN ('pending', 'verified')
              AND (b.notification_sent = 0 OR b.notification_date > DATE_SUB(NOW(), INTERVAL 7 DAY))
              ORDER BY b.notification_date DESC, b.created_at DESC
              LIMIT 20";
    
    $stmt = $db->query($query);
    $restaurants = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $restaurants,
        'total' => count($restaurants)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}
?>
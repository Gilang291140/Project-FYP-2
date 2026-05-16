<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get city statistics with more details
    $query = "SELECT 
                city, 
                COUNT(*) as total,
                SUM(CASE WHEN halal_status = 'certified' THEN 1 ELSE 0 END) as certified,
                SUM(CASE WHEN halal_status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN halal_status = 'pending' THEN 1 ELSE 0 END) as pending
              FROM halal_businesses 
              WHERE city IS NOT NULL AND city != '' 
              GROUP BY city 
              ORDER BY total DESC";
    
    $stmt = $db->query($query);
    $cities = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $cities,
        'total_cities' => count($cities)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
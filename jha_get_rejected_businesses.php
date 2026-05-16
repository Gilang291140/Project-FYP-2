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
    
    $query = "SELECT * FROM halal_businesses 
              WHERE halal_status = 'rejected' 
              ORDER BY updated_at DESC";
    
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
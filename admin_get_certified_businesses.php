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
    
    // SEMUA data tetap muncul di Manage Restaurants (tidak difilter berdasarkan is_published)
    $query = "SELECT b.*
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
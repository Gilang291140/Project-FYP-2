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
    
    $query = "SELECT * FROM import_history ORDER BY import_date DESC LIMIT 10";
    $stmt = $db->query($query);
    $history = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $history
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}
?>
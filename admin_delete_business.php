<?php
session_start();
require_once '../backend/config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Business ID required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "DELETE FROM halal_businesses WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $_GET['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Business deleted successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
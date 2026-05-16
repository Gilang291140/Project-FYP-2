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
    
    // Get pending certificates (status = 'pending')
    $query = "SELECT cd.*, b.business_name, b.address, b.city 
              FROM certification_documents cd
              LEFT JOIN halal_businesses b ON cd.business_id = b.id
              WHERE cd.verification_status = 'pending'
              ORDER BY cd.uploaded_at DESC";
    
    $stmt = $db->query($query);
    $documents = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $documents]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}
?>
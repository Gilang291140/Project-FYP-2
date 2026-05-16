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
    
    // Get all certificates (verified, pending, certified, expired)
    $query = "SELECT cd.*, b.business_name, u.full_name as verifier_name 
              FROM certification_documents cd
              LEFT JOIN halal_businesses b ON cd.business_id = b.id
              LEFT JOIN users u ON cd.verified_by = u.id
              ORDER BY cd.uploaded_at DESC";
    
    $stmt = $db->query($query);
    $certificates = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $certificates]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}
?>
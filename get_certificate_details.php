<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Certificate ID required']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT cd.*, b.business_name 
              FROM certification_documents cd
              LEFT JOIN halal_businesses b ON cd.business_id = b.id
              WHERE cd.id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $id]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($certificate) {
        echo json_encode(['success' => true, 'data' => $certificate]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Certificate not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
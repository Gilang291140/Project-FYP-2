<?php
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Business ID required']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT * FROM halal_businesses WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $id]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($business) {
        echo json_encode(['success' => true, 'data' => $business]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Business not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
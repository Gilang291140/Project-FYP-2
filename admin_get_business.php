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
    echo json_encode(['success' => false, 'message' => 'Business ID required']);
    exit;
}

$id = (int)$_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get business data
    $query = "SELECT * FROM halal_businesses WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $id]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($business) {
        // Get business images
        $imgQuery = "SELECT id, image_url, is_primary FROM business_images WHERE business_id = :id ORDER BY is_primary DESC, id ASC";
        $imgStmt = $db->prepare($imgQuery);
        $imgStmt->execute([':id' => $id]);
        $business['images'] = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get primary image
        $business['primary_image'] = !empty($business['images']) ? $business['images'][0]['image_url'] : null;
        
        echo json_encode(['success' => true, 'data' => $business]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Business not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
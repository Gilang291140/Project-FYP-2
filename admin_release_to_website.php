<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['business_id'])) {
    echo json_encode(['success' => false, 'message' => 'Business ID required']);
    exit;
}

$businessId = $input['business_id'];
$action = $input['action'] ?? 'release';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // First, check if business exists and is certified
    $checkQuery = "SELECT id, business_name, halal_status FROM halal_businesses WHERE id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([':id' => $businessId]);
    $business = $checkStmt->fetch();
    
    if (!$business) {
        echo json_encode(['success' => false, 'message' => 'Business not found']);
        exit;
    }
    
    if ($business['halal_status'] !== 'certified') {
        echo json_encode(['success' => false, 'message' => 'Only certified businesses can be published to website']);
        exit;
    }
    
    if ($action === 'release') {
        $query = "UPDATE halal_businesses SET 
                  is_published = 1, 
                  published_at = NOW() 
                  WHERE id = :id";
        $message = "Restaurant released to website successfully!";
    } else {
        $query = "UPDATE halal_businesses SET 
                  is_published = 0, 
                  published_at = NULL 
                  WHERE id = :id";
        $message = "Restaurant unpublished from website.";
    }
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute([':id' => $businessId]);
    
    if ($result) {
        // Verify update was successful
        $verifyQuery = "SELECT is_published FROM halal_businesses WHERE id = :id";
        $verifyStmt = $db->prepare($verifyQuery);
        $verifyStmt->execute([':id' => $businessId]);
        $updated = $verifyStmt->fetch();
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'is_published' => $updated['is_published']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update publish status']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
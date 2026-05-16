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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'verifier') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['restaurant_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Restaurant ID and action required']);
    exit;
}

$restaurantId = $input['restaurant_id'];
$action = $input['action'];
$reason = $input['reason'] ?? null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // Cek apakah kolom verification_notes ada
    $checkColumn = $db->query("SHOW COLUMNS FROM halal_businesses LIKE 'verification_notes'");
    $hasVerificationNotes = $checkColumn->rowCount() > 0;
    
    if ($action === 'approve') {
        if ($hasVerificationNotes) {
            $updateQuery = "UPDATE halal_businesses SET 
                            halal_status = 'certified', 
                            updated_at = NOW(), 
                            notification_sent = 1,
                            verification_notes = CONCAT('Approved by JHA on ', NOW())
                            WHERE id = :id";
        } else {
            $updateQuery = "UPDATE halal_businesses SET 
                            halal_status = 'certified', 
                            updated_at = NOW(), 
                            notification_sent = 1
                            WHERE id = :id";
        }
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([':id' => $restaurantId]);
        $message = "Restaurant approved and marked as Halal Certified!";
    } else {
        if ($hasVerificationNotes) {
            $updateQuery = "UPDATE halal_businesses SET 
                            halal_status = 'rejected', 
                            updated_at = NOW(), 
                            notification_sent = 1,
                            verification_notes = :reason
                            WHERE id = :id";
        } else {
            $updateQuery = "UPDATE halal_businesses SET 
                            halal_status = 'rejected', 
                            updated_at = NOW(), 
                            notification_sent = 1
                            WHERE id = :id";
        }
        $stmt = $db->prepare($updateQuery);
        if ($hasVerificationNotes) {
            $stmt->execute([
                ':id' => $restaurantId,
                ':reason' => $reason
            ]);
        } else {
            $stmt->execute([':id' => $restaurantId]);
        }
        $message = "Restaurant rejected.";
    }
    
    // Log activity
    $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, new_data, ip_address, created_at) 
                             VALUES (:user_id, :action, :table_name, :record_id, :new_data, :ip, NOW())");
    $logStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':action' => $action === 'approve' ? 'RESTAURANT_APPROVED' : 'RESTAURANT_REJECTED',
        ':table_name' => 'halal_businesses',
        ':record_id' => $restaurantId,
        ':new_data' => json_encode(['reason' => $reason, 'status' => $action === 'approve' ? 'certified' : 'rejected']),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
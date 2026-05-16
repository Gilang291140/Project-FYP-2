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

if (!isset($input['certificate_id'])) {
    echo json_encode(['success' => false, 'message' => 'Certificate ID required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get certificate details
    $query = "SELECT cd.*, b.business_name FROM certification_documents cd 
              LEFT JOIN halal_businesses b ON cd.business_id = b.id 
              WHERE cd.id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $input['certificate_id']]);
    $cert = $stmt->fetch();
    
    if ($cert) {
        // Create notification log
        $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, new_data, ip_address, created_at) 
                                 VALUES (:user_id, 'CERTIFICATE_RENEWAL_NEEDED', 'certification_documents', :record_id, :new_data, :ip, NOW())");
        $logStmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':record_id' => $input['certificate_id'],
            ':new_data' => json_encode([
                'business_name' => $cert['business_name'],
                'expiry_date' => $cert['expiry_date'],
                'message' => 'Certificate needs renewal'
            ]),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Renewal notification sent to Admin']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Certificate not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
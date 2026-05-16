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

if (!isset($input['business_id']) || empty($input['business_id'])) {
    echo json_encode(['success' => false, 'message' => 'Business ID required']);
    exit;
}

$businessId = $input['business_id'];
$newCertNumber = $input['cert_number'] ?? 'RENEW-' . strtoupper(uniqid());
$newExpiryDate = $input['expiry_date'] ?? date('Y-m-d', strtotime('+2 years'));

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // Get old certificate info
    $getOld = $db->prepare("SELECT cert_number, cert_expiry_date, business_name FROM halal_businesses WHERE id = :id");
    $getOld->execute([':id' => $businessId]);
    $old = $getOld->fetch();
    
    // Create renewal request
    $renewQuery = "INSERT INTO certificate_renewal_history 
                   (business_id, old_cert_number, new_cert_number, old_expiry_date, new_expiry_date, renewal_date, renewed_by, status) 
                   VALUES 
                   (:business_id, :old_cert, :new_cert, :old_expiry, :new_expiry, NOW(), :renewed_by, 'pending')";
    $renewStmt = $db->prepare($renewQuery);
    $renewStmt->execute([
        ':business_id' => $businessId,
        ':old_cert' => $old['cert_number'],
        ':new_cert' => $newCertNumber,
        ':old_expiry' => $old['cert_expiry_date'],
        ':new_expiry' => $newExpiryDate,
        ':renewed_by' => $_SESSION['user_id']
    ]);
    
    // Update business certificate status to pending renewal
    $updateQuery = "UPDATE halal_businesses SET 
                    cert_status = 'pending',
                    halal_status = 'pending',
                    updated_at = NOW()
                    WHERE id = :id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([':id' => $businessId]);
    
    // Log activity
    $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, new_data, ip_address, created_at) 
                             VALUES (:user_id, 'CERTIFICATE_RENEWAL_REQUESTED', 'halal_businesses', :record_id, :new_data, :ip, NOW())");
    $logStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':record_id' => $businessId,
        ':new_data' => json_encode([
            'business_name' => $old['business_name'],
            'old_cert_number' => $old['cert_number'],
            'new_cert_number' => $newCertNumber,
            'new_expiry_date' => $newExpiryDate
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Renewal request submitted! Waiting for JHA approval. Certificate valid for 2 years.',
        'renewal_id' => $db->lastInsertId()
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
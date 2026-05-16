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

if (!isset($input['renewal_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Renewal ID and action required']);
    exit;
}

$renewalId = $input['renewal_id'];
$action = $input['action'];
$reason = $input['reason'] ?? null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // Get renewal details
    $getRenewal = $db->prepare("SELECT * FROM certificate_renewal_history WHERE id = :id");
    $getRenewal->execute([':id' => $renewalId]);
    $renewal = $getRenewal->fetch();
    
    if ($action === 'approve') {
        // Update renewal status
        $updateRenewal = "UPDATE certificate_renewal_history SET 
                          status = 'approved', 
                          approved_by = :approved_by, 
                          approved_at = NOW() 
                          WHERE id = :id";
        $stmt = $db->prepare($updateRenewal);
        $stmt->execute([
            ':approved_by' => $_SESSION['user_id'],
            ':id' => $renewalId
        ]);
        
        // Update business with new certificate
        $updateBusiness = "UPDATE halal_businesses SET 
                          cert_number = :cert_number,
                          cert_expiry_date = :expiry_date,
                          cert_status = 'verified',
                          halal_status = 'certified',
                          updated_at = NOW()
                          WHERE id = :business_id";
        $businessStmt = $db->prepare($updateBusiness);
        $businessStmt->execute([
            ':cert_number' => $renewal['new_cert_number'],
            ':expiry_date' => $renewal['new_expiry_date'],
            ':business_id' => $renewal['business_id']
        ]);
        
        $message = "Certificate renewal approved! New certificate valid for 2 years.";
    } else {
        // Reject renewal
        $updateRenewal = "UPDATE certificate_renewal_history SET 
                          status = 'rejected', 
                          approved_by = :approved_by, 
                          approved_at = NOW(),
                          rejection_reason = :reason
                          WHERE id = :id";
        $stmt = $db->prepare($updateRenewal);
        $stmt->execute([
            ':approved_by' => $_SESSION['user_id'],
            ':reason' => $reason,
            ':id' => $renewalId
        ]);
        
        // Restore old certificate status
        $updateBusiness = "UPDATE halal_businesses SET 
                          cert_status = 'expired',
                          halal_status = 'expired',
                          updated_at = NOW()
                          WHERE id = :business_id";
        $businessStmt = $db->prepare($updateBusiness);
        $businessStmt->execute([':business_id' => $renewal['business_id']]);
        
        $message = "Certificate renewal rejected.";
    }
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
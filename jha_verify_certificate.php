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

if (!isset($input['business_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Business ID and action required']);
    exit;
}

$businessId = $input['business_id'];
$action = $input['action'];
$reason = $input['reason'] ?? null;
$certificationBody = $input['certification_body'] ?? null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // Get business info
    $getBusiness = $db->prepare("SELECT business_name, cert_number, cert_issued_by FROM halal_businesses WHERE id = :id");
    $getBusiness->execute([':id' => $businessId]);
    $business = $getBusiness->fetch();
    
    if (!$business) {
        throw new Exception("Business not found");
    }
    
    if ($action === 'approve') {
        // Use selected certification body or existing one
        $finalIssuedBy = $certificationBody ?? $business['cert_issued_by'] ?? 'JHA';
        
        // Update certificate status to verified
        $updateQuery = "UPDATE halal_businesses SET 
                        cert_status = 'verified',
                        halal_status = 'certified',
                        cert_verified_by = :verified_by,
                        cert_verified_at = NOW(),
                        cert_issued_by = :issued_by,
                        updated_at = NOW()
                        WHERE id = :id";
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([
            ':verified_by' => $_SESSION['user_id'],
            ':issued_by' => $finalIssuedBy,
            ':id' => $businessId
        ]);
        
        $displayStatus = '';
        if ($finalIssuedBy === 'JHA') $displayStatus = 'Certified by JHA';
        elseif ($finalIssuedBy === 'JAKIM') $displayStatus = 'Certified by JAKIM';
        elseif ($finalIssuedBy === 'MUIS') $displayStatus = 'Certified by MUIS';
        else $displayStatus = 'Certified by Other Body';
        
        $message = "Certificate approved! Business is now " . $displayStatus;
    } else {
        // Reject certificate
        $updateQuery = "UPDATE halal_businesses SET 
                        cert_status = 'rejected',
                        halal_status = 'pending',
                        updated_at = NOW()
                        WHERE id = :id";
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([':id' => $businessId]);
        
        $message = "Certificate rejected. Business status remains pending.";
    }
    
    // Log activity
    $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, new_data, ip_address, created_at) 
                             VALUES (:user_id, :action, 'halal_businesses', :record_id, :new_data, :ip, NOW())");
    $logStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':action' => $action === 'approve' ? 'CERTIFICATE_APPROVED' : 'CERTIFICATE_REJECTED',
        ':record_id' => $businessId,
        ':new_data' => json_encode([
            'business_name' => $business['business_name'],
            'cert_number' => $business['cert_number']
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
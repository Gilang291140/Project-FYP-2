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

if (!isset($input['document_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Document ID and action required']);
    exit;
}

$document_id = $input['document_id'];
$action = $input['action'];
$reason = $input['reason'] ?? null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    if ($action === 'approve') {
        // Update certificate status to verified
        $updateQuery = "UPDATE certification_documents SET 
                        verification_status = 'verified',
                        verified_by = :verified_by,
                        verified_at = NOW()
                        WHERE id = :id";
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([
            ':verified_by' => $_SESSION['user_id'],
            ':id' => $document_id
        ]);
        
        // Get business_id from certificate
        $getBusiness = "SELECT business_id FROM certification_documents WHERE id = :id";
        $getStmt = $db->prepare($getBusiness);
        $getStmt->execute([':id' => $document_id]);
        $doc = $getStmt->fetch();
        
        if ($doc) {
            // Update business status to certified
            $updateBusiness = "UPDATE halal_businesses SET 
                              halal_status = 'certified', 
                              updated_at = NOW() 
                              WHERE id = :business_id";
            $businessStmt = $db->prepare($updateBusiness);
            $businessStmt->execute([':business_id' => $doc['business_id']]);
        }
        
        $message = "Certificate approved! Business marked as Halal Certified.";
    } else {
        // Reject certificate
        $updateQuery = "UPDATE certification_documents SET 
                        verification_status = 'rejected',
                        verified_by = :verified_by,
                        verified_at = NOW(),
                        rejection_reason = :reason
                        WHERE id = :id";
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([
            ':verified_by' => $_SESSION['user_id'],
            ':reason' => $reason,
            ':id' => $document_id
        ]);
        
        $message = "Certificate rejected.";
    }
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
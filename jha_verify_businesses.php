<?php
session_start();
require_once '../backend/config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'verifier') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['document_id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Document ID and status required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    $verificationStatus = $input['status'] === 'approve' ? 'verified' : 'rejected';
    
    $updateQuery = "UPDATE certification_documents SET 
                    verification_status = :status,
                    verified_by = :verified_by,
                    verified_at = NOW()
                    WHERE id = :id";
    $stmt = $db->prepare($updateQuery);
    $stmt->execute([
        ':status' => $verificationStatus,
        ':verified_by' => $_SESSION['user_id'],
        ':id' => $input['document_id']
    ]);
    
    if ($input['status'] === 'approve') {
        $getBusiness = "SELECT business_id FROM certification_documents WHERE id = :id";
        $getStmt = $db->prepare($getBusiness);
        $getStmt->execute([':id' => $input['document_id']]);
        $doc = $getStmt->fetch();
        
        if ($doc) {
            $updateBusiness = "UPDATE halal_businesses SET halal_status = 'certified', updated_at = NOW() WHERE id = :business_id";
            $businessStmt = $db->prepare($updateBusiness);
            $businessStmt->execute([':business_id' => $doc['business_id']]);
        }
    }
    
    $db->commit();
    
    echo json_encode(['success' => true, 'message' => 'Document ' . $input['status'] . 'd successfully']);
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
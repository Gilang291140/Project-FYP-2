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
    
    // Update status dokumen
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    $updateQuery = "UPDATE admin_uploaded_documents 
                    SET verification_status = :status, 
                        verified_by = :verified_by, 
                        verified_at = NOW(),
                        rejection_reason = :reason
                    WHERE id = :id";
    
    $stmt = $db->prepare($updateQuery);
    $stmt->execute([
        ':status' => $status,
        ':verified_by' => $_SESSION['user_id'],
        ':reason' => $reason,
        ':id' => $document_id
    ]);
    
    // Jika approve, update status bisnis
    if ($action === 'approve') {
        $getBusiness = "SELECT business_id FROM admin_uploaded_documents WHERE id = :id";
        $getStmt = $db->prepare($getBusiness);
        $getStmt->execute([':id' => $document_id]);
        $doc = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($doc) {
            $updateBusiness = "UPDATE halal_businesses 
                              SET halal_status = 'certified', updated_at = NOW() 
                              WHERE id = :business_id";
            $businessStmt = $db->prepare($updateBusiness);
            $businessStmt->execute([':business_id' => $doc['business_id']]);
        }
    }
    
    $db->commit();
    
    $message = ($action === 'approve') ? 'Document approved and business marked as Halal Certified!' : 'Document rejected.';
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
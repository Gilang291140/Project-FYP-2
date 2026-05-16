<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => []]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, business_name, cert_number, cert_issue_date, cert_expiry_date, 
                     cert_issued_by, cert_status, halal_status, created_at
              FROM halal_businesses 
              WHERE created_by = :user_id AND cert_number IS NOT NULL
              ORDER BY created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $certificates = $stmt->fetchAll();
    
    foreach ($certificates as &$cert) {
        // Calculate days until expiry
        if ($cert['cert_expiry_date']) {
            $today = new DateTime();
            $expiry = new DateTime($cert['cert_expiry_date']);
            $cert['days_until_expiry'] = $today->diff($expiry)->days;
            $cert['is_expired'] = $expiry < $today;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $certificates]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}
?>
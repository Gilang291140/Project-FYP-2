<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'verifier') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $pending = $db->query("SELECT COUNT(*) as count FROM certification_documents WHERE verification_status = 'pending'")->fetch()['count'];
    $adminPending = $db->query("SELECT COUNT(*) as count FROM admin_uploaded_documents WHERE verification_status = 'pending'")->fetch()['count'];
    $certified = $db->query("SELECT COUNT(*) as count FROM halal_businesses WHERE halal_status = 'certified'")->fetch()['count'];
    $totalCerts = $db->query("SELECT COUNT(*) as count FROM certification_documents")->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'pending' => (int)$pending,
        'admin_pending' => (int)$adminPending,
        'certified_businesses' => (int)$certified,
        'total_certificates' => (int)$totalCerts
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
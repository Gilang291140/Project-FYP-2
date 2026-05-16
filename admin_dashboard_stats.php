<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Cache-Control: no-cache, must-revalidate");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // FIRST: Update expired certificates
    $updateExpiredCerts = "UPDATE certification_documents 
                           SET verification_status = 'expired'
                           WHERE verification_status = 'verified' 
                           AND expiry_date IS NOT NULL 
                           AND expiry_date < CURDATE()";
    $db->exec($updateExpiredCerts);
    
    // SECOND: Update expired businesses
    $updateExpiredBusinesses = "UPDATE halal_businesses b
                                SET b.halal_status = 'expired', b.updated_at = NOW()
                                WHERE EXISTS (
                                    SELECT 1 FROM certification_documents cd 
                                    WHERE cd.business_id = b.id 
                                    AND cd.verification_status = 'expired'
                                ) AND b.halal_status != 'expired'";
    $db->exec($updateExpiredBusinesses);
    
    // Get stats
    $total = $db->query("SELECT COUNT(*) as count FROM halal_businesses")->fetch()['count'];
    $certified = $db->query("SELECT COUNT(*) as count FROM halal_businesses WHERE halal_status = 'certified'")->fetch()['count'];
    $verified = $db->query("SELECT COUNT(*) as count FROM halal_businesses WHERE halal_status = 'verified'")->fetch()['count'];
    $pending = $db->query("SELECT COUNT(*) as count FROM halal_businesses WHERE halal_status = 'pending'")->fetch()['count'];
    $expired = $db->query("SELECT COUNT(*) as count FROM halal_businesses WHERE halal_status = 'expired'")->fetch()['count'];
    $rejected = $db->query("SELECT COUNT(*) as count FROM halal_businesses WHERE halal_status = 'rejected'")->fetch()['count'];
    
    // Get expiring soon count (within 30 days)
    $expiringSoon = $db->query("SELECT COUNT(*) as count FROM certification_documents 
                                WHERE verification_status = 'verified' 
                                AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch()['count'];
    
    // Get expired certificates count
    $expiredCerts = $db->query("SELECT COUNT(*) as count FROM certification_documents 
                                WHERE verification_status = 'expired'")->fetch()['count'];
    
    $lastUpdate = $db->query("SELECT MAX(updated_at) as last_update FROM halal_businesses")->fetch()['last_update'];
    
    echo json_encode([
        'success' => true,
        'total' => (int)$total,
        'certified' => (int)$certified,
        'verified' => (int)$verified,
        'pending' => (int)$pending,
        'expired' => (int)$expired,
        'rejected' => (int)$rejected,
        'expiring_soon' => (int)$expiringSoon,
        'expired_certificates' => (int)$expiredCerts,
        'last_update' => $lastUpdate
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
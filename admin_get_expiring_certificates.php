<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'data' => []]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // First, update expired certificates (all statuses)
    $updateExpired = "UPDATE certification_documents 
                      SET verification_status = 'expired'
                      WHERE verification_status IN ('verified', 'pending', 'certified')
                      AND expiry_date IS NOT NULL 
                      AND expiry_date < CURDATE()";
    $db->exec($updateExpired);
    
    // Update expired businesses
    $updateBusiness = "UPDATE halal_businesses b
                       SET b.halal_status = 'expired'
                       WHERE EXISTS (
                           SELECT 1 FROM certification_documents cd 
                           WHERE cd.business_id = b.id 
                           AND cd.verification_status = 'expired'
                       ) AND b.halal_status != 'expired'";
    $db->exec($updateBusiness);
    
    // Get ALL certificates with expiry info (verified, pending, certified)
    $query = "SELECT 
                cd.id,
                cd.business_id,
                b.business_name,
                cd.certificate_number,
                cd.issue_date,
                cd.expiry_date,
                cd.verification_status,
                CASE 
                    WHEN cd.expiry_date IS NULL THEN 'no_expiry'
                    WHEN cd.expiry_date < CURDATE() THEN 'expired'
                    WHEN cd.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'expiring_soon'
                    ELSE 'valid'
                END as expiry_status,
                DATEDIFF(cd.expiry_date, CURDATE()) as days_until_expiry
            FROM certification_documents cd
            LEFT JOIN halal_businesses b ON cd.business_id = b.id
            WHERE cd.verification_status IN ('verified', 'pending', 'certified')
            ORDER BY cd.expiry_date ASC";
    
    $stmt = $db->query($query);
    $certificates = $stmt->fetchAll();
    
    // Calculate statistics
    $expired = 0;
    $expiringSoon = 0;
    $valid = 0;
    $noExpiry = 0;
    
    foreach ($certificates as $cert) {
        if ($cert['expiry_status'] === 'expired') $expired++;
        elseif ($cert['expiry_status'] === 'expiring_soon') $expiringSoon++;
        elseif ($cert['expiry_status'] === 'valid') $valid++;
        elseif ($cert['expiry_status'] === 'no_expiry') $noExpiry++;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $certificates,
        'stats' => [
            'expired' => $expired,
            'expiring_soon' => $expiringSoon,
            'valid' => $valid,
            'no_expiry' => $noExpiry,
            'total' => count($certificates)
        ],
        'current_date' => date('Y-m-d')
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}
?>
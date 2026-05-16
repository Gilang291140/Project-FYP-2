<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get counts for all statuses
    $certified = $db->query("SELECT COUNT(*) as count FROM halal_businesses WHERE halal_status = 'certified'")->fetch()['count'];
    $verified = $db->query("SELECT COUNT(*) as count FROM halal_businesses WHERE halal_status = 'verified'")->fetch()['count'];
    $pending = $db->query("SELECT COUNT(*) as count FROM halal_businesses WHERE halal_status = 'pending'")->fetch()['count'];
    $expired = $db->query("SELECT COUNT(*) as count FROM halal_businesses WHERE halal_status = 'expired'")->fetch()['count'];
    $rejected = $db->query("SELECT COUNT(*) as count FROM halal_businesses WHERE halal_status = 'rejected'")->fetch()['count'];
    
    // Get cities data - GROUP BY city, COUNT restaurants
    $cityQuery = "SELECT 
                    city, 
                    COUNT(*) as count 
                  FROM halal_businesses 
                  WHERE city IS NOT NULL AND city != '' 
                  GROUP BY city 
                  ORDER BY count DESC 
                  LIMIT 10";
    $cityStmt = $db->query($cityQuery);
    $cities = [];
    $cityCounts = [];
    while ($row = $cityStmt->fetch()) {
        $cities[] = $row['city'];
        $cityCounts[] = (int)$row['count'];
    }
    
    // Get certificate expiry stats
    $expiredCerts = $db->query("SELECT COUNT(*) as count FROM certification_documents WHERE verification_status = 'verified' AND expiry_date < CURDATE()")->fetch()['count'];
    $expiringSoon = $db->query("SELECT COUNT(*) as count FROM certification_documents WHERE verification_status = 'verified' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'certified' => (int)$certified,
        'verified' => (int)$verified,
        'pending' => (int)$pending,
        'expired' => (int)$expired,
        'rejected' => (int)$rejected,
        'expired_certificates' => (int)$expiredCerts,
        'expiring_soon_certificates' => (int)$expiringSoon,
        'cities' => $cities,
        'city_counts' => $cityCounts,
        'current_date' => date('Y-m-d')
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
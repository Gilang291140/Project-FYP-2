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
    
    // 1. Monthly growth data (last 12 months)
    $monthlyQuery = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COUNT(*) as total
                     FROM halal_businesses
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                     ORDER BY month ASC";
    $monthlyStmt = $db->query($monthlyQuery);
    $monthlyData = $monthlyStmt->fetchAll();
    
    // 2. Category distribution
    $categoryQuery = "SELECT 
                        category,
                        COUNT(*) as total,
                        SUM(CASE WHEN halal_status = 'certified' THEN 1 ELSE 0 END) as certified,
                        SUM(CASE WHEN halal_status = 'verified' THEN 1 ELSE 0 END) as verified,
                        SUM(CASE WHEN halal_status = 'pending' THEN 1 ELSE 0 END) as pending
                      FROM halal_businesses
                      GROUP BY category
                      ORDER BY total DESC";
    $categoryStmt = $db->query($categoryQuery);
    $categoryData = $categoryStmt->fetchAll();
    
    // 3. City distribution (top 10)
    $cityQuery = "SELECT 
                    city,
                    COUNT(*) as total,
                    SUM(CASE WHEN halal_status = 'certified' THEN 1 ELSE 0 END) as certified
                  FROM halal_businesses
                  WHERE city IS NOT NULL AND city != ''
                  GROUP BY city
                  ORDER BY total DESC
                  LIMIT 10";
    $cityStmt = $db->query($cityQuery);
    $cityData = $cityStmt->fetchAll();
    
    // 4. Status distribution
    $statusQuery = "SELECT 
                        halal_status,
                        COUNT(*) as total
                    FROM halal_businesses
                    GROUP BY halal_status";
    $statusStmt = $db->query($statusQuery);
    $statusData = $statusStmt->fetchAll();
    
    // 5. Certificate expiry status
    $certStatusQuery = "SELECT 
                            CASE 
                                WHEN expiry_date IS NULL THEN 'No Expiry'
                                WHEN expiry_date < CURDATE() THEN 'Expired'
                                WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expiring Soon'
                                ELSE 'Valid'
                            END as cert_status,
                            COUNT(*) as total
                        FROM certification_documents
                        WHERE verification_status = 'verified'
                        GROUP BY cert_status";
    $certStatusStmt = $db->query($certStatusQuery);
    $certStatusData = $certStatusStmt->fetchAll();
    
    // 6. Monthly trend for certified businesses
    $certTrendQuery = "SELECT 
                          DATE_FORMAT(cert_verified_at, '%Y-%m') as month,
                          COUNT(*) as total
                       FROM halal_businesses
                       WHERE cert_verified_at IS NOT NULL
                       AND cert_verified_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                       GROUP BY DATE_FORMAT(cert_verified_at, '%Y-%m')
                       ORDER BY month ASC";
    $certTrendStmt = $db->query($certTrendQuery);
    $certTrendData = $certTrendStmt->fetchAll();
    
    // 7. Total summary
    $summaryQuery = "SELECT 
                        COUNT(*) as total_businesses,
                        SUM(CASE WHEN halal_status = 'certified' THEN 1 ELSE 0 END) as certified,
                        SUM(CASE WHEN halal_status = 'verified' THEN 1 ELSE 0 END) as verified,
                        SUM(CASE WHEN halal_status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN halal_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                        SUM(CASE WHEN halal_status = 'expired' THEN 1 ELSE 0 END) as expired,
                        COUNT(DISTINCT city) as total_cities,
                        (SELECT COUNT(*) FROM users WHERE role != 'verifier') as total_admins,
                        (SELECT COUNT(*) FROM users WHERE role = 'verifier') as total_verifiers
                     FROM halal_businesses";
    $summaryStmt = $db->query($summaryQuery);
    $summary = $summaryStmt->fetch();
    
    // 8. Recent activities for analytics
    $recentQuery = "SELECT 
                        a.*, 
                        u.username,
                        b.business_name
                    FROM activity_logs a
                    LEFT JOIN users u ON a.user_id = u.id
                    LEFT JOIN halal_businesses b ON a.record_id = b.id AND a.table_name = 'halal_businesses'
                    ORDER BY a.created_at DESC
                    LIMIT 20";
    $recentStmt = $db->query($recentQuery);
    $recentActivities = $recentStmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'monthly_growth' => $monthlyData,
        'category_distribution' => $categoryData,
        'city_distribution' => $cityData,
        'status_distribution' => $statusData,
        'certificate_status' => $certStatusData,
        'certified_trend' => $certTrendData,
        'summary' => $summary,
        'recent_activities' => $recentActivities,
        'current_date' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
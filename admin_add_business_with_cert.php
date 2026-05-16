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

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$required = ['business_name', 'category', 'address', 'city', 'cert_number', 'cert_issue_date', 'cert_expiry_date'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty(trim($input[$field]))) {
        echo json_encode(['success' => false, 'message' => "$field is required"]);
        exit;
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // Insert business dengan certificate
    $query = "INSERT INTO halal_businesses 
              (business_name, category, address, city, prefecture, phone, email, website,
               description, opening_hours, price_range, halal_status, no_pork, no_alcohol,
               muslim_owner, cert_number, cert_issue_date, cert_expiry_date, cert_issued_by, 
               cert_status, created_by, created_at) 
              VALUES 
              (:business_name, :category, :address, :city, :prefecture, :phone, :email, :website,
               :description, :opening_hours, :price_range, :halal_status, :no_pork, :no_alcohol,
               :muslim_owner, :cert_number, :cert_issue_date, :cert_expiry_date, :cert_issued_by,
               'pending', :created_by, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':business_name' => $input['business_name'],
        ':category' => $input['category'],
        ':address' => $input['address'],
        ':city' => $input['city'],
        ':prefecture' => $input['prefecture'] ?? '',
        ':phone' => $input['phone'] ?? '',
        ':email' => $input['email'] ?? '',
        ':website' => $input['website'] ?? '',
        ':description' => $input['description'] ?? '',
        ':opening_hours' => $input['opening_hours'] ?? '',
        ':price_range' => $input['price_range'] ?? '$$',
        ':halal_status' => 'pending',
        ':no_pork' => isset($input['no_pork']) ? 1 : 1,
        ':no_alcohol' => isset($input['no_alcohol']) ? 1 : 0,
        ':muslim_owner' => isset($input['muslim_owner']) ? 1 : 0,
        ':cert_number' => $input['cert_number'],
        ':cert_issue_date' => $input['cert_issue_date'],
        ':cert_expiry_date' => $input['cert_expiry_date'],
        ':cert_issued_by' => $input['cert_issued_by'],
        ':created_by' => $_SESSION['user_id']
    ]);
    
    $businessId = $db->lastInsertId();
    
    // Log activity
    $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, new_data, ip_address, created_at) 
                             VALUES (:user_id, 'CERTIFICATE_SUBMITTED', 'halal_businesses', :record_id, :new_data, :ip, NOW())");
    $logStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':record_id' => $businessId,
        ':new_data' => json_encode([
            'business_name' => $input['business_name'],
            'cert_number' => $input['cert_number'],
            'cert_issued_by' => $input['cert_issued_by'],
            'expiry_date' => $input['cert_expiry_date']
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Restaurant added successfully! Certificate submitted to JHA for verification.',
        'business_id' => $businessId
    ]);
    
} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
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
$required = ['business_name', 'category', 'address', 'city'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty(trim($input[$field]))) {
        echo json_encode(['success' => false, 'message' => "$field is required"]);
        exit;
    }
}

// Admin HANYA bisa memilih 'pending' (TIDAK BISA verified atau certified)
$halal_status = 'pending'; // Force to pending only

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    // Cek apakah kolom certificate sudah ada di tabel
    $checkColumns = $db->query("SHOW COLUMNS FROM halal_businesses LIKE 'cert_number'");
    $hasCertColumns = $checkColumns->rowCount() > 0;
    
    if ($hasCertColumns) {
        // Insert dengan kolom certificate
        $query = "INSERT INTO halal_businesses 
                  (business_name, category, address, city, prefecture, postal_code, phone, email, website,
                   description, opening_hours, price_range, halal_status, no_pork, no_alcohol,
                   muslim_owner, latitude, longitude, created_by, created_at,
                   cert_number, cert_issue_date, cert_expiry_date, cert_issued_by, cert_status) 
                  VALUES 
                  (:business_name, :category, :address, :city, :prefecture, :postal_code, :phone, :email, :website,
                   :description, :opening_hours, :price_range, :halal_status, :no_pork, :no_alcohol,
                   :muslim_owner, :latitude, :longitude, :created_by, NOW(),
                   :cert_number, :cert_issue_date, :cert_expiry_date, :cert_issued_by, 
                   CASE WHEN :cert_number IS NOT NULL AND :cert_number != '' THEN 'pending' ELSE 'none' END)";
        
        $stmt = $db->prepare($query);
        
        // Ambil data certificate (optional)
        $cert_number = isset($input['cert_number']) && !empty($input['cert_number']) ? $input['cert_number'] : null;
        $cert_issue_date = isset($input['issue_date']) && !empty($input['issue_date']) ? $input['issue_date'] : null;
        $cert_expiry_date = isset($input['expiry_date']) && !empty($input['expiry_date']) ? $input['expiry_date'] : null;
        $cert_issued_by = isset($input['issued_by']) && !empty($input['issued_by']) ? $input['issued_by'] : null;
        
        $stmt->execute([
            ':business_name' => $input['business_name'],
            ':category' => $input['category'],
            ':address' => $input['address'],
            ':city' => $input['city'],
            ':prefecture' => $input['prefecture'] ?? '',
            ':postal_code' => $input['postal_code'] ?? '',
            ':phone' => $input['phone'] ?? '',
            ':email' => $input['email'] ?? '',
            ':website' => $input['website'] ?? '',
            ':description' => $input['description'] ?? '',
            ':opening_hours' => $input['opening_hours'] ?? '',
            ':price_range' => $input['price_range'] ?? '$$',
            ':halal_status' => $halal_status,
            ':no_pork' => isset($input['no_pork']) ? 1 : 1,
            ':no_alcohol' => isset($input['no_alcohol']) ? 1 : 0,
            ':muslim_owner' => isset($input['muslim_owner']) ? 1 : 0,
            ':latitude' => $input['latitude'] ?? null,
            ':longitude' => $input['longitude'] ?? null,
            ':created_by' => $_SESSION['user_id'],
            ':cert_number' => $cert_number,
            ':cert_issue_date' => $cert_issue_date,
            ':cert_expiry_date' => $cert_expiry_date,
            ':cert_issued_by' => $cert_issued_by
        ]);
    } else {
        // Insert tanpa kolom certificate (fallback)
        $query = "INSERT INTO halal_businesses 
                  (business_name, category, address, city, prefecture, postal_code, phone, email, website,
                   description, opening_hours, price_range, halal_status, no_pork, no_alcohol,
                   muslim_owner, latitude, longitude, created_by, created_at) 
                  VALUES 
                  (:business_name, :category, :address, :city, :prefecture, :postal_code, :phone, :email, :website,
                   :description, :opening_hours, :price_range, :halal_status, :no_pork, :no_alcohol,
                   :muslim_owner, :latitude, :longitude, :created_by, NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':business_name' => $input['business_name'],
            ':category' => $input['category'],
            ':address' => $input['address'],
            ':city' => $input['city'],
            ':prefecture' => $input['prefecture'] ?? '',
            ':postal_code' => $input['postal_code'] ?? '',
            ':phone' => $input['phone'] ?? '',
            ':email' => $input['email'] ?? '',
            ':website' => $input['website'] ?? '',
            ':description' => $input['description'] ?? '',
            ':opening_hours' => $input['opening_hours'] ?? '',
            ':price_range' => $input['price_range'] ?? '$$',
            ':halal_status' => $halal_status,
            ':no_pork' => isset($input['no_pork']) ? 1 : 1,
            ':no_alcohol' => isset($input['no_alcohol']) ? 1 : 0,
            ':muslim_owner' => isset($input['muslim_owner']) ? 1 : 0,
            ':latitude' => $input['latitude'] ?? null,
            ':longitude' => $input['longitude'] ?? null,
            ':created_by' => $_SESSION['user_id']
        ]);
    }
    
    $businessId = $db->lastInsertId();
    
    // Log activity
    $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, new_data, ip_address, created_at) 
                             VALUES (:user_id, 'BUSINESS_ADDED', 'halal_businesses', :record_id, :new_data, :ip, NOW())");
    $logStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':record_id' => $businessId,
        ':new_data' => json_encode([
            'business_name' => $input['business_name'],
            'halal_status' => $halal_status,
            'has_certificate' => !empty($cert_number)
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    $db->commit();
    
    $message = "Restaurant added successfully! Status: Pending.";
    if (!empty($cert_number)) {
        $message .= " Certificate has been submitted to JHA for verification.";
    } else {
        $message .= " You can add certificate later in the edit menu.";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'id' => $businessId,
        'certificate_submitted' => !empty($cert_number)
    ]);
    
} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
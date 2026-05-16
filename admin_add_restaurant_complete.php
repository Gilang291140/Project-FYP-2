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

$uploadDir = '../backend/uploads/';
$imageDir = $uploadDir . 'images/';
$certDir = $uploadDir . 'certificates/';

if (!file_exists($imageDir)) mkdir($imageDir, 0777, true);
if (!file_exists($certDir)) mkdir($certDir, 0777, true);

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->beginTransaction();
    
    // Get certificate data from form
    $certNumber = isset($_POST['cert_number']) && !empty(trim($_POST['cert_number'])) ? trim($_POST['cert_number']) : null;
    $issuedBy = isset($_POST['issued_by']) && !empty($_POST['issued_by']) ? $_POST['issued_by'] : null;
    $issueDate = isset($_POST['issue_date']) && !empty($_POST['issue_date']) ? $_POST['issue_date'] : null;
    $expiryDate = isset($_POST['expiry_date']) && !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    // Check if ALL certificate fields are filled (COMPLETE)
    $isCertificateComplete = ($certNumber && $issuedBy && $issueDate && $expiryDate);
    
    // Determine status - HANYA jika lengkap maka dikirim ke JHA
    $sendToJHA = $isCertificateComplete;
    $finalCertStatus = $sendToJHA ? 'pending' : 'none';
    $halalStatus = 'pending';
    
    // Log untuk debugging
    error_log("=== ADD RESTAURANT ===");
    error_log("Cert Number: $certNumber, Issued By: $issuedBy, Issue Date: $issueDate, Expiry: $expiryDate");
    error_log("Is Complete: " . ($isCertificateComplete ? 'YES' : 'NO'));
    error_log("Send to JHA: " . ($sendToJHA ? 'YES' : 'NO'));
    
    // Insert business
    $query = "INSERT INTO halal_businesses 
              (business_name, category, address, city, prefecture, postal_code, phone, email, website,
               description, opening_hours, price_range, halal_status, no_pork, no_alcohol,
               muslim_owner, latitude, longitude, created_by, created_at,
               cert_number, cert_issue_date, cert_expiry_date, cert_issued_by, cert_status) 
              VALUES 
              (:business_name, :category, :address, :city, :prefecture, :postal_code, :phone, :email, :website,
               :description, :opening_hours, :price_range, :halal_status, :no_pork, :no_alcohol,
               :muslim_owner, :latitude, :longitude, :created_by, NOW(),
               :cert_number, :cert_issue_date, :cert_expiry_date, :cert_issued_by, :cert_status)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':business_name' => $_POST['business_name'],
        ':category' => $_POST['category'],
        ':address' => $_POST['address'],
        ':city' => $_POST['city'],
        ':prefecture' => $_POST['prefecture'] ?? '',
        ':postal_code' => $_POST['postal_code'] ?? '',
        ':phone' => $_POST['phone'] ?? '',
        ':email' => $_POST['email'] ?? '',
        ':website' => $_POST['website'] ?? '',
        ':description' => $_POST['description'] ?? '',
        ':opening_hours' => $_POST['opening_hours'] ?? '',
        ':price_range' => $_POST['price_range'] ?? '$$',
        ':halal_status' => $halalStatus,
        ':no_pork' => $_POST['no_pork'] ?? 1,
        ':no_alcohol' => $_POST['no_alcohol'] ?? 0,
        ':muslim_owner' => $_POST['muslim_owner'] ?? 0,
        ':latitude' => !empty($_POST['latitude']) ? $_POST['latitude'] : null,
        ':longitude' => !empty($_POST['longitude']) ? $_POST['longitude'] : null,
        ':created_by' => $_SESSION['user_id'],
        ':cert_number' => $certNumber,
        ':cert_issue_date' => $issueDate,
        ':cert_expiry_date' => $expiryDate,
        ':cert_issued_by' => $issuedBy,
        ':cert_status' => $finalCertStatus
    ]);
    
    $businessId = $db->lastInsertId();
    
    // Upload certificate file ONLY if sending to JHA
    // Upload certificate file - DISABLED: cert_file_path column doesn't exist
    // if ($sendToJHA && isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
    //     $certFile = $_FILES['certificate'];
    //     $ext = strtolower(pathinfo($certFile['name'], PATHINFO_EXTENSION));
    //     $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    //     if (in_array($ext, $allowedExt)) {
    //         $certFilename = 'cert_' . $businessId . '_' . time() . '.' . $ext;
    //         if (move_uploaded_file($certFile['tmp_name'], $certDir . $certFilename)) {
    //             $certFilePath = 'uploads/certificates/' . $certFilename;
    //             $updateFile = $db->prepare("UPDATE halal_businesses SET cert_file_path = :path WHERE id = :id");
    //             $updateFile->execute([':path' => $certFilePath, ':id' => $businessId]);
    //         }
    //     }
    // }

    // Upload images
    if (isset($_FILES['images'])) {
        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                $filename = 'restaurant_' . $businessId . '_' . time() . '_' . $i . '.' . $ext;
                if (move_uploaded_file($tmp, $imageDir . $filename)) {
                    $isPrimary = ($i === 0) ? 1 : 0;
                    $imgStmt = $db->prepare("INSERT INTO business_images (business_id, image_url, is_primary, uploaded_by) VALUES (:bid, :url, :primary, :uid)");
                    $imgStmt->execute([
                        ':bid' => $businessId,
                        ':url' => 'uploads/images/' . $filename,
                        ':primary' => $isPrimary,
                        ':uid' => $_SESSION['user_id']
                    ]);
                }
            }
        }
    }
    
    // Log activity
    $logStmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, new_data, ip_address, created_at) 
                             VALUES (:user_id, 'BUSINESS_ADDED', 'halal_businesses', :record_id, :new_data, :ip, NOW())");
    $logStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':record_id' => $businessId,
        ':new_data' => json_encode([
            'business_name' => $_POST['business_name'],
            'cert_status' => $finalCertStatus,
            'sent_to_jha' => $sendToJHA
        ]),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    $db->commit();
    
    // Build response message
    $message = "Restaurant added successfully!\n";
    if ($sendToJHA) {
        $message .= "✅ Certificate is COMPLETE and has been sent to JHA for verification.\n";
        $message .= "JHA will review and approve the certificate.";
    } else if ($certNumber || $issuedBy || $issueDate || $expiryDate) {
        $message .= "⚠️ Certificate is INCOMPLETE.\n";
        $message .= "Please complete ALL fields: Certificate Number, Issued By, Issue Date, Expiry Date.\n";
        $message .= "The certificate has NOT been sent to JHA.";
    } else {
        $message .= "No certificate provided. You can add certificate later in edit menu.";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'business_id' => $businessId,
        'sent_to_jha' => $sendToJHA,
        'cert_status' => $finalCertStatus
    ]);
    
} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
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

$businessId = $_POST['id'] ?? 0;

if (!$businessId) {
    echo json_encode(['success' => false, 'message' => 'Business ID required']);
    exit;
}

// Setup directories
$baseDir = dirname(__DIR__);
$uploadDir = $baseDir . '/backend/uploads/';
$imageDir = $uploadDir . 'images/';
$certDir = $uploadDir . 'certificates/';

// Create directories if not exists
if (!file_exists($imageDir)) mkdir($imageDir, 0777, true);
if (!file_exists($certDir)) mkdir($certDir, 0777, true);

// Log for debugging
$debugLog = [];

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->beginTransaction();
    
    // Get old data
    $oldData = $db->prepare("SELECT * FROM halal_businesses WHERE id = :id");
    $oldData->execute([':id' => $businessId]);
    $old = $oldData->fetch(PDO::FETCH_ASSOC);
    
    // Update basic business info
    $query = "UPDATE halal_businesses SET 
              business_name = :business_name,
              category = :category,
              address = :address,
              city = :city,
              prefecture = :prefecture,
              postal_code = :postal_code,
              phone = :phone,
              email = :email,
              website = :website,
              description = :description,
              price_range = :price_range,
              opening_hours = :opening_hours,
              no_pork = :no_pork,
              no_alcohol = :no_alcohol,
              muslim_owner = :muslim_owner,
              latitude = :latitude,
              longitude = :longitude,
              updated_at = NOW()
              WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':id' => $businessId,
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
        ':price_range' => $_POST['price_range'] ?? '$$',
        ':opening_hours' => $_POST['opening_hours'] ?? '',
        ':no_pork' => $_POST['no_pork'] ?? 0,
        ':no_alcohol' => $_POST['no_alcohol'] ?? 0,
        ':muslim_owner' => $_POST['muslim_owner'] ?? 0,
        ':latitude' => !empty($_POST['latitude']) ? $_POST['latitude'] : null,
        ':longitude' => !empty($_POST['longitude']) ? $_POST['longitude'] : null
    ]);
    
    $certSubmitted = false;
    $certFileSaved = false;
    $savedFilePath = null;
    
    // ============ HANDLE CERTIFICATE UPLOAD ============
    // Check for certificate file in both possible field names
    $certFile = null;
    
    if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
        $certFile = $_FILES['certificate'];
        $debugLog[] = "Found certificate file: " . $certFile['name'];
    } elseif (isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] === UPLOAD_ERR_OK) {
        $certFile = $_FILES['certificate_file'];
        $debugLog[] = "Found certificate_file: " . $certFile['name'];
    }
    
    // ============ HANDLE CERTIFICATE UPLOAD ============
    if ($certFile) {
        $ext = strtolower(pathinfo($certFile['name'], PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (in_array($ext, $allowedExt)) {
            // Generate UNIQUE filename
            $timestamp = time();
            $random = substr(md5(uniqid(mt_rand(), true)), 0, 8);
            $certFilename = 'cert_' . $businessId . '_' . $timestamp . '_' . $random . '.' . $ext;
            $destination = $certDir . $certFilename;
            
            if (move_uploaded_file($certFile['tmp_name'], $destination)) {
                $savedFilePath = 'uploads/certificates/' . $certFilename;
                $certFileSaved = true;
                
                // IMPORTANT: Delete ALL old certificate files for this business
                $oldFiles = glob($certDir . 'cert_' . $businessId . '_*');
                $oldRenewFiles = glob($certDir . 'cert_renew_' . $businessId . '_*');
                $allOldFiles = array_merge($oldFiles, $oldRenewFiles);
                
                foreach ($allOldFiles as $oldFile) {
                    if ($oldFile !== $destination && file_exists($oldFile)) {
                        unlink($oldFile);
                        $debugLog[] = "Deleted old file: " . basename($oldFile);
                    }
                }
            }
        }
    }
    
    // Check for certificate data in POST
    $certNumber = $_POST['cert_number'] ?? '';
    $issuedBy = $_POST['issued_by'] ?? 'JHA';
    $issueDate = $_POST['issue_date'] ?? date('Y-m-d');
    $expiryDate = $_POST['expiry_date'] ?? date('Y-m-d', strtotime('+2 years'));
    
    $isNewCertificate = isset($_POST['is_new_certificate']) && $_POST['is_new_certificate'] == '1';
    $isUpdateCertificate = isset($_POST['is_update_certificate']) && $_POST['is_update_certificate'] == '1';
    
    // Update certificate data if provided
    if ($certNumber && !empty($certNumber)) {
        $updateCertSql = "UPDATE halal_businesses SET 
                          cert_number = :cert_number,
                          cert_issued_by = :issued_by,
                          cert_issue_date = :issue_date,
                          cert_expiry_date = :expiry_date,
                          cert_status = 'pending',
                          halal_status = 'pending'";
        
        if ($certFileSaved && $savedFilePath) {
            $updateCertSql .= ", cert_file = :cert_file";
        }
        
        $updateCertSql .= " WHERE id = :id";
        
        $certStmt = $db->prepare($updateCertSql);
        $params = [
            ':cert_number' => $certNumber,
            ':issued_by' => $issuedBy,
            ':issue_date' => $issueDate,
            ':expiry_date' => $expiryDate,
            ':id' => $businessId
        ];
        
        if ($certFileSaved && $savedFilePath) {
            $params[':cert_file'] = $savedFilePath;
        }
        
        $certStmt->execute($params);
        $certSubmitted = true;
        $debugLog[] = "Certificate data updated in database. Cert Number: $certNumber";
    } else {
        $debugLog[] = "No certificate number provided, skipping certificate update";
    }
    
    // Upload new images
    if (isset($_FILES['new_images'])) {
        $imageFiles = $_FILES['new_images'];
        if (isset($imageFiles['tmp_name']) && is_array($imageFiles['tmp_name'])) {
            foreach ($imageFiles['tmp_name'] as $i => $tmp) {
                if ($imageFiles['error'][$i] === UPLOAD_ERR_OK && !empty($tmp)) {
                    $ext = pathinfo($imageFiles['name'][$i], PATHINFO_EXTENSION);
                    $filename = 'restaurant_' . $businessId . '_' . time() . '_' . $i . '.' . $ext;
                    $destination = $imageDir . $filename;
                    if (move_uploaded_file($tmp, $destination)) {
                        $imgStmt = $db->prepare("INSERT INTO business_images (business_id, image_url, uploaded_by) VALUES (:bid, :url, :uid)");
                        $imgStmt->execute([
                            ':bid' => $businessId,
                            ':url' => 'uploads/images/' . $filename,
                            ':uid' => $_SESSION['user_id']
                        ]);
                        $debugLog[] = "Image saved: " . $filename;
                    }
                }
            }
        }
    }
    
    $db->commit();
    
    $message = "Restaurant updated successfully!";
    if ($certSubmitted) {
        $message .= " Certificate has been submitted to JHA for verification.";
        if ($certFileSaved) {
            $message .= " Certificate file uploaded: " . basename($savedFilePath);
        }
    } else {
        if (!empty($certNumber)) {
            $message .= " Certificate data saved (no new file uploaded).";
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'business_id' => $businessId,
        'certificate_submitted' => $certSubmitted,
        'cert_file_saved' => $certFileSaved,
        'cert_file_path' => $savedFilePath,
        'debug' => $debugLog
    ]);
    
} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage(),
        'debug' => $debugLog
    ]);
}
?>
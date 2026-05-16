<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Setup directories
    $baseDir = dirname(__DIR__);
    $uploadDir = $baseDir . '/backend/uploads/';
    $imageDir = $uploadDir . 'images/';
    $certDir = $uploadDir . 'certificates/';
    
    if (!file_exists($imageDir)) mkdir($imageDir, 0777, true);
    if (!file_exists($certDir)) mkdir($certDir, 0777, true);
    
    // Insert basic business info (tanpa certificate dulu)
    $query = "INSERT INTO halal_businesses SET 
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
              halal_status = 'pending',
              created_at = NOW(),
              updated_at = NOW()";
    
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
        ':price_range' => $_POST['price_range'] ?? '$$',
        ':opening_hours' => $_POST['opening_hours'] ?? '',
        ':no_pork' => isset($_POST['no_pork']) ? 1 : 0,
        ':no_alcohol' => isset($_POST['no_alcohol']) ? 1 : 0,
        ':muslim_owner' => isset($_POST['muslim_owner']) ? 1 : 0,
        ':latitude' => !empty($_POST['latitude']) ? $_POST['latitude'] : null,
        ':longitude' => !empty($_POST['longitude']) ? $_POST['longitude'] : null
    ]);
    
    $businessId = $db->lastInsertId();
    
    // ============ HANDLE CERTIFICATE ============
    $certSubmitted = false;
    $certFileSaved = false;
    $savedFilePath = null;
    
    // Ambil data certificate dari POST
    $certNumber = isset($_POST['cert_number']) ? trim($_POST['cert_number']) : '';
    $issuedBy = isset($_POST['issued_by']) ? $_POST['issued_by'] : 'JHA';
    $issueDate = isset($_POST['issue_date']) ? $_POST['issue_date'] : '';
    $expiryDate = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : '';
    
    // Cek apakah ada data certificate yang diisi
    $hasCertificateData = !empty($certNumber);
    
    if ($hasCertificateData) {
        // Validasi semua field certificate harus diisi
        if (empty($certNumber)) {
            echo json_encode(['success' => false, 'message' => 'Certificate Incomplete: Certificate Number is required']);
            exit;
        }
        
        if (empty($issueDate)) {
            echo json_encode(['success' => false, 'message' => 'Certificate Incomplete: Issue Date is required']);
            exit;
        }
        
        if (empty($expiryDate)) {
            echo json_encode(['success' => false, 'message' => 'Certificate Incomplete: Expiry Date is required']);
            exit;
        }
        
        // Handle file upload
        if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
            $certFile = $_FILES['certificate'];
            $ext = strtolower(pathinfo($certFile['name'], PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
            
            if (in_array($ext, $allowedExt)) {
                if ($certFile['size'] > 10 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'Certificate Incomplete: File too large. Max 10MB']);
                    exit;
                }
                
                $timestamp = time();
                $random = substr(md5(uniqid(mt_rand(), true)), 0, 8);
                $certFilename = 'cert_' . $businessId . '_' . $timestamp . '_' . $random . '.' . $ext;
                $destination = $certDir . $certFilename;
                
                if (move_uploaded_file($certFile['tmp_name'], $destination)) {
                    $savedFilePath = 'uploads/certificates/' . $certFilename;
                    $certFileSaved = true;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Certificate Incomplete: Failed to upload certificate file']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Certificate Incomplete: Invalid file type. Please upload PDF, JPG, or PNG']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Certificate Incomplete: Please upload the certificate file (PDF/JPG/PNG)']);
            exit;
        }
        
        // Update database dengan data certificate
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
    }
    
    // Handle images upload
    if (isset($_FILES['images']) && !empty($_FILES['images']['tmp_name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK && !empty($tmp)) {
                $ext = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                $filename = 'restaurant_' . $businessId . '_' . time() . '_' . $i . '.' . $ext;
                if (move_uploaded_file($tmp, $imageDir . $filename)) {
                    $imgStmt = $db->prepare("INSERT INTO business_images (business_id, image_url, uploaded_by) VALUES (:bid, :url, :uid)");
                    $imgStmt->execute([
                        ':bid' => $businessId,
                        ':url' => 'uploads/images/' . $filename,
                        ':uid' => $_SESSION['user_id']
                    ]);
                }
            }
        }
    }
    
    $message = "Restaurant added successfully!";
    if ($certSubmitted) {
        $message = "✅ Restaurant added! Certificate has been submitted to JHA for verification.";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'business_id' => $businessId,
        'certificate_submitted' => $certSubmitted,
        'certificate_saved' => $certFileSaved
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
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

// Check authentication
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = '';
    if (!isset($_FILES['csv_file'])) {
        $errorMessage = 'No file uploaded. Please select a CSV file.';
    } else {
        switch ($_FILES['csv_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $errorMessage = 'File too large (server limit).';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = 'File too large (form limit).';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = 'File only partially uploaded.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = 'No file selected. Please choose a CSV file.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = 'Missing temporary folder.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = 'Failed to write file to disk.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage = 'File upload stopped by extension.';
                break;
            default:
                $errorMessage = 'Unknown upload error.';
        }
    }
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    exit;
}

$file = $_FILES['csv_file'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if ($fileExtension !== 'csv') {
    echo json_encode(['success' => false, 'message' => 'Only CSV files are allowed. Please upload a .csv file.']);
    exit;
}

// Parse CSV file
$csvData = array_map('str_getcsv', file($file['tmp_name']));
if (empty($csvData) || count($csvData) < 2) {
    echo json_encode(['success' => false, 'message' => 'CSV file is empty or invalid format.']);
    exit;
}

$headers = array_shift($csvData);
// Clean headers (remove BOM and trim)
$headers = array_map(function($header) {
    $header = trim($header);
    // Remove BOM if present
    $header = str_replace("\xEF\xBB\xBF", '', $header);
    return $header;
}, $headers);

$imported = 0;
$failed = 0;
$errors = [];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO halal_businesses 
              (business_name, category, address, city, prefecture, phone, email, website,
               description, price_range, opening_hours, halal_status, no_pork, no_alcohol, 
               muslim_owner, created_by, created_at) 
              VALUES 
              (:business_name, :category, :address, :city, :prefecture, :phone, :email, :website,
               :description, :price_range, :opening_hours, :halal_status, :no_pork, :no_alcohol,
               :muslim_owner, :created_by, NOW())";
    
    $stmt = $db->prepare($query);
    
    foreach ($csvData as $rowIndex => $row) {
        // Skip empty rows
        if (empty(array_filter($row))) continue;
        
        // Map CSV columns to database fields
        $data = array_combine($headers, $row);
        if ($data === false) {
            $failed++;
            $errors[] = "Row " . ($rowIndex + 2) . ": Invalid CSV format - headers and row length mismatch";
            continue;
        }
        
        // Validate required fields
        if (empty($data['business_name']) || empty($data['address']) || empty($data['city'])) {
            $failed++;
            $errors[] = "Row " . ($rowIndex + 2) . ": Missing required fields (business_name, address, or city)";
            continue;
        }
        
        // Set default values for optional fields
        $category = isset($data['category']) ? $data['category'] : 'restaurant';
        $price_range = isset($data['price_range']) ? $data['price_range'] : '$$';
        $halal_status = isset($data['halal_status']) ? $data['halal_status'] : 'pending';
        $no_pork = isset($data['no_pork']) ? (int)$data['no_pork'] : 1;
        $no_alcohol = isset($data['no_alcohol']) ? (int)$data['no_alcohol'] : 0;
        $muslim_owner = isset($data['muslim_owner']) ? (int)$data['muslim_owner'] : 0;
        
        // Validate category
        $validCategories = ['restaurant', 'cafe', 'grocery', 'food_truck', 'hotel', 'catering'];
        if (!in_array($category, $validCategories)) {
            $category = 'restaurant';
        }
        
        // Validate price range
        $validPrices = ['$', '$$', '$$$', '$$$$'];
        if (!in_array($price_range, $validPrices)) {
            $price_range = '$$';
        }
        
        // Validate halal status
        $validStatus = ['certified', 'verified', 'pending', 'expired'];
        if (!in_array($halal_status, $validStatus)) {
            $halal_status = 'pending';
        }
        
        $result = $stmt->execute([
            ':business_name' => $data['business_name'],
            ':category' => $category,
            ':address' => $data['address'],
            ':city' => $data['city'],
            ':prefecture' => $data['prefecture'] ?? '',
            ':phone' => $data['phone'] ?? '',
            ':email' => $data['email'] ?? '',
            ':website' => $data['website'] ?? '',
            ':description' => $data['description'] ?? '',
            ':price_range' => $price_range,
            ':opening_hours' => $data['opening_hours'] ?? '',
            ':halal_status' => $halal_status,
            ':no_pork' => $no_pork,
            ':no_alcohol' => $no_alcohol,
            ':muslim_owner' => $muslim_owner,
            ':created_by' => $_SESSION['user_id']
        ]);
        
        if ($result) {
            $imported++;
        } else {
            $failed++;
            $errors[] = "Row " . ($rowIndex + 2) . ": Database insert failed";
        }
    }
    
    $message = "Import completed: $imported records imported, $failed failed";
    if (!empty($errors) && $failed > 0) {
        $message .= ". Errors: " . implode("; ", array_slice($errors, 0, 5));
    }
    
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'failed' => $failed,
        'errors' => $errors,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
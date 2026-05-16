<?php
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get search parameters
$search_term = isset($_GET['q']) ? $_GET['q'] : (isset($_POST['q']) ? $_POST['q'] : '');
$category = isset($_GET['category']) ? $_GET['category'] : '';
$city = isset($_GET['city']) ? $_GET['city'] : '';
$halal_status = isset($_GET['halal_status']) ? $_GET['halal_status'] : '';
$price_range = isset($_GET['price_range']) ? $_GET['price_range'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build search query
    $query = "SELECT b.*, 
              (SELECT COUNT(*) FROM certification_documents cd WHERE cd.business_id = b.id AND cd.verification_status = 'verified') as cert_count,
              (SELECT MIN(expiry_date) FROM certification_documents cd WHERE cd.business_id = b.id AND cd.verification_status = 'verified') as earliest_expiry
              FROM halal_businesses b 
              WHERE 1=1";
    
    $params = [];
    
    // Full-text search
    if (!empty($search_term)) {
        $query .= " AND (b.business_name LIKE :search 
                   OR b.address LIKE :search 
                   OR b.city LIKE :search 
                   OR b.description LIKE :search)";
        $params[':search'] = "%$search_term%";
    }
    
    // Filter by category
    if (!empty($category)) {
        $query .= " AND b.category = :category";
        $params[':category'] = $category;
    }
    
    // Filter by city
    if (!empty($city)) {
        $query .= " AND b.city LIKE :city";
        $params[':city'] = "%$city%";
    }
    
    // Filter by halal status
    if (!empty($halal_status)) {
        $query .= " AND b.halal_status = :halal_status";
        $params[':halal_status'] = $halal_status;
    } else {
        // Default: show only certified and verified
        $query .= " AND b.halal_status IN ('certified', 'verified')";
    }
    
    // Filter by price range
    if (!empty($price_range)) {
        $query .= " AND b.price_range = :price_range";
        $params[':price_range'] = $price_range;
    }
    
    // Add sorting
    $query .= " ORDER BY CASE 
                WHEN b.halal_status = 'certified' THEN 1
                WHEN b.halal_status = 'verified' THEN 2
                ELSE 3 END, b.business_name ASC";
    
    // Get total count
    $countQuery = str_replace("SELECT b.*, (SELECT COUNT(*) FROM certification_documents cd WHERE cd.business_id = b.id AND cd.verification_status = 'verified') as cert_count, (SELECT MIN(expiry_date) FROM certification_documents cd WHERE cd.business_id = b.id AND cd.verification_status = 'verified') as earliest_expiry", "SELECT COUNT(*) as total", $query);
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Add pagination
    $query .= " LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get additional data for each business
    foreach ($businesses as &$business) {
        // Get latest certificate
        $certQuery = "SELECT * FROM certification_documents 
                      WHERE business_id = :business_id 
                      AND verification_status = 'verified'
                      ORDER BY expiry_date DESC LIMIT 1";
        $certStmt = $db->prepare($certQuery);
        $certStmt->execute([':business_id' => $business['id']]);
        $business['latest_certificate'] = $certStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get images
        $imgQuery = "SELECT * FROM business_images WHERE business_id = :business_id LIMIT 3";
        $imgStmt = $db->prepare($imgQuery);
        $imgStmt->execute([':business_id' => $business['id']]);
        $business['images'] = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get menu items
        $menuQuery = "SELECT * FROM menu_items WHERE business_id = :business_id LIMIT 5";
        $menuStmt = $db->prepare($menuQuery);
        $menuStmt->execute([':business_id' => $business['id']]);
        $business['menu_items'] = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $businesses,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_records' => $total,
            'total_pages' => ceil($total / $limit)
        ],
        'filters' => [
            'search_term' => $search_term,
            'category' => $category,
            'city' => $city,
            'halal_status' => $halal_status,
            'price_range' => $price_range
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Search error: ' . $e->getMessage()
    ]);
}
?>
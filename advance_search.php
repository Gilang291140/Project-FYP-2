<?php
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get all search parameters
$filters = [
    'keyword' => $_GET['keyword'] ?? $_POST['keyword'] ?? '',
    'category' => $_GET['category'] ?? $_POST['category'] ?? '',
    'city' => $_GET['city'] ?? $_POST['city'] ?? '',
    'prefecture' => $_GET['prefecture'] ?? $_POST['prefecture'] ?? '',
    'halal_status' => $_GET['halal_status'] ?? $_POST['halal_status'] ?? '',
    'price_range' => $_GET['price_range'] ?? $_POST['price_range'] ?? '',
    'has_certificate' => $_GET['has_certificate'] ?? $_POST['has_certificate'] ?? '',
    'muslim_owned' => $_GET['muslim_owned'] ?? $_POST['muslim_owned'] ?? '',
    'no_alcohol' => $_GET['no_alcohol'] ?? $_POST['no_alcohol'] ?? '',
    'no_pork' => $_GET['no_pork'] ?? $_POST['no_pork'] ?? '',
    'min_rating' => $_GET['min_rating'] ?? $_POST['min_rating'] ?? '',
    'open_now' => $_GET['open_now'] ?? $_POST['open_now'] ?? '',
    'near_location' => $_GET['near_location'] ?? $_POST['near_location'] ?? '',
    'radius_km' => $_GET['radius_km'] ?? $_POST['radius_km'] ?? 5,
    'sort_by' => $_GET['sort_by'] ?? $_POST['sort_by'] ?? 'relevance',
    'sort_order' => $_GET['sort_order'] ?? $_POST['sort_order'] ?? 'DESC',
    'page' => (int)($_GET['page'] ?? $_POST['page'] ?? 1),
    'limit' => (int)($_GET['limit'] ?? $_POST['limit'] ?? 20)
];

$offset = ($filters['page'] - 1) * $filters['limit'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build the main query
    $query = "SELECT b.*, 
              (SELECT COUNT(*) FROM certification_documents cd WHERE cd.business_id = b.id AND cd.verification_status = 'verified') as cert_count,
              (SELECT AVG(rating) FROM reviews WHERE business_id = b.id AND is_approved = 1) as avg_rating,
              (SELECT COUNT(*) FROM reviews WHERE business_id = b.id AND is_approved = 1) as review_count
              FROM halal_businesses b WHERE 1=1";
    
    $params = [];
    
    // Keyword search (full-text)
    if (!empty($filters['keyword'])) {
        $query .= " AND (b.business_name LIKE :keyword 
                   OR b.address LIKE :keyword 
                   OR b.city LIKE :keyword 
                   OR b.description LIKE :keyword)";
        $params[':keyword'] = "%{$filters['keyword']}%";
    }
    
    // Category filter
    if (!empty($filters['category'])) {
        $query .= " AND b.category = :category";
        $params[':category'] = $filters['category'];
    }
    
    // City filter
    if (!empty($filters['city'])) {
        $query .= " AND b.city LIKE :city";
        $params[':city'] = "%{$filters['city']}%";
    }
    
    // Prefecture filter
    if (!empty($filters['prefecture'])) {
        $query .= " AND b.prefecture = :prefecture";
        $params[':prefecture'] = $filters['prefecture'];
    }
    
    // Halal status filter
    if (!empty($filters['halal_status'])) {
        $query .= " AND b.halal_status = :halal_status";
        $params[':halal_status'] = $filters['halal_status'];
    } else {
        $query .= " AND b.halal_status IN ('certified', 'verified')";
    }
    
    // Price range filter
    if (!empty($filters['price_range'])) {
        $query .= " AND b.price_range = :price_range";
        $params[':price_range'] = $filters['price_range'];
    }
    
    // Has certificate filter
    if ($filters['has_certificate'] === 'yes') {
        $query .= " AND EXISTS (SELECT 1 FROM certification_documents cd WHERE cd.business_id = b.id AND cd.verification_status = 'verified')";
    } elseif ($filters['has_certificate'] === 'no') {
        $query .= " AND NOT EXISTS (SELECT 1 FROM certification_documents cd WHERE cd.business_id = b.id AND cd.verification_status = 'verified')";
    }
    
    // Muslim owned filter
    if ($filters['muslim_owned'] === 'yes') {
        $query .= " AND b.muslim_owner = 1";
    }
    
    // No alcohol filter
    if ($filters['no_alcohol'] === 'yes') {
        $query .= " AND b.no_alcohol = 1";
    }
    
    // No pork filter
    if ($filters['no_pork'] === 'yes') {
        $query .= " AND b.no_pork = 1";
    }
    
    // Minimum rating filter
    if (!empty($filters['min_rating'])) {
        $query .= " HAVING avg_rating >= :min_rating OR avg_rating IS NULL";
        $params[':min_rating'] = $filters['min_rating'];
    }
    
    // Location-based search (proximity)
    if (!empty($filters['near_location']) && !empty($filters['radius_km'])) {
        // This requires latitude and longitude to be set in the database
        // For demo, we'll just filter by city if near_location is provided
        $query .= " AND b.city LIKE :near_location";
        $params[':near_location'] = "%{$filters['near_location']}%";
    }
    
    // Sorting
    switch ($filters['sort_by']) {
        case 'rating':
            $query .= " ORDER BY avg_rating {$filters['sort_order']}, b.business_name ASC";
            break;
        case 'name':
            $query .= " ORDER BY b.business_name {$filters['sort_order']}";
            break;
        case 'newest':
            $query .= " ORDER BY b.created_at {$filters['sort_order']}";
            break;
        case 'price':
            $query .= " ORDER BY FIELD(b.price_range, '$', '$$', '$$$', '$$$$') {$filters['sort_order']}";
            break;
        case 'relevance':
        default:
            $query .= " ORDER BY CASE 
                        WHEN b.halal_status = 'certified' THEN 1
                        WHEN b.halal_status = 'verified' THEN 2
                        ELSE 3 END, b.business_name ASC";
            break;
    }
    
    // Get total count for pagination
    $countQuery = str_replace(
        "SELECT b.*, (SELECT COUNT(*) FROM certification_documents cd WHERE cd.business_id = b.id AND cd.verification_status = 'verified') as cert_count, (SELECT AVG(rating) FROM reviews WHERE business_id = b.id AND is_approved = 1) as avg_rating, (SELECT COUNT(*) FROM reviews WHERE business_id = b.id AND is_approved = 1) as review_count",
        "SELECT COUNT(*) as total",
        $query
    );
    
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Add pagination to main query
    $query .= " LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $filters['limit'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get additional data for each business
    foreach ($businesses as &$business) {
        // Get latest valid certificate
        $certQuery = "SELECT * FROM certification_documents 
                      WHERE business_id = :business_id 
                      AND verification_status = 'verified'
                      AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                      ORDER BY expiry_date DESC LIMIT 1";
        $certStmt = $db->prepare($certQuery);
        $certStmt->execute([':business_id' => $business['id']]);
        $business['active_certificate'] = $certStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get images
        $imgQuery = "SELECT * FROM business_images WHERE business_id = :business_id AND is_primary = 1 LIMIT 1";
        $imgStmt = $db->prepare($imgQuery);
        $imgStmt->execute([':business_id' => $business['id']]);
        $business['primary_image'] = $imgStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get menu items
        $menuQuery = "SELECT * FROM menu_items WHERE business_id = :business_id LIMIT 10";
        $menuStmt = $db->prepare($menuQuery);
        $menuStmt->execute([':business_id' => $business['id']]);
        $business['menu'] = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent reviews
        $reviewQuery = "SELECT * FROM reviews WHERE business_id = :business_id AND is_approved = 1 ORDER BY created_at DESC LIMIT 5";
        $reviewStmt = $db->prepare($reviewQuery);
        $reviewStmt->execute([':business_id' => $business['id']]);
        $business['reviews'] = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get available filter options for UI
    $categoryQuery = "SELECT DISTINCT category, COUNT(*) as count FROM halal_businesses GROUP BY category";
    $categoryStmt = $db->query($categoryQuery);
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cityQuery = "SELECT DISTINCT city, COUNT(*) as count FROM halal_businesses GROUP BY city ORDER BY count DESC LIMIT 10";
    $cityStmt = $db->query($cityQuery);
    $cities = $cityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $businesses,
        'pagination' => [
            'current_page' => $filters['page'],
            'per_page' => $filters['limit'],
            'total_records' => $total,
            'total_pages' => ceil($total / $filters['limit'])
        ],
        'filters_applied' => $filters,
        'filter_options' => [
            'categories' => $categories,
            'top_cities' => $cities,
            'price_ranges' => ['$', '$$', '$$$', '$$$$'],
            'halal_statuses' => ['certified', 'verified', 'pending']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Search error: ' . $e->getMessage()
    ]);
}
?>
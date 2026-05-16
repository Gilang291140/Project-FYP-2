<?php
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$city = isset($_GET['city']) ? $_GET['city'] : '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed', 'data' => []]);
        exit;
    }
    
    // Hanya tampilkan data yang sudah di-publish (is_published = 1) DAN certified
    $query = "SELECT b.* FROM halal_businesses b 
              WHERE b.is_published = 1 
              AND b.halal_status = 'certified'";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (b.business_name LIKE :search OR b.city LIKE :search OR b.address LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if (!empty($category)) {
        $query .= " AND b.category = :category";
        $params[':category'] = $category;
    }
    if (!empty($city)) {
        $query .= " AND b.city LIKE :city";
        $params[':city'] = "%$city%";
    }
    
    // Get total count
    $countQuery = str_replace("SELECT b.*", "SELECT COUNT(*) as total", $query);
    $countStmt = $db->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get data with pagination
    $query .= " ORDER BY b.created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get images for each business
    foreach ($businesses as &$business) {
        $imgQuery = "SELECT image_url, is_primary FROM business_images WHERE business_id = :id ORDER BY is_primary DESC LIMIT 1";
        $imgStmt = $db->prepare($imgQuery);
        $imgStmt->execute([':id' => $business['id']]);
        $image = $imgStmt->fetch(PDO::FETCH_ASSOC);
        $business['image_url'] = $image ? '../backend/' . $image['image_url'] : null;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $businesses,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_records' => (int)$total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Public API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}
?>
<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Business ID required']);
    exit;
}

// Admin TIDAK BISA mengubah halal_status (hanya JHA yang bisa)
// Halal status akan tetap sesuai dengan yang sudah ada

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Update hanya data dasar, TIDAK mengubah halal_status
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
    $result = $stmt->execute([
        ':id' => $input['id'],
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
        ':price_range' => $input['price_range'] ?? '$$',
        ':opening_hours' => $input['opening_hours'] ?? '',
        ':no_pork' => isset($input['no_pork']) ? 1 : 0,
        ':no_alcohol' => isset($input['no_alcohol']) ? 1 : 0,
        ':muslim_owner' => isset($input['muslim_owner']) ? 1 : 0,
        ':latitude' => $input['latitude'] ?? null,
        ':longitude' => $input['longitude'] ?? null
    ]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Restaurant updated successfully. Status remains unchanged (managed by JHA).'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update restaurant']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
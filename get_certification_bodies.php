<?php
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, name, country FROM certification_bodies ORDER BY name";
    $stmt = $db->query($query);
    $bodies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $bodies
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'data' => []]);
}
?>
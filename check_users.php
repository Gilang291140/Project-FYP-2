<?php
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if (!isset($_GET['username'])) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id FROM users WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->execute([':username' => $_GET['username']]);
    
    echo json_encode(['exists' => $stmt->rowCount() > 0]);
    
} catch (Exception $e) {
    echo json_encode(['exists' => false]);
}
?>
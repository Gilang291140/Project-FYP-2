<?php
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);

$required = ['full_name', 'email', 'username', 'password', 'role'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty(trim($input[$field]))) {
        echo json_encode(['success' => false, 'message' => "$field is required"]);
        exit;
    }
}

if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if (strlen($input['password']) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

$role = $input['role'] === 'jha' ? 'verifier' : 'admin';
if (!in_array($role, ['admin', 'verifier'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $checkQuery = "SELECT id FROM users WHERE username = :username OR email = :email";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([
        ':username' => $input['username'],
        ':email' => $input['email']
    ]);
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        exit;
    }
    
    $password_hash = password_hash($input['password'], PASSWORD_DEFAULT);
    $query = "INSERT INTO users (username, email, password_hash, full_name, role, created_at) 
              VALUES (:username, :email, :password_hash, :full_name, :role, NOW())";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([
        ':username' => $input['username'],
        ':email' => $input['email'],
        ':password_hash' => $password_hash,
        ':full_name' => $input['full_name'],
        ':role' => $role
    ])) {
        echo json_encode(['success' => true, 'message' => 'Registration successful! Please login.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
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

$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input['username']) || !isset($input['password'])) {
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, username, email, password_hash, full_name, role FROM users 
              WHERE (username = :username OR email = :username) AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':username' => $input['username']]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($input['password'], $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        
        $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([':id' => $user['id']]);
        
        // Tentukan dashboard berdasarkan role
        if ($user['role'] === 'verifier') {
            $dashboard = 'verifier-dashboard.html';
        } else {
            $dashboard = 'admin-dashboard.html';
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ],
            'redirect' => $dashboard
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
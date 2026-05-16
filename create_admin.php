<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../backend/config/database.php';

echo "<html><head><title>Create Test Users</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f0f0f0;}
.success{color:green;font-weight:bold;}
.error{color:red;}
.box{background:white;padding:20px;border-radius:10px;margin:10px 0;}</style>";
echo "</head><body>";

echo "<div class='box'>";
echo "<h2>🔧 Create Test Users</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed!");
    }
    
    echo "<p class='success'>✅ Database connected!</p>";
    
    // Check if jha_permissions table exists
    $tableCheck = "SHOW TABLES LIKE 'jha_permissions'";
    $tableResult = $db->query($tableCheck);
    
    if ($tableResult->rowCount() == 0) {
        echo "<p>Creating jha_permissions table...</p>";
        $createTable = "CREATE TABLE IF NOT EXISTS jha_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            can_verify BOOLEAN DEFAULT TRUE,
            can_approve BOOLEAN DEFAULT TRUE,
            can_reject BOOLEAN DEFAULT TRUE,
            max_verifications_per_day INT DEFAULT 50,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $db->exec($createTable);
        echo "<p class='success'>✅ jha_permissions table created!</p>";
    }
    
    // Create Admin User
    $adminPassword = 'admin123';
    $adminHash = password_hash($adminPassword, PASSWORD_DEFAULT);
    
    $checkAdmin = "SELECT id FROM users WHERE username = 'admin'";
    $checkStmt = $db->query($checkAdmin);
    
    if ($checkStmt->rowCount() == 0) {
        $insertAdmin = "INSERT INTO users (username, email, password_hash, full_name, role) 
                        VALUES ('admin', 'admin@halalfood.jp', :hash, 'System Administrator', 'admin')";
        $stmt = $db->prepare($insertAdmin);
        $stmt->execute([':hash' => $adminHash]);
        echo "<p class='success'>✅ Admin user created!</p>";
    } else {
        echo "<p>⚠️ Admin user already exists</p>";
    }
    
    // Create JHA User
    $jhaPassword = 'jha123';
    $jhaHash = password_hash($jhaPassword, PASSWORD_DEFAULT);
    
    $checkJHA = "SELECT id FROM users WHERE username = 'jha_verifier'";
    $checkJHAStmt = $db->query($checkJHA);
    
    if ($checkJHAStmt->rowCount() == 0) {
        $insertJHA = "INSERT INTO users (username, email, password_hash, full_name, role) 
                      VALUES ('jha_verifier', 'jha@halalfood.jp', :hash, 'JHA Halal Verifier', 'jha')";
        $stmt = $db->prepare($insertJHA);
        $stmt->execute([':hash' => $jhaHash]);
        $jhaId = $db->lastInsertId();
        
        // Add permissions for JHA
        $insertPerm = "INSERT INTO jha_permissions (user_id) VALUES (:user_id)";
        $permStmt = $db->prepare($insertPerm);
        $permStmt->execute([':user_id' => $jhaId]);
        
        echo "<p class='success'>✅ JHA Verifier user created!</p>";
    } else {
        echo "<p>⚠️ JHA user already exists</p>";
    }
    
    // Display users
    $usersQuery = "SELECT id, username, email, full_name, role FROM users";
    $users = $db->query($usersQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Current Users:</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%'>";
    echo "<tr style='background:#333;color:white'><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th></tr>";
    foreach ($users as $user) {
        $roleClass = $user['role'] === 'jha' ? 'style="color:blue"' : 'style="color:green"';
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td $roleClass><strong>{$user['role']}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<div style='margin-top:20px;padding:15px;background:#e8f5e9;border-radius:10px'>";
    echo "<h3>🔐 Login Credentials:</h3>";
    echo "<p><strong>Admin User:</strong><br>";
    echo "Username: <code>admin</code><br>";
    echo "Password: <code>admin123</code><br>";
    echo "Dashboard: Admin Dashboard (No Verify/Approve)</p>";
    
    echo "<p><strong>JHA User:</strong><br>";
    echo "Username: <code>jha_verifier</code><br>";
    echo "Password: <code>jha123</code><br>";
    echo "Dashboard: JHA Dashboard (With Verify/Approve)</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
echo "</body></html>";
?>
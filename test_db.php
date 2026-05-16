<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../backend/config/database.php';

echo "<html><head><title>Database Test</title>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f0f0f0; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; }
    .box { background: white; padding: 20px; border-radius: 10px; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #4CAF50; color: white; }
</style>";
echo "</head><body>";

echo "<div class='box'>";
echo "<h2>🔧 Database Connection Test</h2>";

// Test 1: Database Connection
echo "<h3>Test 1: Database Connection</h3>";
try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "<p class='success'>✅ Database connected successfully!</p>";
        echo "<p class='info'>Connection info: " . get_class($db) . "</p>";
    } else {
        echo "<p class='error'>❌ Database connection failed!</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Connection error: " . $e->getMessage() . "</p>";
}

// Test 2: Check Users Table
echo "<h3>Test 2: Check Users Table</h3>";
try {
    $query = "SHOW TABLES LIKE 'users'";
    $stmt = $db->query($query);
    
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✅ Users table exists</p>";
        
        // Show table structure
        $columns = $db->query("DESCRIBE users");
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count users
        $countQuery = "SELECT COUNT(*) as total FROM users";
        $countStmt = $db->query($countQuery);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Total users: <strong>" . $total['total'] . "</strong></p>";
        
        // Show all users
        $usersQuery = "SELECT id, username, email, full_name, role, is_active FROM users";
        $usersStmt = $db->query($usersQuery);
        $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($users) > 0) {
            echo "<h4>Current Users:</h4>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Full Name</th><th>Role</th><th>Active</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>{$user['id']}</td>";
                echo "<td>{$user['username']}</td>";
                echo "<td>{$user['email']}</td>";
                echo "<td>{$user['full_name']}</td>";
                echo "<td>{$user['role']}</td>";
                echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p class='error'>❌ Users table does not exist!</p>";
        echo "<p>Please run the SQL script to create tables.</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error checking users table: " . $e->getMessage() . "</p>";
}

// Test 3: Test Login Query
echo "<h3>Test 3: Test Login Query</h3>";
try {
    $testUsername = 'admin';
    $query = "SELECT id, username, email, password_hash, full_name, role, is_active 
              FROM users 
              WHERE (username = :username OR email = :username) AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':username' => $testUsername]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<p class='success'>✅ Query successful! User found: " . $user['username'] . "</p>";
        echo "<p>Password hash: " . substr($user['password_hash'], 0, 50) . "...</p>";
        
        // Test password verification
        $testPassword = 'admin123';
        $verify = password_verify($testPassword, $user['password_hash']);
        echo "<p>Password verification for 'admin123': " . ($verify ? "<span class='success'>SUCCESS</span>" : "<span class='error'>FAILED</span>") . "</p>";
    } else {
        echo "<p class='error'>❌ User 'admin' not found or inactive</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Query error: " . $e->getMessage() . "</p>";
}

echo "</div>";
echo "</body></html>";
?>
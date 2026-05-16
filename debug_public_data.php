<?php
require_once '../backend/config/database.php';

header("Content-Type: text/html; charset=utf-8");

echo "<html><head><title>Debug Public Data</title>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f0f0f0; }
    .success { color: green; font-weight: bold; }
    .error { color: red; }
    .box { background: white; padding: 20px; border-radius: 10px; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #4CAF50; color: white; }
</style>";
echo "</head><body>";

echo "<div class='box'>";
echo "<h2>🔍 Debug Public Website Data</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed!");
    }
    
    echo "<p class='success'>✅ Database connected!</p>";
    
    // 1. Cek semua data yang certified
    echo "<h3>📋 All Certified Businesses:</h3>";
    $query = "SELECT id, business_name, halal_status, cert_status, is_published, published_at 
              FROM halal_businesses 
              WHERE halal_status = 'certified'";
    $stmt = $db->query($query);
    $certified = $stmt->fetchAll();
    
    if (count($certified) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Business Name</th><th>Halal Status</th><th>Cert Status</th><th>Is Published</th><th>Published At</th></tr>";
        foreach ($certified as $b) {
            $publishedStatus = $b['is_published'] ? '<span class="success">✅ YES</span>' : '<span class="error">❌ NO</span>';
            echo "<tr>";
            echo "<td>{$b['id']}</td>";
            echo "<td>{$b['business_name']}</strong></td>";
            echo "<td>{$b['halal_status']}</td>";
            echo "<td>{$b['cert_status']}</td>";
            echo "<td>$publishedStatus</strong></td>";
            echo "<td>{$b['published_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No certified businesses found!</p>";
    }
    
    // 2. Cek data yang akan tampil di public
    echo "<h3>📋 Data yang Akan Tampil di Public Website:</h3>";
    $publicQuery = "SELECT id, business_name, halal_status, is_published 
                    FROM halal_businesses 
                    WHERE is_published = 1 AND halal_status = 'certified'";
    $publicStmt = $db->query($publicQuery);
    $publicData = $publicStmt->fetchAll();
    
    if (count($publicData) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Business Name</th><th>Halal Status</th><th>Is Published</th></tr>";
        foreach ($publicData as $b) {
            echo "<tr>";
            echo "<td>{$b['id']}</td>";
            echo "<td>{$b['business_name']}</strong></td>";
            echo "<td>{$b['halal_status']}</td>";
            echo "<td><span class='success'>YES</span></td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p class='success'>✅ " . count($publicData) . " businesses will appear on public website!</p>";
    } else {
        echo "<p class='error'>❌ No data will appear on public website. Please publish some certificates first.</p>";
    }
    
    // 3. Cek API response
    echo "<h3>📡 API Test:</h3>";
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $apiUrl = $scheme . '://' . $host . $scriptDir . '/public_get_businesses.php';
    $apiResponse = file_get_contents($apiUrl);
    $apiData = json_decode($apiResponse, true);
    
    if ($apiData && $apiData['success']) {
        echo "<p>API Response: " . count($apiData['data']) . " businesses found</p>";
        if (count($apiData['data']) > 0) {
            echo "<pre>";
            print_r(array_slice($apiData['data'], 0, 3));
            echo "</pre>";
        }
    } else {
        echo "<p class='error'>API Error: " . ($apiData['message'] ?? 'Unknown error') . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
echo "</body></html>";
?>
<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: text/html; charset=utf-8");

echo "<html><head><title>Debug Expired Certificates</title>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #f0f0f0; }
    .success { color: green; font-weight: bold; }
    .error { color: red; }
    .warning { color: orange; }
    .box { background: white; padding: 20px; border-radius: 10px; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #4CAF50; color: white; }
</style>";
echo "</head><body>";

echo "<div class='box'>";
echo "<h2>🔍 Debug Expired Certificates</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed!");
    }
    
    echo "<p class='success'>✅ Database connected!</p>";
    
    // 1. Show all certificates
    echo "<h3>📋 All Certificates:</h3>";
    $query = "SELECT cd.id, cd.business_id, cd.certificate_number, cd.expiry_date, cd.verification_status, 
                     b.business_name, b.halal_status as business_status
              FROM certification_documents cd
              LEFT JOIN halal_businesses b ON cd.business_id = b.id
              ORDER BY cd.id DESC";
    $stmt = $db->query($query);
    $certificates = $stmt->fetchAll();
    
    if (count($certificates) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Business</th><th>Certificate #</th><th>Expiry Date</th><th>Cert Status</th><th>Business Status</th><th>Expired?</th></tr>";
        foreach ($certificates as $cert) {
            $isExpired = ($cert['expiry_date'] && $cert['expiry_date'] < date('Y-m-d')) ? true : false;
            $expiredText = $isExpired ? '<span class="error">YES (Expired)</span>' : '<span class="success">NO</span>';
            $expiryColor = $isExpired ? 'style="color:red;font-weight:bold"' : '';
            echo "<tr>";
            echo "<td>{$cert['id']}</td>";
            echo "<td>{$cert['business_name']}</td>";
            echo "<td>{$cert['certificate_number']}</td>";
            echo "<td $expiryColor>{$cert['expiry_date']}</td>";
            echo "<td>{$cert['verification_status']}</td>";
            echo "<td>{$cert['business_status']}</td>";
            echo "<td>$expiredText</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No certificates found!</p>";
    }
    
    // 2. Show expired certificates
    echo "<h3>📋 Expired Certificates (based on expiry_date < CURDATE()):</h3>";
    $expiredQuery = "SELECT cd.*, b.business_name 
                     FROM certification_documents cd
                     LEFT JOIN halal_businesses b ON cd.business_id = b.id
                     WHERE cd.expiry_date IS NOT NULL 
                     AND cd.expiry_date < CURDATE()";
    $expiredStmt = $db->query($expiredQuery);
    $expiredCerts = $expiredStmt->fetchAll();
    
    if (count($expiredCerts) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Business</th><th>Certificate #</th><th>Expiry Date</th><th>Current Status</th></tr>";
        foreach ($expiredCerts as $cert) {
            echo "<tr>";
            echo "<td>{$cert['id']}</td>";
            echo "<td>{$cert['business_name']}</td>";
            echo "<td>{$cert['certificate_number']}</td>";
            echo "<td style='color:red'><strong>{$cert['expiry_date']}</strong></td>";
            echo "<td>{$cert['verification_status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Offer to fix
        echo "<form method='POST' action=''>";
        echo "<input type='hidden' name='fix_expired' value='1'>";
        echo "<button type='submit' style='background:#dc3545;color:white;padding:10px;margin-top:10px'>Fix Expired Certificates</button>";
        echo "</form>";
        
        if (isset($_POST['fix_expired'])) {
            $updateCerts = "UPDATE certification_documents 
                           SET verification_status = 'expired'
                           WHERE expiry_date < CURDATE() 
                           AND verification_status != 'expired'";
            $updated = $db->exec($updateCerts);
            echo "<p class='success'>✅ Updated $updated certificates to EXPIRED</p>";
            
            $updateBusiness = "UPDATE halal_businesses b
                              SET b.halal_status = 'expired'
                              WHERE EXISTS (
                                  SELECT 1 FROM certification_documents cd 
                                  WHERE cd.business_id = b.id 
                                  AND cd.expiry_date < CURDATE()
                              ) AND b.halal_status != 'expired'";
            $updatedBiz = $db->exec($updateBusiness);
            echo "<p class='success'>✅ Updated $updatedBiz businesses to EXPIRED</p>";
            echo "<meta http-equiv='refresh' content='2'>";
        }
    } else {
        echo "<p>No expired certificates found.</p>";
    }
    
    // 3. Show businesses with expired status
    echo "<h3>📋 Businesses with 'expired' status:</h3>";
    $businessQuery = "SELECT id, business_name, halal_status, updated_at 
                      FROM halal_businesses 
                      WHERE halal_status = 'expired'";
    $businessStmt = $db->query($businessQuery);
    $expiredBiz = $businessStmt->fetchAll();
    
    if (count($expiredBiz) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Business Name</th><th>Status</th><th>Updated At</th></tr>";
        foreach ($expiredBiz as $biz) {
            echo "<tr>";
            echo "<td>{$biz['id']}</td>";
            echo "<td>{$biz['business_name']}</td>";
            echo "<td style='color:red'><strong>{$biz['halal_status']}</strong></td>";
            echo "<td>{$biz['updated_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No businesses with 'expired' status.</p>";
    }
    
    // 4. Summary
    echo "<h3>📊 Summary:</h3>";
    $stats = $db->query("SELECT 
        COUNT(*) as total_certificates,
        SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN verification_status = 'expired' THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM certification_documents")->fetch();
    
    echo "<ul>";
    echo "<li>Total Certificates: {$stats['total_certificates']}</li>";
    echo "<li>Verified: {$stats['verified']}</li>";
    echo "<li>Expired: <strong style='color:red'>{$stats['expired']}</strong></li>";
    echo "<li>Pending: {$stats['pending']}</li>";
    echo "<li>Current Date: <strong>" . date('Y-m-d') . "</strong></li>";
    echo "</ul>";
    
    echo "<p><a href='../frontend/admin-dashboard.html' class='btn'>Back to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
echo "</body></html>";
?>
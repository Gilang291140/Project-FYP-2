<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: text/html; charset=utf-8");

echo "<html><head><title>Manual Update Expired Certificates</title>";
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
echo "<h2>🔧 Manual Update Expired Certificates</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed!");
    }
    
    echo "<p class='success'>✅ Database connected!</p>";
    
    // 1. Show expired certificates before update
    echo "<h3>📋 Expired Certificates (Before Update):</h3>";
    $query = "SELECT cd.id, cd.certificate_number, cd.expiry_date, cd.verification_status, b.business_name 
              FROM certification_documents cd
              LEFT JOIN halal_businesses b ON cd.business_id = b.id
              WHERE cd.expiry_date < CURDATE() AND cd.verification_status = 'verified'";
    $stmt = $db->query($query);
    $expiredCerts = $stmt->fetchAll();
    
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
    } else {
        echo "<p>No expired certificates found.</p>";
    }
    
    // 2. Update expired certificates
    echo "<h3>🔄 Updating Expired Certificates...</h3>";
    $updateCerts = "UPDATE certification_documents 
                    SET verification_status = 'expired',
                        rejection_reason = CONCAT('Auto-expired on ', CURDATE())
                    WHERE verification_status = 'verified' 
                    AND expiry_date IS NOT NULL 
                    AND expiry_date < CURDATE()";
    $updatedCerts = $db->exec($updateCerts);
    echo "<p class='success'>✅ Updated $updatedCerts certificate(s) to EXPIRED</p>";
    
    // 3. Update expired businesses
    echo "<h3>🔄 Updating Expired Businesses...</h3>";
    $updateBusiness = "UPDATE halal_businesses b
                       SET b.halal_status = 'expired',
                           b.updated_at = NOW()
                       WHERE b.id IN (
                           SELECT DISTINCT business_id 
                           FROM certification_documents 
                           WHERE verification_status = 'expired'
                       ) AND b.halal_status != 'expired'";
    $updatedBusiness = $db->exec($updateBusiness);
    echo "<p class='success'>✅ Updated $updatedBusiness business(es) to EXPIRED</p>";
    
    // 4. Show updated data
    echo "<h3>📋 Expired Certificates (After Update):</h3>";
    $query2 = "SELECT cd.id, cd.certificate_number, cd.expiry_date, cd.verification_status, b.business_name, b.halal_status 
               FROM certification_documents cd
               LEFT JOIN halal_businesses b ON cd.business_id = b.id
               WHERE cd.verification_status = 'expired'";
    $stmt2 = $db->query($query2);
    $expiredAfter = $stmt2->fetchAll();
    
    if (count($expiredAfter) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Business</th><th>Certificate #</th><th>Expiry Date</th><th>Cert Status</th><th>Business Status</th></tr>";
        foreach ($expiredAfter as $cert) {
            echo "<tr>";
            echo "<td>{$cert['id']}</td>";
            echo "<td>{$cert['business_name']}</td>";
            echo "<td>{$cert['certificate_number']}</td>";
            echo "<td style='color:red'><strong>{$cert['expiry_date']}</strong></td>";
            echo "<td><span style='color:red;font-weight:bold'>{$cert['verification_status']}</span></td>";
            echo "<td><span style='color:red;font-weight:bold'>{$cert['halal_status']}</span></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 5. Summary
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
    echo "</ul>";
    
    echo "<p><a href='../frontend/admin-dashboard.html' class='btn'>Back to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
echo "</body></html>";
?>
<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: text/html; charset=utf-8");

echo "<html><head><title>Check Admin Submissions</title>";
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
echo "<h2>📋 Cek Data Admin Submissions</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed!");
    }
    
    echo "<p class='success'>✅ Database connected!</p>";
    
    // Cek semua admin_uploaded_documents
    $query = "SELECT d.*, b.business_name, u.full_name as submitted_by_name 
              FROM admin_uploaded_documents d
              LEFT JOIN halal_businesses b ON d.business_id = b.id
              LEFT JOIN users u ON d.submitted_by = u.id
              ORDER BY d.created_at DESC";
    
    $stmt = $db->query($query);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($documents) > 0) {
        echo "<p class='success'>✅ Ditemukan " . count($documents) . " dokumen dari Admin!</p>";
        echo "能";
        echo "<tr><th>ID</th><th>Business</th><th>Document Title</th><th>Status</th><th>Submitted By</th><th>Created At</th>貌";
        foreach ($documents as $doc) {
            $statusBadge = $doc['verification_status'] === 'pending' ? '🟡 Pending' : ($doc['verification_status'] === 'approved' ? '🟢 Approved' : '🔴 Rejected');
            echo "<tr>";
            echo "<td>{$doc['id']}</td>";
            echo "<td>{$doc['business_name']}</strong></td>";
            echo "<td>{$doc['document_title']}</td>";
            echo "<td>{$statusBadge}</td>";
            echo "<td>{$doc['submitted_by_name']}</td>";
            echo "<td>{$doc['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Cek khusus yang pending
        $pendingQuery = "SELECT COUNT(*) as count FROM admin_uploaded_documents WHERE verification_status = 'pending'";
        $pendingStmt = $db->query($pendingQuery);
        $pending = $pendingStmt->fetch()['count'];
        echo "<p>Jumlah dokumen PENDING: <strong>$pending</strong></p>";
        
    } else {
        echo "<p class='error'>❌ Tidak ada dokumen dari Admin di database!</p>";
    }
    
    // Cek session login
    echo "<h3>Session Info:</h3>";
    if (isset($_SESSION['user_id'])) {
        echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
        echo "<p>Role: " . $_SESSION['role'] . "</p>";
    } else {
        echo "<p class='error'>❌ Tidak ada session! Silakan login sebagai JHA terlebih dahulu.</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div>";
echo "</body></html>";
?>
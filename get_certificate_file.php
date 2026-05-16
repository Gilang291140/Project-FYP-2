<?php
session_start();
require_once '../backend/config/database.php';

if (!isset($_GET['id'])) {
    showFallback('', 'Business ID required');
    exit;
}

$businessId = (int)$_GET['id'];

// Disable cache to always get latest file
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get certificate info including cert_file path
    $query = "SELECT id, business_name, cert_number, cert_issued_by, cert_issue_date, cert_expiry_date, cert_file 
              FROM halal_businesses WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $businessId]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$business || empty($business['cert_number'])) {
        showFallback('', 'Certificate not found', $business['business_name'] ?? 'Unknown');
        exit;
    }

    $baseDir = dirname(__DIR__);
    $certDir = $baseDir . '/backend/uploads/certificates/';
    $foundFile = null;
    
    // METHOD 1: Gunakan path dari database jika ada
    if (!empty($business['cert_file'])) {
        $possiblePaths = [
            $baseDir . '/' . $business['cert_file'],
            $baseDir . '/backend/' . $business['cert_file'],
            $certDir . basename($business['cert_file']),
            $business['cert_file']
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                // Check file size - ignore files smaller than 10KB (likely placeholders)
                $fileSize = filesize($path);
                if ($fileSize > 10000) { // 10KB minimum
                    $foundFile = $path;
                    break;
                }
            }
        }
    }
    
    // METHOD 2: Cari semua file certificate untuk business ini dan ambil yang TERBARU & TERBESAR
    if (!$foundFile) {
        $pattern = $certDir . 'cert_' . $businessId . '_*';
        $files = glob($pattern);
        
        // Also check for renew certificates
        $renewPattern = $certDir . 'cert_renew_' . $businessId . '_*';
        $renewFiles = glob($renewPattern);
        $files = array_merge($files, $renewFiles);
        
        if (!empty($files)) {
            // Filter files by size (ignore small/placeholder files)
            $validFiles = array_filter($files, function($file) {
                return filesize($file) > 10000; // Minimum 10KB
            });
            
            if (empty($validFiles)) {
                $validFiles = $files; // Use all if no valid files found
            }
            
            // Sort by file modification time (newest first)
            usort($validFiles, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            $foundFile = $validFiles[0];
        }
    }
    
    // METHOD 3: Cari berdasarkan timestamp tertinggi (paling baru)
    if (!$foundFile) {
        $pattern = $certDir . '*_' . $businessId . '_*';
        $files = glob($pattern);
        
        if (!empty($files)) {
            // Extract timestamp from filename and sort
            usort($files, function($a, $b) {
                preg_match('/_(\d+)_/', $a, $matchesA);
                preg_match('/_(\d+)_/', $b, $matchesB);
                $timeA = isset($matchesA[1]) ? (int)$matchesA[1] : 0;
                $timeB = isset($matchesB[1]) ? (int)$matchesB[1] : 0;
                return $timeB - $timeA;
            });
            $foundFile = $files[0];
        }
    }
    
    // Serve file if found
    if ($foundFile && file_exists($foundFile) && is_readable($foundFile)) {
        $fileSize = filesize($foundFile);
        $mime = mime_content_type($foundFile);
        if (!$mime) {
            $ext = strtolower(pathinfo($foundFile, PATHINFO_EXTENSION));
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png'
            ];
            $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        }
        
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="certificate_' . $business['cert_number'] . '.' . pathinfo($foundFile, PATHINFO_EXTENSION) . '"');
        header('Content-Length: ' . $fileSize);
        readfile($foundFile);
        exit;
    }
    
    // No file found - show certificate info
    showFallback($business['cert_number'], 'Certificate file not found on server', $business['business_name'], $business['cert_issued_by'], $business['cert_issue_date'], $business['cert_expiry_date']);

} catch (Exception $e) {
    error_log('Certificate retrieval error: ' . $e->getMessage());
    showFallback('', 'An error occurred while retrieving the certificate');
}

function showFallback($certNumber, $message, $businessName = '', $issuedBy = '', $issueDate = '', $expiryDate = '') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Certificate Unavailable</title>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; text-align: center; padding: 50px; background: linear-gradient(135deg, #f0f2f5 0%, #e8edf2 100%); margin: 0; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 35px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
            h1 { color: #dc3545; margin-bottom: 20px; }
            .cert-info { background: #f8f9fa; padding: 20px; border-radius: 12px; margin: 25px 0; text-align: left; border-left: 4px solid #ffc107; }
            .cert-info p { margin: 8px 0; }
            .btn { display: inline-block; padding: 12px 25px; background: linear-gradient(135deg, #2e7d32, #1b5e20); color: white; text-decoration: none; border-radius: 30px; margin-top: 10px; border: none; cursor: pointer; font-weight: 600; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(46,125,50,0.3); }
        </style>
    </head>
    <body>
        <div class="container">
            <div style="font-size: 64px; margin-bottom: 20px;">📄</div>
            <h1>Certificate Not Available</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <?php if ($certNumber): ?>
            <div class="cert-info">
                <p><strong>🏢 Business:</strong> <?php echo htmlspecialchars($businessName); ?></p>
                <p><strong>📜 Certificate #:</strong> <?php echo htmlspecialchars($certNumber); ?></p>
                <p><strong>🏛️ Issued By:</strong> <?php echo htmlspecialchars($issuedBy ?: 'JHA'); ?></p>
                <p><strong>📅 Issue Date:</strong> <?php echo htmlspecialchars($issueDate ?: 'N/A'); ?></p>
                <p><strong>⏰ Expiry Date:</strong> <?php echo htmlspecialchars($expiryDate ?: 'N/A'); ?></p>
            </div>
            <?php endif; ?>
            <p><em>Please contact the administrator to upload the certificate file.</em></p>
            <button onclick="history.back()" class="btn">← Go Back</button>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<?php
session_start();
require_once '../backend/config/database.php';

// Get format parameter
$format = isset($_GET['format']) ? $_GET['format'] : 'json';

// Set headers based on format
if ($format === 'excel' || $format === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="halal_businesses_report_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
} else {
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");
}

// Authentication check
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    if ($format === 'excel' || $format === 'csv') {
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['Error', 'Unauthorized access']);
        fclose($output);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    }
    exit;
}

$start_date = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : null;
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build query with filters
    $query = "SELECT 
                b.id,
                b.business_name,
                b.category,
                b.address,
                b.city,
                b.phone,
                b.email,
                b.website,
                b.price_range,
                b.halal_status,
                b.cert_number,
                b.cert_issue_date,
                b.cert_expiry_date,
                b.cert_issued_by,
                b.cert_status,
                b.cert_file,
                b.latitude,
                b.longitude,
                b.description,
                b.opening_hours,
                b.no_pork,
                b.no_alcohol,
                b.muslim_owner,
                b.created_at,
                b.updated_at,
                (SELECT COUNT(*) FROM business_images WHERE business_id = b.id) as image_count
              FROM halal_businesses b
              WHERE 1=1";
    
    $params = [];
    
    // Apply date filters
    if ($start_date) {
        $query .= " AND DATE(b.created_at) >= :start_date";
        $params[':start_date'] = $start_date;
    }
    
    if ($end_date) {
        $query .= " AND DATE(b.created_at) <= :end_date";
        $params[':end_date'] = $end_date;
    }
    
    // Apply status filter
    if ($status_filter) {
        $query .= " AND b.halal_status = :status";
        $params[':status'] = $status_filter;
    }
    
    $query .= " ORDER BY b.id DESC";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary statistics
    $totalCertified = 0;
    $totalVerified = 0;
    $totalPending = 0;
    $totalExpired = 0;
    $expiringSoon = 0;
    $today = new DateTime();
    
    foreach ($data as $row) {
        if ($row['halal_status'] === 'certified') $totalCertified++;
        if ($row['halal_status'] === 'verified') $totalVerified++;
        if ($row['halal_status'] === 'pending') $totalPending++;
        if ($row['halal_status'] === 'expired') $totalExpired++;
        
        // Check expiring soon
        if ($row['cert_expiry_date']) {
            $expiryDate = new DateTime($row['cert_expiry_date']);
            $diff = $today->diff($expiryDate);
            $daysLeft = $expiryDate > $today ? (int)$diff->format('%r%a') : -(int)$diff->format('%r%a');
            if ($daysLeft >= 0 && $daysLeft <= 30) {
                $expiringSoon++;
            }
        }
    }
    
    if ($format === 'excel' || $format === 'csv') {
        // Output CSV
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8
        
        // Add summary row
        fputcsv($output, ['=== HALAL FOOD JAPAN - BUSINESS REPORT ===']);
        fputcsv($output, ['Report Generated:', date('Y-m-d H:i:s')]);
        fputcsv($output, ['']);
        fputcsv($output, ['=== SUMMARY STATISTICS ===']);
        fputcsv($output, ['Total Businesses', count($data)]);
        fputcsv($output, ['Certified', $totalCertified]);
        fputcsv($output, ['Verified', $totalVerified]);
        fputcsv($output, ['Pending', $totalPending]);
        fputcsv($output, ['Expired', $totalExpired]);
        fputcsv($output, ['Expiring Soon (≤30 days)', $expiringSoon]);
        fputcsv($output, ['']);
        fputcsv($output, ['']);
        
        // Headers
        $headers = [
            'ID', 
            'Business Name', 
            'Category', 
            'Address', 
            'City', 
            'Phone', 
            'Email',
            'Website',
            'Price Range', 
            'Halal Status',
            'No Pork',
            'No Alcohol',
            'Muslim Owner',
            'Certificate Number',
            'Certificate Issue Date',
            'Certificate Expiry Date',
            'Certificate Issued By',
            'Certificate Status',
            'Certificate File',
            'Latitude',
            'Longitude',
            'Description',
            'Opening Hours',
            'Images Count',
            'Created At',
            'Updated At'
        ];
        
        fputcsv($output, $headers);
        
        // Data rows
        foreach ($data as $row) {
            // Determine certificate expiry status
            $expiryStatus = '';
            $expiryDisplay = '';
            if ($row['cert_expiry_date']) {
                $expiryDate = new DateTime($row['cert_expiry_date']);
                $diff = $today->diff($expiryDate);
                $daysLeft = $expiryDate > $today ? (int)$diff->format('%r%a') : -(int)$diff->format('%r%a');
                
                if ($daysLeft < 0) {
                    $expiryStatus = 'Expired';
                    $expiryDisplay = 'Expired on ' . $row['cert_expiry_date'];
                } elseif ($daysLeft <= 30) {
                    $expiryStatus = 'Expiring Soon';
                    $expiryDisplay = 'Expires in ' . $daysLeft . ' days (' . $row['cert_expiry_date'] . ')';
                } else {
                    $expiryStatus = 'Valid';
                    $expiryDisplay = 'Valid until ' . $row['cert_expiry_date'];
                }
            } else {
                $expiryStatus = 'No Certificate';
                $expiryDisplay = 'No expiry date set';
            }
            
            fputcsv($output, [
                $row['id'],
                $row['business_name'],
                $row['category'],
                $row['address'],
                $row['city'],
                $row['phone'] ?? '',
                $row['email'] ?? '',
                $row['website'] ?? '',
                $row['price_range'] ?? '$$',
                $row['halal_status'],
                $row['no_pork'] ? 'Yes' : 'No',
                $row['no_alcohol'] ? 'Yes' : 'No',
                $row['muslim_owner'] ? 'Yes' : 'No',
                $row['cert_number'] ?? '',
                $row['cert_issue_date'] ?? '',
                $row['cert_expiry_date'] ?? '',
                $row['cert_issued_by'] ?? '',
                $expiryStatus,
                $row['cert_file'] ?? '',
                $row['latitude'] ?? '',
                $row['longitude'] ?? '',
                strip_tags($row['description'] ?? ''),
                $row['opening_hours'] ?? '',
                $row['image_count'] ?? 0,
                $row['created_at'],
                $row['updated_at'] ?? ''
            ]);
        }
        
        fclose($output);
        exit;
    } else {
        // Return JSON response
        echo json_encode([
            'success' => true, 
            'data' => $data,
            'total' => count($data),
            'summary' => [
                'total_certified' => $totalCertified,
                'total_verified' => $totalVerified,
                'total_pending' => $totalPending,
                'total_expired' => $totalExpired,
                'expiring_soon' => $expiringSoon
            ],
            'filters' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'status' => $status_filter
            ]
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Export Error: " . $e->getMessage());
    if ($format === 'excel' || $format === 'csv') {
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['Error', 'Database error: ' . $e->getMessage()]);
        fclose($output);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    error_log("Export Error: " . $e->getMessage());
    if ($format === 'excel' || $format === 'csv') {
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['Error', $e->getMessage()]);
        fclose($output);
    } else {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
<?php
session_start();
require_once '../backend/config/database.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT b.*, 
              (SELECT COUNT(*) FROM certification_documents WHERE business_id = b.id) as cert_count
              FROM halal_businesses b
              ORDER BY b.created_at DESC";
    $stmt = $db->query($query);
    $data = $stmt->fetchAll();
    
    if ($format === 'excel' || $format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="halal_businesses_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8
        
        // Headers
        fputcsv($output, ['ID', 'Business Name', 'Category', 'Address', 'City', 'Phone', 'Email', 
                          'Price Range', 'Halal Status', 'Certificate #', 'Expiry Date', 'Created At']);
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['business_name'],
                $row['category'],
                $row['address'],
                $row['city'],
                $row['phone'],
                $row['email'],
                $row['price_range'],
                $row['halal_status'],
                $row['cert_number'] ?? '',
                $row['cert_expiry_date'] ?? '',
                $row['created_at']
            ]);
        }
        fclose($output);
    } else {
        echo json_encode(['success' => true, 'data' => $data]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
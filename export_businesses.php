<?php
session_start();
require_once '../backend/config/database.php';
require_once '../backend/middleware/auth_middleware.php';

if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$format = isset($_GET['format']) ? $_GET['format'] : 'json';
$type = isset($_GET['type']) ? $_GET['type'] : 'businesses';

if ($type == 'businesses') {
    $query = "SELECT * FROM halal_businesses ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format == 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="halal_businesses_report.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
    } else {
        echo json_encode(['success' => true, 'data' => $data]);
    }
}
?>
<?php
session_start();
require_once '../backend/config/database.php';

if (!isset($_GET['id'])) {
    die('Document ID required');
}

$docId = $_GET['id'];
$type = $_GET['type'] ?? 'admin';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($type === 'admin') {
        $query = "SELECT file_path, file_name FROM admin_uploaded_documents WHERE id = :id";
    } else {
        $query = "SELECT file_path, file_name FROM certification_documents WHERE id = :id";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $docId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doc && file_exists('../backend/' . $doc['file_path'])) {
        $file = '../backend/' . $doc['file_path'];
        $mime = mime_content_type($file);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $doc['file_name'] . '"');
        readfile($file);
    } else {
        echo "File not found. Path: " . $doc['file_path'] ?? 'unknown';
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
<?php
require_once 'backend/config/database.php';

$database = new Database();
$db = $database->getConnection();

// Password: Gilang123
$hash_gilang12 = password_hash('Gilang123', PASSWORD_DEFAULT);

// Password: gilang1234
$hash_gilang1234 = password_hash('gilang1234', PASSWORD_DEFAULT);

// Update gilang12
$stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE username = 'gilang12'");
$stmt->execute([':hash' => $hash_gilang12]);
echo "Password untuk gilang12 telah direset menjadi 'Gilang123'<br>";

// Update gilang1234
$stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE username = 'gilang1234'");
$stmt->execute([':hash' => $hash_gilang1234]);
echo "Password untuk gilang1234 telah direset menjadi 'gilang1234'<br>";

echo "<br><strong>Kredensial Login:</strong><br>";
echo "gilang12 / Gilang123 (Role: Admin)<br>";
echo "gilang1234 / gilang1234 (Role: Verifier)<br>";
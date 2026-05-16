<?php
$response = file_get_contents('http://localhost/halal_food_japan/api/public_get_businesses.php');
$data = json_decode($response, true);

echo 'API Response:' . PHP_EOL;
echo 'Success: ' . ($data['success'] ? 'true' : 'false') . PHP_EOL;
echo 'Total records: ' . ($data['pagination']['total_records'] ?? 0) . PHP_EOL;
echo 'Data count: ' . count($data['data'] ?? []) . PHP_EOL;

if (count($data['data'] ?? []) > 0) {
    echo 'First business: ' . $data['data'][0]['business_name'] . PHP_EOL;
    echo 'Published status: ' . ($data['data'][0]['is_published'] ? 'YES' : 'NO') . PHP_EOL;
    echo 'Halal status: ' . $data['data'][0]['halal_status'] . PHP_EOL;
} else {
    echo 'No businesses returned' . PHP_EOL;
    echo 'Raw response: ' . substr($response, 0, 200) . PHP_EOL;
}
?>
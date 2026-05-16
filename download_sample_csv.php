<?php
// Set headers untuk download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sample_halal_businesses.csv"');

// Buka output stream
$output = fopen('php://output', 'w');

// Tambahkan BOM untuk Excel (supaya UTF-8 berfungsi)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header CSV
$headers = [
    'business_name',
    'category', 
    'address',
    'city',
    'prefecture',
    'phone',
    'email',
    'website',
    'description',
    'price_range',
    'halal_status',
    'opening_hours'
];

// Tulis header
fputcsv($output, $headers);

// Sample data
$sampleData = [
    [
        'Halal Ramen Tokyo',
        'restaurant',
        'Shinjuku 3-2-1',
        'Tokyo',
        'Tokyo-to',
        '03-1234-5678',
        'info@halalramen.jp',
        'https://halalramen.jp',
        'Authentic Japanese ramen made with 100% halal chicken broth',
        '$$',
        'certified',
        '11:00-22:00'
    ],
    [
        'Osaka Halal Kitchen',
        'restaurant',
        'Dotonbori 1-5-10',
        'Osaka',
        'Osaka-fu',
        '06-8765-4321',
        'info@osakahalal.com',
        'https://osakahalal.com',
        'Traditional Japanese halal cuisine',
        '$$$',
        'verified',
        '10:00-21:00'
    ],
    [
        'Tokyo Halal Mart',
        'grocery',
        'Asakusa 2-3-4',
        'Tokyo',
        'Tokyo-to',
        '03-9876-5432',
        'sales@tokyohalal.com',
        'https://tokyohalal.com',
        'Halal grocery store',
        '$$',
        'certified',
        '09:00-20:00'
    ],
    [
        'Kyoto Muslim Cafe',
        'cafe',
        'Kawaramachi 2-3-4',
        'Kyoto',
        'Kyoto-fu',
        '075-1234-567',
        'cafe@kyotohalal.com',
        'https://kyotohalal.com',
        'Halal cakes and coffee',
        '$',
        'pending',
        '08:00-19:00'
    ],
    [
        'Nagoya Halal Food Truck',
        'food_truck',
        'Sakae 1-2-3',
        'Nagoya',
        'Aichi-ken',
        '052-1234-567',
        'foodtruck@nagoyahalal.com',
        'https://nagoyahalal.com',
        'Halal Turkish kebab',
        '$',
        'verified',
        '11:00-20:00'
    ]
];

// Tulis sample data
foreach ($sampleData as $row) {
    fputcsv($output, $row);
}

// Tutup stream
fclose($output);
exit;
?>
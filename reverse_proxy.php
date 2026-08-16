<?php
header('Content-Type: application/json; charset=utf-8');

$lat = isset($_GET['lat']) ? $_GET['lat'] : null;
$lon = isset($_GET['lon']) ? $_GET['lon'] : null;
if (!$lat || !$lon) {
    echo json_encode(['error' => 'Missing coordinates']);
    exit;
}

// OpenStreetMap Nominatim reverse geocoding API
$url = "https://nominatim.openstreetmap.org/reverse?lat=" . urlencode($lat) . "&lon=" . urlencode($lon) . "&format=jsonv2";

// Use file_get_contents (or cURL) to get OSM result
$options = [
    "http" => [
        "header" => "User-Agent: CustomApp/1.0\r\n"
    ]
];
$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);
if (!$response) {
    echo json_encode(['error' => 'Geocoding failed']);
    exit;
}
echo $response;
?>

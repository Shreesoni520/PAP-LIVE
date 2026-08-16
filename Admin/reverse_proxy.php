<?php
// reverse_proxy.php
if (!isset($_GET['lat']) || !isset($_GET['lon'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}
$lat = $_GET['lat'];
$lon = $_GET['lon'];

$url = "https://nominatim.openstreetmap.org/reverse?lat=$lat&lon=$lon&format=json";
$opts = [
    "http"=> [
        "header"=> "User-Agent: DemoApp/1.0\r\n"
    ]
];
$context = stream_context_create($opts);

header('Content-Type: application/json');
echo file_get_contents($url, false, $context);

<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$q = isset($_GET['q']) ? trim($_GET['q']) : null;

if (!$q || strlen($q) < 3) {
    echo json_encode([]);
    exit;
}

// evitar abuso
usleep(300000);

$url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($q) . "&format=jsonv2&limit=1";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "User-Agent: ReportaEvora/1.0 (teuemail@example.com)"
]);

$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    echo json_encode([]);
    exit;
}

$data = json_decode($response, true);

if (!$data) {
    echo json_encode([]);
    exit;
}

echo json_encode($data);
?>

<?php
// nominatim_proxy.php
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['q'])) {
    echo json_encode([]);
    exit;
}

$url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($_GET['q']) . "&format=json&accept-language=pt";
$opts = [
    "http" => [
        "header" => "User-Agent: DemoApp/1.0\r\n"
    ]
];
$context = stream_context_create($opts);

$result = file_get_contents($url, false, $context);
if ($result === false) {
    echo json_encode([]);
} else {
    echo $result;
}
?>

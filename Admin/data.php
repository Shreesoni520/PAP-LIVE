<?php
session_start();
include 'config.php';
header('Content-Type: application/json; charset=utf-8');

// must be logged in (admin OR funcionário)
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Select all trees, LEFT JOIN estado info (name to color)
$sql = "SELECT
            a.id,
            a.place_name,
            a.latitude,
            a.longitude,
            a.especie,
            a.tipo_intervencao,
            a.estado,
            s.color_name
        FROM arvores a
        LEFT JOIN states s ON a.estado = s.name";

$result = $conn->query($sql);

$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id'               => (int)$row['id'],
            'place_name'       => $row['place_name'],
            'latitude'         => (float)$row['latitude'],
            'longitude'        => (float)$row['longitude'],
            'especie'          => $row['especie'],
            'tipo_intervencao' => $row['tipo_intervencao'],
            'estado'           => $row['estado'],
            'color_name'       => $row['color_name'],
        ];
    }
}

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

<?php
session_start();
require './config.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$estadoColors = [
    'Por tratar'  => '#ef4444',
    'Em análise'  => '#f97316',
    'Em execução' => '#eab308',
    'Concluída'   => '#22c55e',
];

$sql = "
    SELECT 
        id,
        descricao,
        latitude,
        longitude,
        place_name,
        tipo_intervencao,
        estado,
        imagem,
        data_ocorrencia
    FROM ocorrencias_estrada
    WHERE latitude IS NOT NULL
      AND longitude IS NOT NULL
";

$result = $conn->query($sql);
$data = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $estado = $row['estado'] ?? '';
        $color  = $estadoColors[$estado] ?? '#6b7280';

        $data[] = [
            'id'              => (int)$row['id'],
            'descricao'       => $row['descricao'],
            'latitude'        => (float)$row['latitude'],
            'longitude'       => (float)$row['longitude'],
            'place_name'      => $row['place_name'],
            'tipo_intervencao'=> $row['tipo_intervencao'],
            'estado'          => $estado,
            'imagem'          => $row['imagem'],
            'data_ocorrencia' => $row['data_ocorrencia'],
            'color_name'      => $color
        ];
    }
}

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

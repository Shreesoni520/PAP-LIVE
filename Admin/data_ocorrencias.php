<?php
// data_ocorrencias.php
session_start();
include './config.php'; // must define $conn (MySQLi), same as in add_ocorrencias.php

header('Content-Type: application/json; charset=utf-8');

// must be logged in (admin OR funcionário)
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Busca todas as ocorrências com coordenadas
$sql = "
    SELECT
        o.id,
        o.descricao,
        o.latitude,
        o.longitude,
        o.place_name,
        o.tipo_intervencao,
        o.estado,
        o.data_ocorrencia,
        o.imagem
    FROM ocorrencias o
    WHERE o.latitude IS NOT NULL
      AND o.longitude IS NOT NULL
";

$result = $conn->query($sql);

$data = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        // Escolher cor pela coluna 'estado'
        $color = '#6b7280'; // cinza por defeito

        if (!empty($row['estado'])) {
            switch (mb_strtolower($row['estado'], 'UTF-8')) {
                case 'aberta':
                    $color = '#ef4444'; // vermelho
                    break;
                case 'em análise':
                case 'em analise':
                    $color = '#f59e0b'; // laranja
                    break;
                case 'em execução':
                case 'em execucao':
                    $color = '#3b82f6'; // azul
                    break;
                case 'concluída':
                case 'concluida':
                    $color = '#22c55e'; // verde
                    break;
                default:
                    $color = '#6b7280'; // cinza
                    break;
            }
        }

        $data[] = [
            'id'               => (int)$row['id'],
            'descricao'        => $row['descricao'],
            'latitude'         => (float)$row['latitude'],
            'longitude'        => (float)$row['longitude'],
            'place_name'       => $row['place_name'],
            'tipo_intervencao' => $row['tipo_intervencao'],
            'estado'           => $row['estado'],
            'data_ocorrencia'  => $row['data_ocorrencia'],
            'imagem'           => $row['imagem'],
            'color_name'       => $color,
        ];
    }
}

// Devolve JSON
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

<?php
include 'config.php';
header('Content-Type: application/json');

$sql = "
    SELECT
        e.id,
        e.descricao,
        e.latitude,
        e.longitude,
        e.place_name,
        e.tipo_intervencao,
        e.estado,
        e.imagem,
        e.data_ocorrencia,
        s.color_name
    FROM ocorrencias_estrada e
    LEFT JOIN states s ON e.estado = s.name
";

$result = $conn->query($sql);

$data = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'id'              => $row['id'],
            'descricao'       => $row['descricao'],
            'latitude'        => $row['latitude'],
            'longitude'       => $row['longitude'],
            'place_name'      => $row['place_name'],
            'tipo_intervencao'=> $row['tipo_intervencao'],
            'tarefa'          => $row['estado'],
            'imagem'          => $row['imagem'],
            'data_ocorrencia' => $row['data_ocorrencia'],
            'color_name'      => $row['color_name']
        ];
    }
}

echo json_encode($data);
?>

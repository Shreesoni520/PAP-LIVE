<?php
include 'config.php';
header('Content-Type: application/json');

$sql = "
    SELECT
        o.id,
        o.descricao,
        o.latitude,
        o.longitude,
        o.place_name,
        o.tipo_intervencao,
        o.estado,
        o.imagem,
        o.data_ocorrencia,
        s.color_name
    FROM ocorrencias o
    LEFT JOIN states s ON o.estado = s.name
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

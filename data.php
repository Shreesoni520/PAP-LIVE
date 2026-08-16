<?php
include 'config.php';
header('Content-Type: application/json');

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
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id'              => $row['id'],
        'place_name'      => $row['place_name'],
        'latitude'        => $row['latitude'],
        'longitude'       => $row['longitude'],
        'especie'         => $row['especie'],
        'tipo_intervencao'=> $row['tipo_intervencao'],
        'tarefa'          => $row['estado'],
        'color_name'      => $row['color_name']
    ];
}
echo json_encode($data);
?>

<?php
include '../../conexao.php';

$eventos = [];

// Buscar apenas eventos do tipo 'Entrega' de todas as obras
// Usamos MIN(id) para garantir um id estável quando agrupamos por descricao/data_evento
$sql = "SELECT MIN(id) AS id, descricao, data_evento AS start, tipo_evento
        FROM eventos_obra
        WHERE tipo_evento = 'Entrega'
        GROUP BY descricao, data_evento, tipo_evento
        ORDER BY data_evento ASC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $eventos[] = $row;
    }
} else {
    error_log('getEventosEntrega.php SQL error: ' . $conn->error);
}

$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($eventos, JSON_UNESCAPED_UNICODE);

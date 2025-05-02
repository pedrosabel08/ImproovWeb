<?php
include '../../conexao.php';

$eventos = [];

// Buscar apenas eventos do tipo 'Entrega' de todas as obras
$sql = "SELECT id, descricao, data_evento AS start, tipo_evento 
        FROM eventos_obra 
        WHERE tipo_evento = 'Entrega'";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $eventos[] = $row;
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($eventos);

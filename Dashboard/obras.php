<?php
include '../conexao.php'; // conexão com o banco

$response = [
    'hold' => [],
    'andamento' => [],
    'finalizadas' => []
];

// Query para obras paradas (HOLD)
$queryHold = "SELECT o.idobra, o.nomenclatura
FROM obra o
JOIN imagens_cliente_obra ico ON o.idobra = ico.obra_id
WHERE o.status_obra = 0
GROUP BY o.idobra, o.nomenclatura
HAVING COUNT(*) = SUM(ico.status_id IN (7, 9))";
$resultHold = $conn->query($queryHold);
while ($row = $resultHold->fetch_assoc()) {
    $response['hold'][] = $row;
}

// Query para obras em andamento
$queryAndamento = "SELECT idobra, nomenclatura FROM obra WHERE status_obra = 0";
$resultAndamento = $conn->query($queryAndamento);
while ($row = $resultAndamento->fetch_assoc()) {
    $response['andamento'][] = $row;
}

// Query para obras finalizadas
$queryFinalizadas = "SELECT idobra, nomenclatura FROM obra WHERE status_obra = 1";
$resultFinalizadas = $conn->query($queryFinalizadas);
while ($row = $resultFinalizadas->fetch_assoc()) {
    $response['finalizadas'][] = $row;
}

header('Content-Type: application/json');
echo json_encode($response);

<?php
header('Content-Type: application/json');

include 'conexao.php';

$result = $conn->query("SELECT idobra, nome_obra, nomenclatura FROM obra WHERE status_obra = 0 ORDER BY nomenclatura ASC");

$obras = [];
while ($row = $result->fetch_assoc()) {
    $obras[] = [
        'idobra' => $row['idobra'],
        'nomenclatura' => $row['nomenclatura']
    ];
}

echo json_encode([
    'success' => true,
    'obras' => $obras
]);

$conn->close();

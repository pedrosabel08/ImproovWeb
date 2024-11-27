<?php
include '../conexao.php';

// SELECT com filtro WHERE
$query1 = "SELECT 
    o.nome_obra, 
    o.idobra,
    MAX(i.prazo) AS prazo 
FROM 
    obra o 
JOIN 
    imagens_cliente_obra i 
    ON i.obra_id = o.idobra 
WHERE 
    o.status_obra = 0 
GROUP BY 
    o.nome_obra;";

$result1 = $conn->query($query1);
$data_with_filter = [];
while ($row = $result1->fetch_assoc()) {
    $data_with_filter[] = $row;
}

// SELECT sem o filtro WHERE
$query2 = "SELECT 
    o.nome_obra, 
    o.idobra,
    MAX(i.prazo) AS prazo,
    o.status_obra
FROM 
    obra o 
JOIN 
    imagens_cliente_obra i 
    ON i.obra_id = o.idobra 
    GROUP BY o.nome_obra
    ORDER BY o.status_obra ASC";

$result2 = $conn->query($query2);
$data_without_filter = [];
while ($row = $result2->fetch_assoc()) {
    $data_without_filter[] = $row;
}

// Retornar os dois conjuntos de dados como JSON
$response = [
    'with_filter' => $data_with_filter,
    'without_filter' => $data_without_filter
];

echo json_encode($response);

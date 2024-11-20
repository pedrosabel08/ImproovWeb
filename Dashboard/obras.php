<?php
include '../conexao.php';

$query = "SELECT 
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
    o.nome_obra;

";

$result = $conn->query($query);
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);

<?php
include '../conexao.php';

// SELECT com filtro WHERE
$query1 = "SELECT 
    o.nomenclatura, 
    o.idobra,
    o.recebimento_arquivos,
    GROUP_CONCAT(DISTINCT i.status_id ORDER BY i.status_id ASC SEPARATOR ', ') AS status_ids, -- Concatena os status_id
    MAX(i.prazo) AS prazo,
    COUNT(fun.idfuncao) AS total_funcoes,
    COUNT(CASE WHEN f.status = 'Finalizado' THEN 1 END) AS funcoes_finalizadas,
    ROUND(
        (COUNT(CASE WHEN f.status = 'Finalizado' THEN 1 END) * 100.0) 
        / COUNT(fun.idfuncao), 
        2
    ) AS porcentagem_finalizada,
    CASE 
        WHEN SUM(CASE WHEN i.status_id IN (3, 4, 5, 14, 15) THEN 1 ELSE 0 END) > 0 THEN 'Alterações'
        ELSE 'Prévias'
    END AS status_obra -- Define o status da obra com base nos status_id
FROM 
    obra o
LEFT JOIN 
    imagens_cliente_obra i 
    ON i.obra_id = o.idobra
LEFT JOIN 
    funcao_imagem f 
    ON i.idimagens_cliente_obra = f.imagem_id
LEFT JOIN 
    funcao fun 
    ON fun.idfuncao = f.funcao_id
WHERE 
    o.status_obra = 0
GROUP BY 
    o.nomenclatura, 
    o.idobra";

$result1 = $conn->query($query1);
$data_with_filter = [];
while ($row = $result1->fetch_assoc()) {
    $data_with_filter[] = $row;
}

// SELECT sem o filtro WHERE
$query2 = "SELECT 
    o.nomenclatura, 
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

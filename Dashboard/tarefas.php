<?php
include '../conexao.php';

$query = "SELECT 
        f.nome_funcao,
        MONTH(prazo) AS mes,
        COUNT(*) AS total_tarefas,
        SUM(CASE WHEN status = 'Finalizado' THEN 1 ELSE 0 END) AS total_finalizado,
        ROUND((SUM(CASE WHEN status = 'Finalizado' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) AS percentual_finalizado
    FROM 
        funcao_imagem fi
        JOIN funcao f on f.idfuncao = fi.funcao_id
    WHERE 
        MONTH(prazo) = MONTH(CURRENT_DATE) AND YEAR(prazo) = YEAR(CURRENT_DATE)
    GROUP BY 
        f.nome_funcao, MONTH(prazo)
    ORDER BY
        fi.funcao_id
";

$result = $conn->query($query);
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);

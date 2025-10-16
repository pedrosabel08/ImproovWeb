<?php
include '../conexao.php';

$query = "SELECT 
    i.idimagens_cliente_obra AS imagem_id,
    i.tipo_imagem,
    i.imagem_nome,
    c.nome_colaborador AS colaborador,
    i.prazo AS prazo_imagem,
    (
        SELECT MAX(la2.data)
        FROM log_alteracoes la2
        WHERE la2.funcao_imagem_id = f.idfuncao_imagem
          AND la2.colaborador_id = f.colaborador_id
          AND la2.status_novo = 'Em andamento'
    ) AS data_inicio,
    MAX(f.prazo) AS data_fim,
    s.nome_status AS etapa,
    ss.nome_substatus AS status
FROM 
    imagens_cliente_obra i
LEFT JOIN 
    obra o ON i.obra_id = o.idobra
LEFT JOIN 
    funcao_imagem f ON f.imagem_id = i.idimagens_cliente_obra AND f.funcao_id = 4
LEFT JOIN 
    colaborador c ON c.idcolaborador = f.colaborador_id
LEFT JOIN 
    status_imagem s ON s.idstatus = i.status_id
LEFT JOIN 
    substatus_imagem ss ON ss.id = i.substatus_id
WHERE 
    o.status_obra = 0 
    AND i.substatus_id NOT IN (6, 7, 8, 9)
    AND i.status_id IN (1, 2)
    
GROUP BY
    i.idimagens_cliente_obra, i.tipo_imagem, c.nome_colaborador, i.prazo
ORDER BY 
    i.tipo_imagem, c.nome_colaborador, i.obra_id, i.idimagens_cliente_obra;
";

$result = $conn->query($query);

$dados = [];
while ($row = $result->fetch_assoc()) {
    $dados[] = $row;
}

header('Content-Type: application/json');
echo json_encode($dados);

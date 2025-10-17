<?php
include '../conexao.php';

$funcao_id = null;
if (isset($_GET['funcao_id']) && is_numeric($_GET['funcao_id'])) {
    $funcao_id = (int) $_GET['funcao_id'];
}

// build SELECT with an assigned flag when filtering by funcao
$assigned_select = '';
if ($funcao_id !== null) {
    $assigned_select = "(
        SELECT COUNT(1)
        FROM funcao_imagem fi2
        WHERE fi2.imagem_id = i.idimagens_cliente_obra
          AND fi2.funcao_id = ?
          AND fi2.colaborador_id IS NOT NULL
    ) AS assigned_for_funcao,";
} else {
    $assigned_select = "0 AS assigned_for_funcao,";
}

$query = "SELECT 
    i.idimagens_cliente_obra AS imagem_id,
    i.tipo_imagem,
    i.imagem_nome,
    f.funcao_id AS funcao_id,
    fu.nome_funcao AS nome_funcao,
    c.idcolaborador AS colaborador_id,
    c.nome_colaborador AS colaborador,
    i.prazo AS prazo_imagem,
    " . $assigned_select . "
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
    funcao_imagem f ON f.imagem_id = i.idimagens_cliente_obra
LEFT JOIN 
    colaborador c ON c.idcolaborador = f.colaborador_id
LEFT JOIN 
    funcao fu ON fu.idfuncao = f.funcao_id
LEFT JOIN 
    status_imagem s ON s.idstatus = i.status_id
LEFT JOIN 
    substatus_imagem ss ON ss.id = i.substatus_id
WHERE 
    o.status_obra = 0 
    AND i.substatus_id NOT IN (6, 7, 8, 9)
    AND i.status_id IN (1, 2)
    
";

// quando for passado funcao_id, filtrar mas também incluir imagens sem função (disponíveis)
if ($funcao_id !== null) {
    $query .= " AND (f.funcao_id = ? OR f.funcao_id IS NULL) ";
}

$query .= "\n    
GROUP BY
    i.idimagens_cliente_obra,
    i.tipo_imagem,
    i.imagem_nome,
    f.funcao_id,
    fu.nome_funcao,
    c.idcolaborador,
    c.nome_colaborador,
    i.prazo,
    s.nome_status,
    ss.nome_substatus
ORDER BY 
    i.tipo_imagem, fu.nome_funcao, c.nome_colaborador, i.obra_id, i.idimagens_cliente_obra;
";

$dados = [];
if ($stmt = $conn->prepare($query)) {
    if ($funcao_id !== null) {
        $stmt->bind_param('ii', $funcao_id, $funcao_id);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $dados[] = $row;
        }
        $res->free();
    }
    $stmt->close();
} else {
    // fallback para query direta em caso de erro no prepare
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $dados[] = $row;
        }
    }
}

header('Content-Type: application/json');
echo json_encode($dados);

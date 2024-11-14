<?php
include '../conexao.php';

header('Content-Type: application/json');

// Recebe o ID da obra via GET
$obraId = isset($_GET['id']) ? $_GET['id'] : null;

// Verifica se o ID da obra foi passado corretamente
if ($obraId === null) {
    echo json_encode(["error" => "ID da obra não fornecido."]);
    exit;
}

// Consulta SQL para buscar detalhes da obra
$sql = "SELECT 
    o.nome_obra,
    o.nomenclatura,
    o.cliente,
    o.status_obra,
    i.data_inicio,
    i.prazo,
    o.data_final,
    o.animacao,

    -- Total de colaboradores (sem duplicação)
    (SELECT COUNT(DISTINCT fi.colaborador_id)
     FROM funcao_imagem fi
     JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
     WHERE ico.obra_id = o.idobra) AS total_colaboradores,

    -- Nome dos colaboradores para cada função
    GROUP_CONCAT(DISTINCT CASE WHEN fi.funcao_id = 1 THEN c.nome_colaborador ELSE NULL END) AS colab_caderno,
    GROUP_CONCAT(DISTINCT CASE WHEN fi.funcao_id = 2 THEN c.nome_colaborador ELSE NULL END) AS colab_model,
    GROUP_CONCAT(DISTINCT CASE WHEN fi.funcao_id = 3 THEN c.nome_colaborador ELSE NULL END) AS colab_comp,
    GROUP_CONCAT(DISTINCT CASE WHEN fi.funcao_id = 4 THEN c.nome_colaborador ELSE NULL END) AS colab_final,
    GROUP_CONCAT(DISTINCT CASE WHEN fi.funcao_id = 5 THEN c.nome_colaborador ELSE NULL END) AS colab_pos,
    GROUP_CONCAT(DISTINCT CASE WHEN fi.funcao_id = 6 THEN c.nome_colaborador ELSE NULL END) AS colab_alt,
    GROUP_CONCAT(DISTINCT CASE WHEN fi.funcao_id = 7 THEN c.nome_colaborador ELSE NULL END) AS colab_planta,
    GROUP_CONCAT(DISTINCT CASE WHEN fi.funcao_id = 8 THEN c.nome_colaborador ELSE NULL END) AS colab_filtro,

    -- Cálculo de porcentagens para cada grupo de status
    COUNT(ico.idimagens_cliente_obra) AS total_imagens,
    
    -- Porcentagem do status 1
    ROUND((COUNT(CASE WHEN ico.status_id = 1 THEN 1 END) / COUNT(ico.idimagens_cliente_obra)) * 100, 2) AS percentual_status_1,
    
    -- Porcentagem do status 2
    ROUND((COUNT(CASE WHEN ico.status_id = 2 THEN 1 END) / COUNT(ico.idimagens_cliente_obra)) * 100, 2) AS percentual_status_2,

    -- Porcentagem dos status 3, 4, e 5 somados
    ROUND((COUNT(CASE WHEN ico.status_id IN (3, 4, 5) THEN 1 END) / COUNT(ico.idimagens_cliente_obra)) * 100, 2) AS percentual_status_3_4_5,

    -- Porcentagem do status 6
    ROUND((COUNT(CASE WHEN ico.status_id = 6 THEN 1 END) / COUNT(ico.idimagens_cliente_obra)) * 100, 2) AS percentual_status_6,

    -- Porcentagem do status 9
    ROUND((COUNT(CASE WHEN ico.status_id = 9 THEN 1 END) / COUNT(ico.idimagens_cliente_obra)) * 100, 2) AS percentual_status_9,

    -- Total de revisões
    (SELECT SUM(CASE WHEN lf.status_novo IN (3, 4, 5) THEN 1 ELSE 0 END)
     FROM log_followup lf
     WHERE lf.imagem_id = ico.idimagens_cliente_obra) AS total_revisoes,

    -- Total gasto em produção
    (SELECT SUM(f.valor)
     FROM funcao_imagem f
     JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
     WHERE i.obra_id = o.idobra) AS total_gasto_producao

FROM obra o
JOIN imagens_cliente_obra i ON o.idobra = i.obra_id
LEFT JOIN funcao_imagem fi ON fi.imagem_id = i.idimagens_cliente_obra
LEFT JOIN colaborador c ON c.idcolaborador = fi.colaborador_id
LEFT JOIN imagens_cliente_obra ico ON ico.obra_id = o.idobra
WHERE o.idobra = ?
GROUP BY o.idobra";

$stmt = $conn->prepare($sql);

// Verifica se a preparação da consulta falhou
if ($stmt === false) {
    die('Erro na preparação da consulta: ' . $conn->error);
}

$stmt->bind_param("i", $obraId);

// Executa a consulta
$stmt->execute();
$result = $stmt->get_result();

// Verifica se há resultados
if ($result->num_rows > 0) {
    $response = $result->fetch_assoc();
    echo json_encode($response);
} else {
    echo json_encode(["error" => "Detalhes não encontrados para a obra com ID " . $obraId]);
}

$stmt->close();
$conn->close();

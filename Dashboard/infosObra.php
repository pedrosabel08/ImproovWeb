<?php
include '../conexao.php';

header('Content-Type: application/json');

// Recebe o ID da obra via GET
$obraId = intval($_GET['obraId']);

// Verifica se o ID da obra foi passado corretamente
if ($obraId === null) {
    echo json_encode(["error" => "ID da obra não fornecido."]);
    exit;
}

$response = [];

// Primeiro SELECT: Detalhes das funções
$sqlFuncoes = "SELECT 
        fun.nome_funcao,
        COUNT(DISTINCT i.idimagens_cliente_obra) AS total_imagens,
        COUNT(DISTINCT CASE WHEN f.status = 'Finalizado' THEN i.idimagens_cliente_obra END) AS funcoes_finalizadas,
        ROUND(
            (COUNT(DISTINCT CASE WHEN f.status = 'Finalizado' THEN i.idimagens_cliente_obra END) * 100.0) 
            / COUNT(DISTINCT i.idimagens_cliente_obra), 2
        ) AS porcentagem_finalizada
    FROM 
        imagens_cliente_obra i
    JOIN 
        funcao_imagem f 
        ON f.imagem_id = i.idimagens_cliente_obra
    JOIN 
        funcao fun 
        ON fun.idfuncao = f.funcao_id
    WHERE 
        i.obra_id = ?
    GROUP BY 
        fun.nome_funcao
";

$stmtFuncoes = $conn->prepare($sqlFuncoes);
if ($stmtFuncoes === false) {
    die('Erro na preparação da consulta (funções): ' . $conn->error);
}

$stmtFuncoes->bind_param("i", $obraId);
$stmtFuncoes->execute();
$resultFuncoes = $stmtFuncoes->get_result();

// Processa os resultados do primeiro SELECT
$funcoes = [];
while ($row = $resultFuncoes->fetch_assoc()) {
    $funcoes[] = $row;
}
$response['funcoes'] = $funcoes;

$stmtFuncoes->close();

// Segundo SELECT: Detalhes gerais da obra
$sqlObra = "SELECT
    o.idobra,
    o.nomenclatura,
    i.data_inicio,
	i.prazo,
    COUNT(*) AS total_imagens,
    COUNT(CASE WHEN i.antecipada = 1 THEN 1 ELSE NULL END) AS total_imagens_antecipadas,
    i.dias_trabalhados
FROM 
    imagens_cliente_obra i
JOIN
    obra o 
    ON o.idobra = i.obra_id
WHERE 
    i.obra_id = ?";

$stmtObra = $conn->prepare($sqlObra);
if ($stmtObra === false) {
    die('Erro na preparação da consulta (obra): ' . $conn->error);
}

$stmtObra->bind_param("i", $obraId);
$stmtObra->execute();
$resultObra = $stmtObra->get_result();

// Processa os resultados do segundo SELECT
$obra = $resultObra->fetch_assoc(); // Deve retornar uma única linha
$response['obra'] = $obra;

$stmtObra->close();

// Segundo SELECT: Detalhes gerais da obra
$sqlValores = "SELECT 
    COALESCE(p.valor_producao, 0) AS custo_producao,
    COALESCE(c.custo_fixo, 0) AS custo_fixo,
    COALESCE(o.valor_orcamento, 0) AS valor_orcamento,
    COALESCE(o.valor_orcamento, 0) - (COALESCE(p.valor_producao, 0)) AS lucro
FROM (
    SELECT 
        ROUND(SUM(valor), 2) AS valor_producao 
    FROM funcao_imagem f
    JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = f.imagem_id
    WHERE i.obra_id = ?
) p,
(
    SELECT 
        ROUND(SUM(valor), 2) AS valor_orcamento
    FROM orcamentos_obra
    WHERE obra_id = ?
) o,
(
    SELECT 
        custo_fixo
    FROM custos_fixos_obra
    WHERE obra_id = ?
) c
";


$stmtValores = $conn->prepare($sqlValores);
if ($stmtValores === false) {
    die('Erro na preparação da consulta (obra): ' . $conn->error);
}

$stmtValores->bind_param("iii", $obraId, $obraId, $obraId);
$stmtValores->execute();
$resultValores = $stmtValores->get_result();

// Processa os resultados do segundo SELECT
$valores = $resultValores->fetch_assoc(); // Deve retornar uma única linha
$response['valores'] = $valores;

$stmtValores->close();


$sqlImagens = "SELECT
        ico.idimagens_cliente_obra AS imagem_id,
        ico.imagem_nome,
        ico.tipo_imagem,
        ico.antecipada,
        MAX(CASE WHEN fi.funcao_id = 1 THEN c.nome_colaborador END) AS caderno_colaborador,
        MAX(CASE WHEN fi.funcao_id = 1 THEN fi.status END) AS caderno_status,
        MAX(CASE WHEN fi.funcao_id = 2 THEN c.nome_colaborador END) AS modelagem_colaborador,
        MAX(CASE WHEN fi.funcao_id = 2 THEN fi.status END) AS modelagem_status,
        MAX(CASE WHEN fi.funcao_id = 3 THEN c.nome_colaborador END) AS composicao_colaborador,
        MAX(CASE WHEN fi.funcao_id = 3 THEN fi.status END) AS composicao_status,
        MAX(CASE WHEN fi.funcao_id = 4 THEN c.nome_colaborador END) AS finalizacao_colaborador,
        MAX(CASE WHEN fi.funcao_id = 4 THEN fi.status END) AS finalizacao_status,
        MAX(CASE WHEN fi.funcao_id = 5 THEN c.nome_colaborador END) AS pos_producao_colaborador,
        MAX(CASE WHEN fi.funcao_id = 5 THEN fi.status END) AS pos_producao_status,
        MAX(CASE WHEN fi.funcao_id = 6 THEN c.nome_colaborador END) AS alteracao_colaborador,
        MAX(CASE WHEN fi.funcao_id = 6 THEN fi.status END) AS alteracao_status,
        MAX(CASE WHEN fi.funcao_id = 7 THEN c.nome_colaborador END) AS planta_colaborador,
        MAX(CASE WHEN fi.funcao_id = 7 THEN fi.status END) AS planta_status
    FROM imagens_cliente_obra ico
    LEFT JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra
    LEFT JOIN colaborador c ON fi.colaborador_id = c.idcolaborador
    WHERE ico.obra_id = ?
    GROUP BY ico.imagem_nome
    ORDER BY ico.idimagens_cliente_obra
";

$stmtImagens = $conn->prepare($sqlImagens);
if ($stmtImagens === false) {
    die('Erro na preparação da consulta (imagens): ' . $conn->error);
}

$stmtImagens->bind_param("i", $obraId);
$stmtImagens->execute();
$resultImagens = $stmtImagens->get_result();

// Processa os resultados do novo SELECT
$imagens = [];
while ($row = $resultImagens->fetch_assoc()) {
    $imagens[] = $row;
}
$response['imagens'] = $imagens;

$stmtImagens->close();

$conn->close();

// Retorna o resultado como JSON
echo json_encode($response);

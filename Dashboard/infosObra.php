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
        s.nome_status AS imagem_status,
        ico.prazo,
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
    LEFT JOIN status_imagem s ON ico.status_id = s.idstatus
    WHERE ico.obra_id = ?
    GROUP BY ico.imagem_nome
    ORDER BY FIELD(ico.tipo_imagem, 'Fachada', 'Imagem Interna', 'Imagem Externa', 'Planta Humanizada'), ico.idimagens_cliente_obra;
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


$sqlInfos = "SELECT * from observacao_obra where obra_id = ?";

$stmtInfos = $conn->prepare($sqlInfos);
if ($stmtInfos === false) {
    die('Erro na preparação da consulta (imagens): ' . $conn->error);
}

$stmtInfos->bind_param("i", $obraId);
$stmtInfos->execute();
$resultInfos = $stmtInfos->get_result();

// Processa os resultados do novo SELECT
$infos = [];
while ($row = $resultInfos->fetch_assoc()) {
    $infos[] = $row;
}
$response['infos'] = $infos;

$stmtInfos->close();

$sqlTotalObra = "SELECT 
    COUNT(*) AS total_funcoes,
    COUNT(CASE WHEN f.status = 'Finalizado' THEN 1 END) AS funcoes_finalizadas,
    ROUND(
        (COUNT(CASE WHEN f.status = 'Finalizado' THEN 1 END) * 100.0) 
        / COUNT(*), 
        2
    ) AS porcentagem_finalizada
FROM 
    funcao fun
LEFT JOIN 
    funcao_imagem f 
    ON fun.idfuncao = f.funcao_id
LEFT JOIN 
    imagens_cliente_obra i 
    ON f.imagem_id = i.idimagens_cliente_obra
WHERE 
    i.obra_id = ?";

$stmtTotalObra = $conn->prepare($sqlTotalObra);
if ($stmtInfos === false) {
    die('Erro na preparação da consulta (imagens): ' . $conn->error);
}

$stmtTotalObra->bind_param("i", $obraId);
$stmtTotalObra->execute();
$resultTotalObra = $stmtTotalObra->get_result();

// Processa os resultados do novo SELECT
$totalObra = [];
while ($row = $resultTotalObra->fetch_assoc()) {
    $totalObra[] = $row;
}
$response['totalObra'] = $totalObra;

$stmtTotalObra->close();

$sqlBriefing = "SELECT 
    nivel,
    conceito,
    valor_media,
    outro_padrao,
    assets,
    comp_planta
    FROM briefing
WHERE 
    briefing.obra_id = ?";

$stmtBriefing = $conn->prepare($sqlBriefing);
if ($stmtBriefing === false) {
    die('Erro na preparação da consulta (imagens): ' . $conn->error);
}

$stmtBriefing->bind_param("i", $obraId);
$stmtBriefing->execute();
$resultBriefing = $stmtBriefing->get_result();

// Processa os resultados do novo SELECT
$briefing = [];
while ($row = $resultBriefing->fetch_assoc()) {
    $briefing[] = $row;
}
$response['briefing'] = $briefing;

$stmtBriefing->close();

$sqlPrazos = "SELECT s.nome_status, i.prazo
FROM imagens_cliente_obra i 
JOIN status_imagem s ON i.status_id = s.idstatus 
WHERE i.obra_id = ?
GROUP BY s.nome_status, i.prazo";

$stmtPrazos = $conn->prepare($sqlPrazos);
if ($stmtPrazos === false) {
    die('Erro na preparação da consulta (imagens): ' . $conn->error);
}

$stmtPrazos->bind_param("i", $obraId);
$stmtPrazos->execute();
$resultPrazos = $stmtPrazos->get_result();

// Processa os resultados do novo SELECT
$prazos = [];
while ($row = $resultPrazos->fetch_assoc()) {
    $prazos[] = $row;
}
$response['prazos'] = $prazos;

$stmtPrazos->close();

$conn->close();

// Retorna o resultado como JSON
echo json_encode($response);

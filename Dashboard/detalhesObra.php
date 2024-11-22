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
        o.nomenclatura,
        data_inicio, 
        prazo, 
        COUNT(*) AS total_imagens,
        COUNT(CASE WHEN i.antecipada = 1 THEN 1 ELSE NULL END) AS total_imagens_antecipadas
    FROM 
        imagens_cliente_obra i
    JOIN
        obra o 
        ON o.idobra = i.obra_id
    WHERE 
        obra_id = ?
";

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
$conn->close();

// Retorna o resultado como JSON
echo json_encode($response);

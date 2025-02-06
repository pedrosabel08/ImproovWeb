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

// Consulta para obter as imagens e suas revisões
$sqlAlteracao = "SELECT i.imagem_nome, a.descricao, a.data_envio, a.data_recebimento, a.status, c.nome_colaborador, a.numero_revisao, i.idimagens_cliente_obra
FROM alteracoes a 
LEFT JOIN imagens_cliente_obra i ON a.imagem_id = i.idimagens_cliente_obra 
JOIN colaborador c ON c.idcolaborador = a.colaborador_id 
WHERE i.obra_id = ?
ORDER BY i.imagem_nome, a.numero_revisao";

$stmtAlteracao = $conn->prepare($sqlAlteracao);
if ($stmtAlteracao === false) {
    die('Erro na preparação da consulta (imagens): ' . $conn->error);
}

$stmtAlteracao->bind_param("i", $obraId);
$stmtAlteracao->execute();
$resultAlteracao = $stmtAlteracao->get_result();

// Processa os resultados e agrupa as revisões por imagem
$imagens = [];

while ($row = $resultAlteracao->fetch_assoc()) {
    $imagemId = $row['idimagens_cliente_obra'];

    // Se a imagem ainda não estiver no array, cria a estrutura
    if (!isset($imagens[$imagemId])) {
        $imagens[$imagemId] = [
            'imagem_nome' => $row['imagem_nome'],
            'revisoes' => []
        ];
    }

    // Adiciona a revisão à imagem correspondente
    $imagens[$imagemId]['revisoes'][] = [
        'descricao' => $row['descricao'],
        'data_envio' => $row['data_envio'],
        'data_recebimento' => $row['data_recebimento'],
        'status' => $row['status'],
        'numero_revisao' => $row['numero_revisao'],
        'nome_colaborador' => $row['nome_colaborador']
    ];
}

// Organiza as imagens e revisões
$response['alt'] = array_values($imagens); // Converte o array associativo em um array numérico

$stmtAlteracao->close();
$conn->close();

// Retorna o resultado como JSON
echo json_encode($response);

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

$sqlImagens = "SELECT
        ico.idimagens_cliente_obra AS imagem_id,
        ico.imagem_nome,
        ru.id,
        ru.nome_arquivo,
        ru.lock,
        ru.hide,
        ru.data_envio,
        ru.versao,
        o.nomenclatura
    FROM imagens_cliente_obra ico
    LEFT JOIN review_uploads ru ON ico.idimagens_cliente_obra = ru.imagem_id
    LEFT JOIN obra o ON ico.obra_id = o.idobra
    WHERE ico.obra_id = ?
    ORDER BY FIELD(ico.tipo_imagem, 'Fachada', 'Imagem Interna', 'Unidade', 'Imagem Externa', 'Planta Humanizada'), ico.idimagens_cliente_obra;";


$stmtImagens = $conn->prepare($sqlImagens);
if ($stmtImagens === false) {
    die('Erro na preparação da consulta (imagens): ' . $conn->error);
}

$stmtImagens->bind_param("i", $obraId);
$stmtImagens->execute();
$resultImagens = $stmtImagens->get_result();

// Processa os resultados do novo SELECT
$imagens = [];
$nomenclatura = null;
while ($row = $resultImagens->fetch_assoc()) {
    if ($nomenclatura === null) {
        $nomenclatura = $row['nomenclatura'];
    }
    unset($row['nomenclatura']); // Remove do array de cada imagem
    $imagens[] = $row;
}
$response['imagens'] = $imagens;
$response['nomenclatura'] = $nomenclatura;

$stmtImagens->close();
// Retorna o resultado como JSON
echo json_encode($response);

$conn->close();

<?php
require '../conexao.php';

$tipoImagem = $_GET['tipo_imagem'] ?? '';
$obra_id = $_GET['obra_id'] ?? '';
$funcao_id = $_GET['funcao_id'] ?? null;

$sql = "SELECT 
        ico.idimagens_cliente_obra, 
        ico.imagem_nome,
        fi.colaborador_id,
        fi.funcao_id
    FROM imagens_cliente_obra ico
    LEFT JOIN funcao_imagem fi ON fi.imagem_id = ico.idimagens_cliente_obra AND fi.funcao_id = ?
    WHERE ico.tipo_imagem = ? AND ico.obra_id = ?
";


if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("isi", $funcao_id, $tipoImagem, $obra_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $imagens = [];
    while ($row = $result->fetch_assoc()) {
        $imagens[] = $row;
    }

    $stmt->close();
    echo json_encode($imagens);
} else {
    http_response_code(500);
    echo json_encode(["erro" => "Erro na preparação da consulta"]);
}

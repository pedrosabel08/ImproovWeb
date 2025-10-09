<?php
include '../conexao.php';

header('Content-Type: application/json');

$response = [];


$sql = " SELECT fi.funcao_id, fi.status, COUNT(*) AS quantidade
        FROM funcao_imagem fi
        JOIN imagens_cliente_obra ico ON fi.imagem_id = ico.idimagens_cliente_obra
        JOIN obra o ON ico.obra_id = o.idobra
        WHERE o.status_obra = 0
        GROUP BY fi.funcao_id, fi.status
        ORDER BY fi.funcao_id";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Erro na preparação da consulta: ' . $conn->error);
}
$stmt->execute();
$result = $stmt->get_result();

// Processa os resultados
$metricas = [];
while ($row = $result->fetch_assoc()) {
    $funcao_id = $row['funcao_id'];
    $status = $row['status'];
    $quantidade = (int)$row['quantidade'];

    if (!isset($metricas[$funcao_id])) {
        $metricas[$funcao_id] = [];
    }
    $metricas[$funcao_id][$status] = $quantidade;
}

$response['metricas'] = $metricas;

$stmt->close();
// Retorna o resultado como JSON
echo json_encode($response);

$conn->close();

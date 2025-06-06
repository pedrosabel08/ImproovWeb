<?php

include "conexao.php";

$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data["idcolaborador"]);
$situacao = $data["situacao"];
$observacao = trim($data["observacao"] ?? '');
$dataEnvio = $data["data"];


$stmt = $conn->prepare("INSERT INTO revisao_mensal (id_colaborador, situacao, observacao, data_envio) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $id, $situacao, $observacao, $dataEnvio);

if ($stmt->execute()) {
    echo "OK";
} else {
    http_response_code(500);
    echo "Erro ao salvar.";
}

$stmt->close();
$conn->close();

<?php
header('Content-Type: application/json');
require '../conexao.php'; // sua conexão mysqli ($conn)

// Recebe os dados enviados via AJAX
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['ids']) || !isset($data['campos'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos']);
    exit;
}

$ids = array_map('intval', $data['ids']); // garante que os IDs são inteiros
$campos = $data['campos'];

// Monta a parte SET da query
$set = [];
foreach ($campos as $col => $valor) {
    $valor = mysqli_real_escape_string($conn, $valor);
    $set[] = "`$col` = '$valor'";
}

// Monta a lista de IDs para o IN
$idsList = implode(',', $ids);

// Query única
$sql = "UPDATE imagens_cliente_obra SET " . implode(", ", $set) . " WHERE idimagens_cliente_obra IN ($idsList)";

if (mysqli_query($conn, $sql)) {
    echo json_encode(['sucesso' => true]);
} else {
    echo json_encode(['sucesso' => false, 'mensagem' => mysqli_error($conn)]);
}

<?php

include '../conexao.php';

// Captura os dados enviados via POST
$data = json_decode(file_get_contents('php://input'), true);
$revisao = $data['revisao'];
$observacao = $data['observacao'];

// Prepara a query SQL
$stmt = $conn->prepare("UPDATE historico_aprovacoes SET observacoes = ? WHERE id = ?");
$stmt->bind_param("si", $observacao, $revisao);

// Executa a query
if ($stmt->execute()) {
    // Retorna sucesso
    echo json_encode(['success' => true]);
} else {
    // Retorna erro
    echo json_encode(['success' => false, 'error' => 'Erro ao atualizar']);
}

// Fecha a conexão
$stmt->close();
$conn->close();

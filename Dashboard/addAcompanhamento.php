<?php
// Configuração do banco de dados
include '../conexao.php';

// Configura o cabeçalho para JSON
header('Content-Type: application/json');

// Obtém os dados enviados pelo cliente
$obra_id = isset($_POST['idobra']) ? intval($_POST['idobra']) : null;
$colaborador_id = 1; // ID fixo
$assunto = isset($_POST['assunto']) ? trim($_POST['assunto']) : null;
$data = isset($_POST['data']) ? $_POST['data'] : null; // Data enviada pelo cliente

// Validações
if (!$obra_id || !$assunto || !$data) {
    echo json_encode(["success" => false, "message" => "Dados incompletos."]);
    exit;
}

// Prepara o comando SQL
$stmt = $conn->prepare("INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiss", $obra_id, $colaborador_id, $assunto, $data);

// Executa a query e verifica o resultado
if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Acompanhamento adicionado com sucesso."]);
} else {
    echo json_encode(["success" => false, "message" => "Erro ao inserir acompanhamento: " . $conn->error]);
}

// Fecha a conexão
$stmt->close();
$conn->close();

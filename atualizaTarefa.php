<?php
header('Content-Type: application/json');

// ...conexão com o banco...
require_once 'conexao.php'; // ajuste para seu arquivo de conexão

$tarefa_id = $_POST['tarefa_id'] ?? null;
$prazo = $_POST['prazo'] ?? null;
$observacao = $_POST['observacao'] ?? null;
$status = $_POST['status'] ?? null;

if (!$tarefa_id) {
    echo json_encode(['success' => false, 'message' => 'ID da tarefa não informado']);
    exit;
}

$stmt = $conn->prepare("UPDATE tarefas SET prazo = ?, descricao = ?, status = ? WHERE id = ?");
$stmt->bind_param("sssi", $prazo, $observacao, $status, $tarefa_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Tarefa atualizada com sucesso']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar tarefa']);
}

$stmt->close();
$conn->close();
?>

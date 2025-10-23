<?php
header('Content-Type: application/json');

// conexão com o banco
require_once __DIR__ . '/../conexao.php'; // ajuste para seu arquivo de conexão

$tarefa_id = $_POST['tarefa_id'] ?? null;
$prazo = $_POST['prazo'] ?? null;
$observacao = isset($_POST['observacao']) ? trim($_POST['observacao']) : null;
$status = $_POST['status'] ?? null;

if (!$tarefa_id) {
    echo json_encode(['success' => false, 'message' => 'ID da tarefa não informado']);
    exit;
}

// Construir UPDATE dinamicamente: só inclui descricao se houver observacao não vazia
$sets = [];
$types = '';
$values = [];

if ($prazo !== null) {
    $sets[] = 'prazo = ?';
    $types .= 's';
    $values[] = $prazo;
}

if ($observacao !== null && $observacao !== '') {
    $sets[] = 'descricao = ?';
    $types .= 's';
    $values[] = $observacao;
}

if ($status !== null) {
    $sets[] = 'status = ?';
    $types .= 's';
    $values[] = $status;
}

if (count($sets) === 0) {
    // Nada para atualizar
    echo json_encode(['success' => false, 'message' => 'Nenhuma alteração fornecida']);
    $conn->close();
    exit;
}

$sql = 'UPDATE tarefas SET ' . implode(', ', $sets) . ' WHERE id = ?';
$types .= 'i'; // id é inteiro
$values[] = $tarefa_id;

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Erro na preparação da query: ' . $conn->error]);
    $conn->close();
    exit;
}

// Bind dinamicamente
$bind_names[] = $types;
for ($i = 0; $i < count($values); $i++) {
    $bind_name = 'bind' . $i;
    $$bind_name = $values[$i];
    $bind_names[] = &$$bind_name;
}

call_user_func_array([$stmt, 'bind_param'], $bind_names);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Tarefa atualizada com sucesso']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar tarefa: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

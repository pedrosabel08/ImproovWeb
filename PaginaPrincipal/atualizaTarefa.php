<?php
header('Content-Type: application/json');

// conexão com o banco
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php'; // ajuste para seu arquivo de conexão
require_once __DIR__ . '/../helpers/unidade_trabalho_helper.php';

$tarefa_id = $_POST['tarefa_id'] ?? null;
$prazo = $_POST['prazo'] ?? null;
$observacao = isset($_POST['observacao']) ? trim($_POST['observacao']) : null;
$status = $_POST['status'] ?? null;

if (!$tarefa_id) {
    echo json_encode(['success' => false, 'message' => 'ID da tarefa não informado']);
    exit;
}
if (empty($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'code' => 'UNAUTHENTICATED', 'message' => 'Sessão inválida.']);
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

$conn->begin_transaction();
try {
    $currentStmt = $conn->prepare('SELECT status, colaborador_id FROM tarefas WHERE id = ? LIMIT 1 FOR UPDATE');
    $taskIdInt = (int) $tarefa_id;
    $currentStmt->bind_param('i', $taskIdInt);
    $currentStmt->execute();
    $current = $currentStmt->get_result()->fetch_assoc();
    $currentStmt->close();
    if ($current && strcasecmp((string) ($current['status'] ?? ''), 'Não iniciado') === 0 && strcasecmp((string) $status, 'Em andamento') === 0) {
        flow_wip_assert_novo_inicio($conn, (int) ($current['colaborador_id'] ?? 0));
    }

$sql = 'UPDATE tarefas SET ' . implode(', ', $sets) . ' WHERE id = ?';
$types .= 'i'; // id é inteiro
$values[] = $tarefa_id;

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    throw new RuntimeException('Erro na preparação da query: ' . $conn->error);
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
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Tarefa atualizada com sucesso']);
} else {
    throw new RuntimeException('Erro ao atualizar tarefa: ' . $stmt->error);
}

$stmt->close();
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    if ($e instanceof FlowWipException) {
        http_response_code(409);
        echo json_encode(flow_wip_exception_payload($e), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
$conn->close();
?>

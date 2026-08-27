<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/tarefa_planejamento_contexto_helper.php';

if (empty($_SESSION['logado'])) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}
$tarefaId = (int) ($_GET['funcao_imagem_id'] ?? 0);
$previsao = flow_tarefa_planejamento_data_valida($_GET['previsao_conclusao'] ?? null);
if (!$tarefaId || !$previsao) {
    http_response_code(422);
    echo json_encode(['success' => false]);
    exit;
}
$stmt = $conn->prepare('SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.status, ico.tipo_imagem FROM funcao_imagem fi JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id WHERE fi.idfuncao_imagem = ? LIMIT 1');
$stmt->bind_param('i', $tarefaId);
$stmt->execute();
$tarefa = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tarefa) {
    http_response_code(404);
    echo json_encode(['success' => false]);
    exit;
}
$contexto = flow_tarefa_contexto_planejamento($conn, $tarefa);
$prazo = $contexto['prazo_necessario'] ?? null;
$diferenca = $prazo ? flow_tarefa_planejamento_desvio($prazo, $previsao) : null;
echo json_encode(['success' => true, 'prazo_necessario' => $prazo, 'diferenca_dias_uteis' => $diferenca], JSON_UNESCAPED_UNICODE);
$conn->close();

<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/tarefa_planejamento_contexto_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['logado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tarefaId = (int) ($_POST['funcao_imagem_id'] ?? 0);
$previsao = flow_tarefa_planejamento_data_valida($_POST['previsao_conclusao'] ?? null);
$justificativa = trim((string) ($_POST['justificativa'] ?? ''));
$atorColaboradorId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;
$atorUsuarioId = isset($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : null;

if (!$tarefaId || !$previsao) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Informe uma previsão de conclusão válida.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!flow_tarefa_planejamento_persistencia_disponivel($conn)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'A migration da previsão de conclusão ainda não foi aplicada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare('SELECT fi.idfuncao_imagem, fi.imagem_id, fi.funcao_id, fi.status, fi.colaborador_id, ico.tipo_imagem FROM funcao_imagem fi JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id WHERE fi.idfuncao_imagem = ? LIMIT 1');
$stmt->bind_param('i', $tarefaId);
$stmt->execute();
$tarefa = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tarefa) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($atorColaboradorId && (int) $tarefa['colaborador_id'] !== $atorColaboradorId && (int) ($_SESSION['nivel_acesso'] ?? 0) < 2) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Você não pode alterar a previsão de outra pessoa.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$contexto = flow_tarefa_contexto_planejamento($conn, $tarefa);
$prazoNecessario = $contexto['prazo_necessario'] ?? null;
$diferenca = $prazoNecessario ? flow_tarefa_planejamento_desvio($prazoNecessario, $previsao) : null;
if ($diferenca !== null && $diferenca > 0 && $justificativa === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Explique o que impede a conclusão dentro do prazo necessário.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (mb_strlen($justificativa, 'UTF-8') > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A justificativa deve ter no máximo 500 caracteres.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('SELECT previsao_conclusao, justificativa FROM funcao_imagem_previsao_conclusao WHERE funcao_imagem_id = ? FOR UPDATE');
    $stmt->bind_param('i', $tarefaId);
    $stmt->execute();
    $anterior = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $justificativaDb = $justificativa !== '' ? $justificativa : null;
    $stmt = $conn->prepare('INSERT INTO funcao_imagem_previsao_conclusao (funcao_imagem_id, previsao_conclusao, justificativa, criado_por_colaborador_id, criado_por_usuario_id) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE previsao_conclusao = VALUES(previsao_conclusao), justificativa = VALUES(justificativa)');
    $stmt->bind_param('issii', $tarefaId, $previsao, $justificativaDb, $atorColaboradorId, $atorUsuarioId);
    $stmt->execute();
    $stmt->close();

    $mudou = !$anterior || (string) $anterior['previsao_conclusao'] !== $previsao || (string) ($anterior['justificativa'] ?? '') !== (string) ($justificativaDb ?? '');
    if ($mudou) {
        $evento = $anterior ? 'PREVISAO_ALTERADA' : 'PREVISAO_INFORMADA';
        $previsaoAnterior = $anterior['previsao_conclusao'] ?? null;
        $versao = $contexto['versao_id'] ?? null;
        $stmt = $conn->prepare('INSERT INTO funcao_imagem_previsao_historico (funcao_imagem_id, evento, prazo_necessario, previsao_anterior, previsao_conclusao, diferenca_dias_uteis, justificativa, versao_planejamento_id, ator_colaborador_id, ator_usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issssisiii', $tarefaId, $evento, $prazoNecessario, $previsaoAnterior, $previsao, $diferenca, $justificativaDb, $versao, $atorColaboradorId, $atorUsuarioId);
        $stmt->execute();
        $stmt->close();
    }
    $conn->commit();
    $contexto['previsao_colaborador'] = $previsao;
    $contexto['justificativa_previsao'] = $justificativaDb;
    $contexto['diferenca_previsao_dias_uteis'] = $diferenca;
    echo json_encode(['success' => true, 'planejamento' => $contexto], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível salvar sua previsão.'], JSON_UNESCAPED_UNICODE);
}

$conn->close();

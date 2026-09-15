<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/motor_requisitos_helper.php';
require_once __DIR__ . '/../helpers/unidade_trabalho_helper.php';

function emptyToNull($value)
{
    return ($value !== '' && $value !== null) ? $value : null;
}

$idFuncaoAnimacao = isset($_POST['cardId']) ? (int) $_POST['cardId'] : 0;
$status = isset($_POST['status']) ? emptyToNull($_POST['status']) : null;
$prazo = isset($_POST['prazo']) ? emptyToNull($_POST['prazo']) : null;
$observacao = isset($_POST['observacao']) ? emptyToNull($_POST['observacao']) : null;

if ($idFuncaoAnimacao <= 0) {
    echo json_encode(['error' => 'Parâmetro cardId é obrigatório']);
    exit;
}
if (empty($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'code' => 'UNAUTHENTICATED', 'message' => 'Sessão inválida.']);
    exit;
}

try {
    $conn->begin_transaction();
    $stmtCurrent = $conn->prepare('SELECT status, colaborador_id FROM funcao_animacao WHERE id = ? LIMIT 1 FOR UPDATE');
    $stmtCurrent->bind_param('i', $idFuncaoAnimacao);
    $stmtCurrent->execute();
    $current = $stmtCurrent->get_result()->fetch_assoc();
    $stmtCurrent->close();
    if ($current && strcasecmp((string) ($current['status'] ?? ''), 'Não iniciado') === 0 && strcasecmp((string) $status, 'Em andamento') === 0) {
        flow_wip_assert_novo_inicio($conn, (int) ($current['colaborador_id'] ?? 0));
        $avaliacao = motor_requisitos_avaliar_funcao_animacao($conn, $idFuncaoAnimacao);
        if (motor_requisitos_tem_bloqueio_producao($avaliacao)) {
            throw new DomainException('Conclua todas as pendências de Produção antes de iniciar a tarefa.');
        }
    }
    $stmt = $conn->prepare("UPDATE funcao_animacao SET status = ?, prazo = ?, observacao = ? WHERE id = ?");
    $stmt->bind_param('sssi', $status, $prazo, $observacao, $idFuncaoAnimacao);
    $stmt->execute();
    $stmt->close();
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Função de animação atualizada com sucesso']);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    if ($e instanceof FlowWipException) {
        http_response_code(409);
        echo json_encode(flow_wip_exception_payload($e), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        http_response_code($e instanceof DomainException ? 422 : 500);
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'avaliacao' => $avaliacao ?? null], JSON_UNESCAPED_UNICODE);
    }
}
$conn->close();

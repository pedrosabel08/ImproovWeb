<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/inicio_conjunto_helper.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function flow_joint_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    flow_joint_response(401, ['success' => false, 'code' => 'UNAUTHENTICATED', 'message' => 'Sessão inválida.']);
}
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$modelagemId = (int) ($payload['modelagem_id'] ?? 0);
$prazo = isset($payload['prazo_modelagem']) ? trim((string) $payload['prazo_modelagem']) : null;
$actorColaboradorId = (int) ($_SESSION['idcolaborador'] ?? 0);
$actorUsuarioId = (int) ($_SESSION['idusuario'] ?? 0);
$evaluation = [];

try {
    $conn->begin_transaction();
    $evaluation = flow_inicio_conjunto_avaliar_modelagem_composicao($conn, $modelagemId, true, true);
    if (empty($evaluation['joint_start_available'])) {
        throw new DomainException((string) ($evaluation['joint_start_reason'] ?? 'JOINT_START_NOT_AVAILABLE'));
    }
    $ownerId = (int) ($evaluation['colaborador_id'] ?? 0);
    $nivel = (int) ($_SESSION['nivel_acesso'] ?? 0);
    $manager = in_array($nivel, [1, 5], true) || in_array($actorColaboradorId, [9, 21], true);
    if ($ownerId !== $actorColaboradorId && !$manager) {
        throw new RuntimeException('Você não tem permissão para iniciar esta unidade.');
    }
    $unit = flow_inicio_conjunto_registrar($conn, $evaluation, $prazo, $actorColaboradorId ?: null, $actorUsuarioId ?: null);
    $conn->commit();
    error_log(sprintf(
        '[FLOW][INICIO_CONJUNTO] unidade=%d usuario=%d ator_colaborador=%d colaborador=%d imagem=%d modelagem=%d composicao=%d resultado=COMMIT',
        (int) $unit['unit_id'],
        $actorUsuarioId,
        $actorColaboradorId,
        $ownerId,
        (int) $evaluation['imagem_id'],
        (int) $evaluation['modelagem_id'],
        (int) $evaluation['composicao_id']
    ));
    flow_joint_response(200, ['success' => true, 'message' => 'Modelagem e Composição iniciadas juntas.', 'work_unit' => $unit]);
} catch (FlowWipException $error) {
    $conn->rollback();
    error_log(sprintf(
        '[FLOW][INICIO_CONJUNTO] usuario=%d ator_colaborador=%d colaborador=%d imagem=%d modelagem=%d composicao=%d resultado=ROLLBACK motivo=%s',
        $actorUsuarioId,
        $actorColaboradorId,
        (int) ($evaluation['colaborador_id'] ?? 0),
        (int) ($evaluation['imagem_id'] ?? 0),
        $modelagemId,
        (int) ($evaluation['composicao_id'] ?? 0),
        FLOW_WIP_ERROR_CODE
    ));
    flow_joint_response(409, flow_wip_exception_payload($error));
} catch (Throwable $error) {
    $conn->rollback();
    error_log(sprintf(
        '[FLOW][INICIO_CONJUNTO] usuario=%d ator_colaborador=%d colaborador=%d imagem=%d modelagem=%d composicao=%d resultado=ROLLBACK motivo=%s',
        $actorUsuarioId,
        $actorColaboradorId,
        (int) ($evaluation['colaborador_id'] ?? 0),
        (int) ($evaluation['imagem_id'] ?? 0),
        $modelagemId,
        (int) ($evaluation['composicao_id'] ?? 0),
        $error->getMessage()
    ));
    flow_joint_response(422, ['success' => false, 'code' => 'JOINT_START_NOT_AVAILABLE', 'message' => $error->getMessage()]);
}

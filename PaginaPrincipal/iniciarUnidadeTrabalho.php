<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/inicio_operacional_helper.php';

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
    $resultadoInicio = flow_inicio_operacional_iniciar($conn, [
        'funcao_imagem_id' => $modelagemId,
        'previsao' => $prazo,
        'motivo_codigo' => $payload['motivo_codigo'] ?? null,
        'motivo_texto' => $payload['motivo_texto'] ?? null,
        'confirmar_pendencias' => !empty($payload['confirmar_pendencias']),
        'iniciar_modelagem_composicao' => true,
        'ator_colaborador_id' => $actorColaboradorId ?: null,
        'ator_usuario_id' => $actorUsuarioId ?: null,
        'nivel_acesso' => (int) ($_SESSION['nivel_acesso'] ?? 0),
    ]);
    $evaluation = $resultadoInicio['evaluation'];
    $ownerId = (int) ($evaluation['responsavel_id'] ?? 0);
    $unit = $resultadoInicio['work_unit'];
    $conn->commit();
    error_log(sprintf(
        '[FLOW][INICIO_CONJUNTO] unidade=%d usuario=%d ator_colaborador=%d colaborador=%d imagem=%d modelagem=%d composicao=%d resultado=COMMIT',
        (int) ($unit['unit_id'] ?? 0),
        $actorUsuarioId,
        $actorColaboradorId,
        $ownerId,
        0,
        $modelagemId,
        (int) (($unit['member_ids'][1] ?? 0))
    ));
    flow_joint_response(200, ['success' => true, 'message' => 'Modelagem e Composição iniciadas juntas.', 'work_unit' => $unit, 'cycle' => $resultadoInicio['cycle'], 'evaluation' => $resultadoInicio['evaluation']]);
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

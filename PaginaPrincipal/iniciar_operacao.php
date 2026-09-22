<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/inicio_operacional_helper.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function flow_inicio_operacional_responder(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['logado'])) {
    flow_inicio_operacional_responder(401, ['success' => false, 'code' => 'UNAUTHENTICATED', 'message' => 'Sessao invalida.']);
}
$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    $entrada = $_POST;
}
$entrada['ator_colaborador_id'] = !empty($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;
$entrada['ator_usuario_id'] = !empty($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : null;
$entrada['nivel_acesso'] = (int) ($_SESSION['nivel_acesso'] ?? 0);

try {
    $conn->begin_transaction();
    $acao = (string) ($entrada['acao'] ?? 'iniciar');
    $resultado = $acao === 'enviar_aprovacao'
        ? flow_inicio_operacional_enviar_aprovacao($conn, $entrada)
        : flow_inicio_operacional_iniciar($conn, $entrada);
    $conn->commit();
    error_log(sprintf('[FLOW][JANELA][INICIO] tarefa=%d ciclo=%d estado=%s resultado=COMMIT', (int) ($entrada['funcao_imagem_id'] ?? 0), (int) ($resultado['cycle']['ciclo_id'] ?? 0), (string) ($resultado['evaluation']['estado'] ?? 'SEM_REGRA')));
    flow_inicio_operacional_responder(200, $resultado);
} catch (FlowWipException $error) {
    $conn->rollback();
    flow_inicio_operacional_responder(409, flow_wip_exception_payload($error));
} catch (DomainException $error) {
    $conn->rollback();
    flow_inicio_operacional_responder(422, ['success' => false, 'code' => 'START_VALIDATION_FAILED', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    $conn->rollback();
    error_log('[FLOW][JANELA][INICIO] resultado=ROLLBACK erro=' . $error->getMessage());
    $schema = str_contains($error->getMessage(), 'migration');
    flow_inicio_operacional_responder($schema ? 503 : 500, ['success' => false, 'code' => $schema ? 'JANELA_SCHEMA_INDISPONIVEL' : 'START_FAILED', 'message' => $schema ? $error->getMessage() : 'Nao foi possivel iniciar a tarefa.']);
}


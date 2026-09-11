<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/inicio_operacional_helper.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['logado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'code' => 'UNAUTHENTICATED', 'message' => 'Sessao invalida.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = $_POST;
}
$tarefaId = (int) ($data['funcao_imagem_id'] ?? 0);
$atorColaboradorId = !empty($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;
$atorUsuarioId = !empty($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : null;

try {
    $conn->begin_transaction();
    $tarefa = flow_janela_carregar_tarefa($conn, $tarefaId, true);
    if (!$tarefa) {
        throw new DomainException('Tarefa nao encontrada.');
    }
    flow_inicio_operacional_validar_permissao($tarefa, $atorColaboradorId, (int) ($_SESSION['nivel_acesso'] ?? 0));
    $resultado = flow_janela_atualizar_previsao(
        $conn,
        $tarefaId,
        (string) ($data['previsao'] ?? ''),
        $data['motivo_codigo'] ?? null,
        $data['motivo_texto'] ?? null,
        $atorColaboradorId,
        $atorUsuarioId
    );
    $unidade = flow_janela_resolver_unidade($conn, $tarefaId, true);
    $motivo = flow_janela_validar_justificativa($conn, $resultado['estado'], $data['motivo_codigo'] ?? null, $data['motivo_texto'] ?? null);
    flow_inicio_operacional_salvar_previsao_legada($conn, $unidade['membros'], $resultado['previsao'], $motivo, $atorColaboradorId, $atorUsuarioId, 'PREVISAO_ALTERADA');
    $conn->commit();
    echo json_encode(['success' => true, 'evaluation' => $resultado], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (DomainException $error) {
    $conn->rollback();
    http_response_code(422);
    echo json_encode(['success' => false, 'code' => 'FORECAST_VALIDATION_FAILED', 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    $conn->rollback();
    error_log('[FLOW][JANELA][PREVISAO] erro=' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'FORECAST_UPDATE_FAILED', 'message' => 'Nao foi possivel atualizar a previsao.'], JSON_UNESCAPED_UNICODE);
}
$conn->close();


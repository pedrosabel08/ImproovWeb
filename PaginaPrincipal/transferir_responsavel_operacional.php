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
$atorColaboradorId = (int) ($_SESSION['idcolaborador'] ?? 0);
$nivel = (int) ($_SESSION['nivel_acesso'] ?? 0);
$gestor = in_array($nivel, [1, 5], true) || in_array($atorColaboradorId, [9, 21], true);
if (!$gestor) {
    http_response_code(403);
    echo json_encode(['success' => false, 'code' => 'FORBIDDEN', 'message' => 'A transferencia exige permissao de gestao.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = $_POST;
}

try {
    $conn->begin_transaction();
    $resultado = flow_inicio_operacional_transferir($conn, [
        'funcao_imagem_id' => (int) ($data['funcao_imagem_id'] ?? 0),
        'novo_responsavel_id' => (int) ($data['novo_responsavel_id'] ?? 0),
        'nova_previsao' => $data['nova_previsao'] ?? null,
        'motivo_transferencia' => $data['motivo_transferencia'] ?? '',
        'motivo_operacional_codigo' => $data['motivo_codigo'] ?? null,
        'motivo_operacional_texto' => $data['motivo_texto'] ?? null,
        'ator_colaborador_id' => $atorColaboradorId ?: null,
        'ator_usuario_id' => (int) ($_SESSION['idusuario'] ?? 0) ?: null,
    ]);
    if (empty($resultado['aplicada'])) {
        throw new DomainException('A tarefa nao possui ciclo ativo; atribua o responsavel pelo fluxo comum.');
    }
    $conn->commit();
    echo json_encode(['success' => true, 'transfer' => $resultado], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (FlowWipException $error) {
    $conn->rollback();
    http_response_code(409);
    echo json_encode(flow_wip_exception_payload($error), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (DomainException $error) {
    $conn->rollback();
    http_response_code(422);
    echo json_encode(['success' => false, 'code' => 'TRANSFER_VALIDATION_FAILED', 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    $conn->rollback();
    error_log('[FLOW][JANELA][TRANSFERENCIA] erro=' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'TRANSFER_FAILED', 'message' => 'Nao foi possivel transferir a unidade.'], JSON_UNESCAPED_UNICODE);
}
$conn->close();


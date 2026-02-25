<?php
/**
 * deletar_marcacao.php
 * Remove uma marcação de ambiente da planta ativa.
 * Valida que a marcação pertence a uma planta da obra informada.
 *
 * POST params:
 *   marcacao_id (int)
 *   obra_id     (int)  — usado como segurança extra (evita deleção cross-obra)
 */

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';

header('Content-Type: application/json; charset=utf-8');

// --- Auth ---
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Não autenticado.']);
    exit();
}

$nivelAcesso = (int) ($_SESSION['nivel_acesso'] ?? 0);
if (!in_array($nivelAcesso, [1, 2])) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'erro' => 'Sem permissão para deletar marcações.']);
    exit();
}

// --- Aceita JSON ou form-data ---
$body = [];
$raw = file_get_contents('php://input');
if (!empty($raw)) {
    $body = json_decode($raw, true) ?? [];
}
$input = array_merge($body, $_POST);

$marcacaoId = isset($input['marcacao_id']) ? (int) $input['marcacao_id'] : 0;
$obraId = isset($input['obra_id']) ? (int) $input['obra_id'] : 0;

if ($marcacaoId <= 0 || $obraId <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'Parâmetros inválidos.']);
    exit();
}

$conn = conectarBanco();

// --- Confirmar que a marcação pertence a uma planta desta obra ---
$stmtCheck = $conn->prepare("
    SELECT pm.id
    FROM planta_marcacoes pm
    INNER JOIN planta_compatibilizacao pc ON pc.id = pm.planta_id
    WHERE pm.id = ?
      AND pc.obra_id = ?
    LIMIT 1
");
$stmtCheck->bind_param('ii', $marcacaoId, $obraId);
$stmtCheck->execute();
$pertence = $stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

if (!$pertence) {
    $conn->close();
    echo json_encode(['sucesso' => false, 'erro' => 'Marcação não encontrada ou não pertence a esta obra.']);
    exit();
}

// --- Deletar ---
$stmtDel = $conn->prepare("DELETE FROM planta_marcacoes WHERE id = ?");
$stmtDel->bind_param('i', $marcacaoId);

if (!$stmtDel->execute()) {
    $erro = $stmtDel->error;
    $stmtDel->close();
    $conn->close();
    echo json_encode(['sucesso' => false, 'erro' => 'Erro ao deletar: ' . $erro]);
    exit();
}

$stmtDel->close();
$conn->close();

echo json_encode(['sucesso' => true, 'id_deletado' => $marcacaoId]);

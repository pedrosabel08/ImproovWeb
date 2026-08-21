<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/planejamento_producao_helper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_SESSION['logado'])) {
        http_response_code(401);
        throw new RuntimeException('Sessão inválida.');
    }
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) throw new InvalidArgumentException('Dados de confirmação inválidos.');
    $entregaId = (int) ($payload['entrega_id'] ?? 0);
    $fingerprint = trim((string) ($payload['fingerprint'] ?? ''));
    $lockVersion = (int) ($payload['lock_version'] ?? 0);
    if ($entregaId <= 0 || !preg_match('/^[a-f0-9]{64}$/', $fingerprint) || $lockVersion < 0) {
        throw new InvalidArgumentException('O plano exibido não contém a versão necessária para confirmação. Recarregue a página.');
    }
    $pessoas = is_array($payload['pessoas'] ?? null) ? $payload['pessoas'] : [];
    $pessoasValidas = [];
    foreach ($pessoas as $etapa => $quantidade) {
        if (!is_string($etapa) || !preg_match('/^[A-Z_]+$/', $etapa)) continue;
        $pessoasValidas[$etapa] = max(1, min(20, (int) $quantidade));
    }
    $conn = conectarBanco();
    $plano = flow_planejamento_persistir_confirmacao(
        $conn,
        $entregaId,
        $pessoasValidas,
        $fingerprint,
        $lockVersion,
        isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null,
        !empty($payload['replanejar']),
        isset($payload['motivo_codigo']) ? (string) $payload['motivo_codigo'] : null,
        isset($payload['motivo_observacao']) ? (string) $payload['motivo_observacao'] : null,
    );
    $conn->close();
    echo json_encode(['success' => true, 'plano' => $plano], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    $codigo = str_contains($erro->getMessage(), 'alterado') || str_contains($erro->getMessage(), 'mudou') ? 409 : 422;
    http_response_code($codigo);
    echo json_encode(['success' => false, 'message' => $erro->getMessage()], JSON_UNESCAPED_UNICODE);
}

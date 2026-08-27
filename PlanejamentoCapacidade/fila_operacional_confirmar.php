<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/planejamento_fila_confirmada_helper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_SESSION['logado'])) {
        http_response_code(401);
        throw new RuntimeException('Sessão inválida.');
    }
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload) || empty($payload['confirmado'])) {
        throw new InvalidArgumentException('Confirme a nova ordem antes de salvá-la.');
    }
    $conn = conectarBanco();
    if (!improov_usuario_eh_gestor_sidebar($conn)) {
        $conn->close();
        http_response_code(403);
        throw new RuntimeException('Somente gestores podem confirmar a fila operacional.');
    }
    $resultado = flow_fila_confirmada_confirmar(
        $conn,
        (int) ($payload['colaborador_id'] ?? 0),
        (array) ($payload['ordem'] ?? []),
        (string) ($payload['fingerprint_atual'] ?? ''),
        isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null,
        trim((string) ($payload['motivo'] ?? '')),
        [
            'entrega_id' => (int) ($payload['entrega_id'] ?? 0),
            'codigo_etapa' => strtoupper(trim((string) ($payload['codigo_etapa'] ?? ''))),
        ]
    );
    $conn->close();
    echo json_encode(['success' => true, 'resultado' => $resultado], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    $codigo = str_contains($erro->getMessage(), 'DESATUALIZADA') ? 409 : (http_response_code() >= 400 ? http_response_code() : 422);
    http_response_code($codigo);
    echo json_encode(['success' => false, 'message' => $erro->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

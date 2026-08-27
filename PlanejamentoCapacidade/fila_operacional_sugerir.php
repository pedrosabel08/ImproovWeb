<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/planejamento_fila_confirmada_helper.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_SESSION['logado'])) { http_response_code(401); throw new RuntimeException('Sessão inválida.'); }
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) throw new InvalidArgumentException('Envie os dados da fila.');
    $conn = conectarBanco();
    if (!improov_usuario_eh_gestor_sidebar($conn)) { $conn->close(); http_response_code(403); throw new RuntimeException('Somente gestores podem sugerir uma ordem operacional.'); }
    $resultado = flow_fila_confirmada_encontrar_melhor_ordem($conn, (int) ($payload['colaborador_id'] ?? 0), (int) ($payload['entrega_id'] ?? 0), strtoupper(trim((string) ($payload['codigo_etapa'] ?? ''))), (string) ($payload['fingerprint_atual'] ?? ''));
    $conn->close();
    echo json_encode(['success' => true, 'simulacao' => $resultado], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    http_response_code(str_contains($erro->getMessage(), 'DESATUALIZADA') ? 409 : (http_response_code() >= 400 ? http_response_code() : 422));
    echo json_encode(['success' => false, 'message' => $erro->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

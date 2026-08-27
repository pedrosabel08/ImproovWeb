<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/planejamento_alocacao_helper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (empty($_SESSION['logado'])) {
        http_response_code(401);
        throw new RuntimeException('Sessão inválida.');
    }
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Dados da distribuição assistida inválidos.');
    }
    $inicio = (string) ($payload['inicio'] ?? '');
    $fim = (string) ($payload['fim'] ?? '');
    $entregaId = (int) ($payload['entrega_id'] ?? 0);
    $codigo = strtoupper(trim((string) ($payload['codigo_etapa'] ?? '')));
    if (!flow_capacidade_data_valida($inicio) || !flow_capacidade_data_valida($fim) || $fim < $inicio) {
        throw new InvalidArgumentException('Informe um período válido.');
    }
    $conn = conectarBanco();
    if (!improov_usuario_eh_gestor_sidebar($conn)) {
        $conn->close();
        http_response_code(403);
        throw new RuntimeException('Somente gestores podem buscar distribuições.');
    }
    $resultado = flow_alocacao_sugerir_distribuicao($conn, $inicio, $fim, $entregaId, $codigo);
    $conn->close();
    echo json_encode(['success' => true, 'sugestao' => $resultado], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    http_response_code(str_contains($erro->getMessage(), 'mudou') ? 409 : 422);
    echo json_encode(['success' => false, 'message' => $erro->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

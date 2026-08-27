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
        throw new InvalidArgumentException('Dados de simulação inválidos.');
    }
    $inicio = (string) ($payload['inicio'] ?? '');
    $fim = (string) ($payload['fim'] ?? '');
    if (!flow_capacidade_data_valida($inicio) || !flow_capacidade_data_valida($fim) || $fim < $inicio) {
        throw new InvalidArgumentException('Informe um período válido.');
    }
    $conn = conectarBanco();
    if (!improov_usuario_eh_gestor_sidebar($conn)) {
        $conn->close();
        http_response_code(403);
        throw new RuntimeException('Somente gestores podem simular realocações.');
    }
    $opcoes = [];
    if (!empty($payload['obra_id'])) {
        $opcoes['obra_id'] = (int) $payload['obra_id'];
    }
    $simulacao = flow_alocacao_simular_movimentos($conn, $inicio, $fim, (array) ($payload['movimentos'] ?? []), $opcoes);
    $conn->close();
    echo json_encode(['success' => true, 'simulacao' => $simulacao], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    $codigo = str_contains($erro->getMessage(), 'mudou') || str_contains($erro->getMessage(), 'alterada') ? 409 : 422;
    http_response_code($codigo);
    echo json_encode(['success' => false, 'message' => $erro->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

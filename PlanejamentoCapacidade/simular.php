<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/planejamento_capacidade_simulacao_helper.php';

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
    $conflito = is_array($payload['conflito'] ?? null) ? $payload['conflito'] : [];
    $codigo = strtoupper(trim((string) ($conflito['codigo_etapa'] ?? '')));
    $semana = (string) ($conflito['semana'] ?? '');
    if (!flow_capacidade_data_valida($inicio) || !flow_capacidade_data_valida($fim) || $fim < $inicio
        || !flow_capacidade_data_valida($semana) || !isset(flow_capacidade_definicoes_etapas()[$codigo])) {
        throw new InvalidArgumentException('Informe o período e o conflito a simular.');
    }
    $conn = conectarBanco();
    if (!improov_usuario_eh_gestor_sidebar($conn)) {
        $conn->close();
        http_response_code(403);
        throw new RuntimeException('Somente gestores podem simular soluções de capacidade.');
    }
    if (!empty($payload['sugestoes'])) {
        $resultado = ['sugestoes' => array_map('flow_capacidade_simulacao_para_interface', flow_capacidade_sugerir($conn, $inicio, $fim, $codigo, $semana))];
    } else {
        $resultado = flow_capacidade_simulacao_para_interface(
            flow_capacidade_simular($conn, $inicio, $fim, $codigo, $semana, (array) ($payload['acoes'] ?? []))
        );
    }
    $conn->close();
    echo json_encode(['success' => true, 'simulacao' => $resultado], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    if (!headers_sent() && http_response_code() < 400) {
        http_response_code(422);
    }
    echo json_encode(['success' => false, 'message' => $erro->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

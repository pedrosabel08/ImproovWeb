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
    if (!is_array($payload) || empty($payload['confirmado'])) {
        throw new InvalidArgumentException('Confirme a aplicação do cenário antes de continuar.');
    }
    $inicio = (string) ($payload['inicio'] ?? '');
    $fim = (string) ($payload['fim'] ?? '');
    $conflito = (array) ($payload['conflito'] ?? []);
    $codigo = strtoupper(trim((string) ($conflito['codigo_etapa'] ?? '')));
    $semana = (string) ($conflito['semana'] ?? '');
    if (!flow_capacidade_data_valida($inicio) || !flow_capacidade_data_valida($fim) || $fim < $inicio
        || !flow_capacidade_data_valida($semana) || !isset(flow_capacidade_definicoes_etapas()[$codigo])) {
        throw new InvalidArgumentException('O cenário não possui o conflito de origem necessário. Recalcule antes de aplicar.');
    }
    $conn = conectarBanco();
    if (!improov_usuario_eh_gestor_sidebar($conn)) {
        $conn->close();
        http_response_code(403);
        throw new RuntimeException('Somente gestores podem aplicar cenários de capacidade.');
    }
    $resultado = flow_capacidade_aplicar_cenario(
        $conn,
        $inicio,
        $fim,
        $codigo,
        $semana,
        (array) ($payload['acoes'] ?? []),
        isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null,
        isset($payload['observacao']) ? (string) $payload['observacao'] : null
    );
    $conn->close();
    echo json_encode(['success' => true, 'resultado' => $resultado], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    $codigoHttp = str_contains($erro->getMessage(), 'mudou') || str_contains($erro->getMessage(), 'alterado') ? 409 : 422;
    http_response_code($codigoHttp);
    echo json_encode(['success' => false, 'message' => $erro->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

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

    $inicio = (string) ($_GET['inicio'] ?? '');
    $fim = (string) ($_GET['fim'] ?? '');
    if (!flow_capacidade_data_valida($inicio) || !flow_capacidade_data_valida($fim) || $fim < $inicio) {
        http_response_code(422);
        throw new InvalidArgumentException('Informe início e fim válidos no formato Y-m-d.');
    }

    $conn = conectarBanco();
    if (!improov_usuario_eh_gestor_sidebar($conn)) {
        http_response_code(403);
        throw new RuntimeException('Somente gestores podem consultar a Central de Alocação.');
    }

    $opcoes = [];
    if (!empty($_GET['obra_id'])) {
        $opcoes['obra_id'] = (int) $_GET['obra_id'];
    }
    if (!empty($_GET['cliente_id'])) {
        $opcoes['cliente_id'] = (int) $_GET['cliente_id'];
    }

    $resultado = flow_alocacao_consultar($conn, $inicio, $fim, $opcoes);
    $conn->close();
    echo json_encode(['success' => true, 'alocacao' => $resultado], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    if (!headers_sent() && http_response_code() < 400) {
        http_response_code(422);
    }
    echo json_encode(['success' => false, 'message' => $erro->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

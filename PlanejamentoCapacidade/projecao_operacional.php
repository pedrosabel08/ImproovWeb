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
    $conn = conectarBanco();
    if (!improov_usuario_eh_gestor_sidebar($conn)) {
        http_response_code(403);
        throw new RuntimeException('Somente gestores podem consultar a projeção operacional.');
    }
    $filtros = [];
    if (!empty($_GET['obra_id'])) $filtros['obra_id'] = (int) $_GET['obra_id'];
    if (!empty($_GET['entrega_id'])) $filtros['entrega_id'] = (int) $_GET['entrega_id'];
    if (!empty($_GET['entrega_ids'])) {
        $filtros['entrega_ids'] = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $_GET['entrega_ids'])))));
    }
    $opcoes = [];
    // Permitida apenas em teste automatizado/controlado; a interface nunca envia isso.
    if (!empty($_GET['data_referencia']) && entregas_valid_date((string) $_GET['data_referencia'])) {
        $opcoes['data_hoje'] = (string) $_GET['data_referencia'];
    }
    // V1.5B: a mesma consulta continua read-only, mas respeita decisões de
    // fila já confirmadas. Sem override, a resposta permanece DERIVADA.
    $resultado = flow_fila_confirmada_projetar($conn, $filtros, $opcoes);
    $conn->close();
    echo json_encode(['success' => true, 'projecao' => $resultado], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    if (!headers_sent() && http_response_code() < 400) http_response_code(422);
    echo json_encode(['success' => false, 'message' => $erro->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

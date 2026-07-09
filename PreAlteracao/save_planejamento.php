<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/planejamento_helpers.php';

pre_alt_planejamento_require_session();

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    pre_alt_planejamento_json_response(['success' => false, 'error' => 'Payload invalido.'], 400);
}

$loteId = isset($payload['lote_id']) && is_numeric($payload['lote_id']) ? (int) $payload['lote_id'] : 0;
if ($loteId <= 0) {
    pre_alt_planejamento_json_response(['success' => false, 'error' => 'lote_id invalido.'], 400);
}

try {
    $graph = pre_alt_planejamento_save_graph($conn, $loteId, $payload);
    $validation = pre_alt_planejamento_validate_graph($graph);
    pre_alt_planejamento_json_response([
        'success' => true,
        'graph' => $graph,
        'validation' => $validation,
    ]);
} catch (Throwable $e) {
    pre_alt_planejamento_json_response(['success' => false, 'error' => $e->getMessage()], 400);
}

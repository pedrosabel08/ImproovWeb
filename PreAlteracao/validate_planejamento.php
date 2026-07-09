<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/planejamento_helpers.php';

pre_alt_planejamento_require_session();

$payload = json_decode(file_get_contents('php://input'), true);
if (is_array($payload) && !empty($payload['lote_id'])) {
    $loteId = (int) $payload['lote_id'];
    $graph = $payload;
    if (empty($graph['items_source'])) {
        try {
            $loaded = pre_alt_planejamento_fetch_graph($conn, $loteId);
            $graph['lote'] = $loaded['lote'];
            $graph['items_source'] = $loaded['items_source'];
        } catch (Throwable $e) {
            pre_alt_planejamento_json_response(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
} else {
    $loteId = isset($_GET['lote_id']) && is_numeric($_GET['lote_id']) ? (int) $_GET['lote_id'] : 0;
    if ($loteId <= 0) {
        pre_alt_planejamento_json_response(['success' => false, 'error' => 'Informe lote_id ou payload.'], 400);
    }
    try {
        $graph = pre_alt_planejamento_fetch_graph($conn, $loteId);
    } catch (Throwable $e) {
        pre_alt_planejamento_json_response(['success' => false, 'error' => $e->getMessage()], 400);
    }
}

$validation = pre_alt_planejamento_validate_graph($graph);
pre_alt_planejamento_json_response(['success' => true, 'validation' => $validation]);

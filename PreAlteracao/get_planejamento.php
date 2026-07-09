<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/planejamento_helpers.php';

pre_alt_planejamento_require_session();

$loteId = isset($_GET['lote_id']) && is_numeric($_GET['lote_id']) ? (int) $_GET['lote_id'] : 0;
if ($loteId <= 0) {
    pre_alt_planejamento_json_response(['success' => false, 'error' => 'lote_id invalido.'], 400);
}

try {
    $graph = pre_alt_planejamento_fetch_graph($conn, $loteId);
    pre_alt_planejamento_json_response(['success' => true] + $graph);
} catch (Throwable $e) {
    pre_alt_planejamento_json_response(['success' => false, 'error' => $e->getMessage()], 400);
}

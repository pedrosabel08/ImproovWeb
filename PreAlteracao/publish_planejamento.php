<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/planejamento_helpers.php';

pre_alt_planejamento_require_session();

$payload = json_decode(file_get_contents('php://input'), true);
$loteId = isset($payload['lote_id']) && is_numeric($payload['lote_id']) ? (int) $payload['lote_id'] : 0;
if ($loteId <= 0) {
    pre_alt_planejamento_json_response(['success' => false, 'error' => 'lote_id invalido.'], 400);
}

try {
    pre_alt_planejamento_json_response(pre_alt_planejamento_publish($conn, $loteId));
} catch (Throwable $e) {
    pre_alt_planejamento_json_response(['success' => false, 'error' => $e->getMessage()], 400);
}

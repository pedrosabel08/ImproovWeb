<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (empty($_SESSION['logado'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/historico_data.php';

$idObra = isset($_GET['idobra']) ? intval($_GET['idobra']) : 0;

if ($idObra <= 0 || !improov_usuario_pode_acessar_obra($conn, $idObra)) {
    http_response_code(403);
    echo json_encode(['error' => 'Obra indisponível.']);
    exit;
}
try {
    echo json_encode(historicoRegistros($conn, $idObra), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    error_log('[historico] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível carregar o histórico.']);
}

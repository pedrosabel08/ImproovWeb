<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
if (empty($_SESSION['logado']) || empty($_SESSION['idcolaborador'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); header('Allow: GET');
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/alteracao_planner_helper.php';
try {
    $valor = $_GET['semana'] ?? null;
    if ($valor !== null && !is_string($valor)) throw new InvalidArgumentException('Semana inválida.');
    $semana = flow_alt_semana($valor);
    $conn = conectarBanco();
    echo json_encode(flow_alt_planner_carregar($conn, $semana, improov_sidebar_contexto_usuario($conn)), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    $conn->close();
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Alterações planner: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível carregar as alterações. Tente novamente.'], JSON_UNESCAPED_UNICODE);
}

<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/planned_function_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$obraId = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : 0;
if ($obraId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Obra inválida.']);
    exit;
}

try {
    $dataset = dashboard_fetch_planned_queue_dataset($conn, $obraId);
    echo json_encode([
        'success' => true,
        'obra_id' => $obraId,
        'planning_ready' => (bool) ($dataset['summary']['planning_ready'] ?? false),
        'summary' => $dataset['summary'],
        'functions' => $dataset['functions'],
        'collaborators_by_function' => $dataset['collaborators_by_function'],
        'groups' => $dataset['groups'],
    ]);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
}
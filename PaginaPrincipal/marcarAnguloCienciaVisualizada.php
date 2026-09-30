<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/angulo_ciencia_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['logado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
    exit;
}
$entrada = json_decode(file_get_contents('php://input'), true);
$entrada = is_array($entrada) ? $entrada : $_POST;
$funcaoImagemId = (int) ($entrada['funcao_imagem_id'] ?? 0);
$colaboradorSessaoId = (int) ($_SESSION['idcolaborador'] ?? 0);
$colaboradorId = (int) ($entrada['colaborador_id'] ?? $colaboradorSessaoId);
if ($funcaoImagemId <= 0 || $colaboradorId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}
if (
    $colaboradorId !== $colaboradorSessaoId
    && !in_array((int) ($_SESSION['nivel_acesso'] ?? 0), [1, 5], true)
    && !in_array($colaboradorSessaoId, [9, 21], true)
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissao para marcar a visualizacao deste colaborador.']);
    exit;
}
try {
    $conn->begin_transaction();
    $visualizado = flow_angulo_ciencia_marcar_visualizado($conn, $funcaoImagemId, $colaboradorId);
    $conn->commit();
    echo json_encode(['success' => true, 'visualizado' => $visualizado]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível registrar a visualização.']);
}

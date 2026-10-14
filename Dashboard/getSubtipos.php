<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/subtipo_imagem_helper.php';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true, 64, JSON_THROW_ON_ERROR);
        echo json_encode(flow_subtipo_save($conn, $body['nome'] ?? null), JSON_UNESCAPED_UNICODE);
    } elseif ((int) ($_GET['obra_id'] ?? 0) > 0) {
        $groups = flow_subtipos($conn, (int) $_GET['obra_id']);
        echo json_encode($groups + ['permite_cadastro' => !$groups['obra'] && !$groups['demais']], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode($conn->query('SELECT id, nome FROM subtipo_imagem ORDER BY nome')->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível carregar ou salvar o subtipo.'], JSON_UNESCAPED_UNICODE);
} finally {
    $conn->close();
}

<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../Render/pos_referencias_helper.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");


header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['logado']) || empty($_SESSION['idcolaborador'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => 'Sessão expirada.']);
    exit;
}

$referenceId = (int)($_REQUEST['referencia_id'] ?? 0);
if ($referenceId <= 0) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Referência inválida.']);
    exit;
}

try {
    pos_referencias_ensure_annotations_schema($conn);
    $authorId = (int)$_SESSION['idcolaborador'];
    $method = strtoupper((string)($_POST['_method'] ?? $_SERVER['REQUEST_METHOD']));

    if ($method === 'GET') {
        echo json_encode([
            'sucesso' => true,
            'autor_atual_id' => $authorId,
            'comentarios' => pos_referencias_annotations_list($conn, $referenceId),
        ]);
        exit;
    }

    if ($method === 'DELETE') {
        $annotationId = (int)($_POST['comentario_id'] ?? 0);
        if ($annotationId <= 0 || !pos_referencias_annotation_remove($conn, $annotationId, $authorId)) {
            throw new RuntimeException('A anotação não pode ser removida.');
        }
        echo json_encode(['sucesso' => true]);
        exit;
    }

    $text = trim((string)($_POST['texto'] ?? ''));
    $type = (string)($_POST['tipo'] ?? 'ponto');
    $x = isset($_POST['x']) ? (float)$_POST['x'] : null;
    $y = isset($_POST['y']) ? (float)$_POST['y'] : null;
    $path = isset($_POST['path_data']) ? (string)$_POST['path_data'] : null;
    $color = (string)($_POST['cor'] ?? '#f59e0b');
    $width = (int)($_POST['espessura'] ?? 2);
    $hasDrawing = array_key_exists('possui_desenho', $_POST) ? filter_var($_POST['possui_desenho'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
    $id = pos_referencias_annotation_create($conn, $referenceId, $authorId, $text, $type, $x, $y, $path, $color, $width, $hasDrawing);
    echo json_encode(['sucesso' => true, 'comentario_id' => $id]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}

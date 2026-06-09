<?php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/tipos_animacao_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true);
        $input = is_array($body) ? $body : $_POST;
        $nome = $input['nome'] ?? '';

        $tipo = animacao_tipo_salvar($conn, $nome);
        echo json_encode(['success' => true, 'id' => $tipo['id'], 'nome' => $tipo['nome']]);
        $conn->close();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['success' => false, 'error' => 'Metodo invalido']);
        $conn->close();
        exit;
    }

    echo json_encode(animacao_tipo_listar($conn));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();

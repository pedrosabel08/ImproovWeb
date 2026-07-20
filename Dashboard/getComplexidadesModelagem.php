<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

$result = $conn->query(
    'SELECT id, codigo, nome
       FROM complexidade_modelagem
      WHERE ativo = 1
      ORDER BY ordem ASC, nome ASC'
);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível carregar as classificações.']);
    exit;
}

echo json_encode($result->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
$conn->close();

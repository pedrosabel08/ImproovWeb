<?php
// get_imagens.php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

$obra_id = isset($_GET['obra_id']) ? intval($_GET['obra_id']) : null;

if ($obra_id) {
    $sql = "SELECT idimagens_cliente_obra AS id, imagem_nome AS nome FROM imagens_cliente_obra WHERE obra_id = ? ORDER BY imagem_nome";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $obra_id);
} else {
    $sql = "SELECT idimagens_cliente_obra AS id, imagem_nome AS nome FROM imagens_cliente_obra ORDER BY imagem_nome LIMIT 200";
    $stmt = $conn->prepare($sql);
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar imagens.']);
    exit;
}

$res = $stmt->get_result();
$itens = [];
while ($row = $res->fetch_assoc()) {
    $itens[] = $row;
}

echo json_encode($itens);

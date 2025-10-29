<?php
require_once '../conexao.php';

$obra_id = $_GET['obra_id'] ?? null;
$status_id = $_GET['status_id'] ?? null;

if (!$obra_id || !$status_id) {
    echo json_encode([]);
    exit;
}

// Ajuste conforme sua estrutura real de imagens
$stmt = $conn->prepare("SELECT idimagens_cliente_obra AS id, imagem_nome AS nome, antecipada
    FROM imagens_cliente_obra
    WHERE obra_id = ? AND status_id = ?
");
$stmt->bind_param("ii", $obra_id, $status_id);
$stmt->execute();
$result = $stmt->get_result();

$imagens = [];
while ($row = $result->fetch_assoc()) {
    $imagens[] = $row;
}

echo json_encode($imagens);

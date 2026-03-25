<?php
header('Content-Type: application/json');

include '../conexao.php';

$imagem_id = intval($_POST['imagem_id'] ?? 0);
$par_tipo  = $_POST['par_tipo'] ?? '';

if (!$imagem_id || $par_tipo !== 'caderno_filtro') {
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    exit;
}

$stmt = $conn->prepare(
    "INSERT IGNORE INTO funcao_par_separado (imagem_id, par_tipo) VALUES (?, ?)"
);
$stmt->bind_param('is', $imagem_id, $par_tipo);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);

$conn->close();

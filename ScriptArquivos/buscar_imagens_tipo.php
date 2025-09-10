<?php
header('Content-Type: application/json');
include 'conexao.php';

$obra_id = $_GET['obra_id'] ?? null;
$tipo_imagem = $_GET['tipo_imagem'] ?? null;

if (!$obra_id || !$tipo_imagem) {
    echo json_encode([]);
    exit;
}

// Busca imagens da obra do tipo selecionado
$stmt = $conn->prepare("SELECT idimagens_cliente_obra as idimagem, imagem_nome FROM imagens_cliente_obra WHERE obra_id = ? AND tipo_imagem = ?");
$stmt->bind_param("is", $obra_id, $tipo_imagem);
$stmt->execute();
$result = $stmt->get_result();

$imagens = [];
while ($row = $result->fetch_assoc()) {
    $imagens[] = [
        'idimagem' => $row['idimagem'],
        'imagem_nome' => $row['imagem_nome'] 
    ];
}

echo json_encode($imagens);

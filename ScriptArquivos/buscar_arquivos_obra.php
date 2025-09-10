<?php
header('Content-Type: application/json');
include 'conexao.php'; // conexão MySQL

$obra_id = $_GET['obra_id'] ?? null;
if (!$obra_id) {
    echo json_encode([]);
    exit;
}

// Busca arquivos da obra
$stmt = $conn->prepare("SELECT idarquivo, nome_original, caminho FROM arquivos WHERE obra_id = ? ORDER BY recebido_em DESC");
$stmt->bind_param("i", $obra_id);
$stmt->execute();
$result = $stmt->get_result();

$arquivos = [];
while ($row = $result->fetch_assoc()) {
    $arquivos[] = $row;
}

echo json_encode($arquivos);

<?php
header('Content-Type: application/json');
include '../conexao.php';

$result = $conn->query("SELECT id, nome FROM subtipo_imagem ORDER BY id");
if (!$result) {
    echo json_encode([]);
    exit;
}

echo json_encode($result->fetch_all(MYSQLI_ASSOC));
$conn->close();

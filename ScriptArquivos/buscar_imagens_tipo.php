<?php
require 'conexao.php';

$obra_id = isset($_GET['obra_id']) ? (int)$_GET['obra_id'] : 0;
$tipos = isset($_GET['tipos']) ? json_decode($_GET['tipos'], true) : [];

if (!$obra_id || empty($tipos)) {
    echo json_encode([]);
    exit;
}

// Monta placeholders dinâmicos
$placeholders = implode(',', array_fill(0, count($tipos), '?'));
$types = str_repeat('s', count($tipos));

$sql = "SELECT idimagens_cliente_obra as idimagem, imagem_nome 
        FROM imagens_cliente_obra 
        WHERE obra_id = ? AND tipo_imagem IN ($placeholders)";
$stmt = $conn->prepare($sql);

$params = array_merge([$obra_id], $tipos);
$stmt->bind_param('i' . $types, ...$params);

$stmt->execute();
$res = $stmt->get_result();

$imagens = [];
while ($row = $res->fetch_assoc()) {
    $imagens[] = $row;
}

echo json_encode($imagens);

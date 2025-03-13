<?php

$pdo = new PDO('mysql:host=mysql.improov.com.br;dbname=improov', 'improov', 'Impr00v');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ap_imagem_id = $_GET['ap_imagem_id'] ?? null;

if (!$ap_imagem_id) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM comentarios_imagem WHERE ap_imagem_id = :ap_imagem_id");
    $stmt->bindParam(':ap_imagem_id', $ap_imagem_id, PDO::PARAM_INT);
    $stmt->execute();
    $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($comentarios);
} catch (PDOException $e) {
    echo json_encode(['erro' => $e->getMessage()]);
}

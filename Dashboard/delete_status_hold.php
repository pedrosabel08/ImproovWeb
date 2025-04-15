<?php
// delete_status_hold.php

include '../conexao.php';

$imagem_id = $_POST['imagem_id'] ?? null;
$status = $_POST['status'] ?? null;

if ($imagem_id && $status) {
    $stmt = $conn->prepare("DELETE FROM status_hold WHERE imagem_id = ? AND descricao = ?");
    $stmt->bind_param("is", $imagem_id, $status);

    if ($stmt->execute()) {
        echo "Status removido com sucesso.";
    } else {
        echo "Erro ao remover: " . $conn->error;
    }

    $stmt->close();
} else {
    echo "Dados incompletos.";
}

$conn->close();

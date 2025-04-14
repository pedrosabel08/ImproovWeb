<?php

include '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);


    $sql = "DELETE FROM historico_aprovacoes_imagens WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        echo "Imagem excluída com sucesso.";
    } else {
        echo "Erro ao excluir imagem.";
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Requisição inválida.";
}

<?php
// excluirFuncao.php

include 'conexao.php';

if (isset($_GET['id'])) {
    $funcaoId = $_GET['id'];

    // Prepara a consulta SQL para excluir a função
    $sql = "DELETE FROM funcao_imagem WHERE idfuncao_imagem = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $funcaoId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'error' => 'ID da função não fornecido.']);
}
?>
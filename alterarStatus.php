<?php
// alterarStatus.php

include 'conexao.php';

if (isset($_POST['imagem_id']) && isset($_POST['status_id'])) {
    $imagem_id = $_POST['imagem_id'];
    $status_id = $_POST['status_id'];

    // Prepara a consulta SQL para excluir a função
    $sql = "UPDATE imagens_cliente_obra SET substatus_id = ? WHERE idimagens_cliente_obra = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $status_id, $imagem_id);

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

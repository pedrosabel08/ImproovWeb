<?php

include 'conexao.php';

if (isset($_POST['obra_id']) && isset($_POST['prazo']) && isset($_POST['tipo_entrega'])) {
    $obra_id = $_POST['obra_id'];
    $prazo = $_POST['prazo'];
    $tipo_entrega = $_POST['tipo_entrega'];

    $sql = "INSERT INTO obra_prazo (obra_id, prazo, tipo_entrega) VALUES ('$obra_id', '$prazo', '$tipo_entrega')";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao inserir o prazo: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
}

$conn->close();

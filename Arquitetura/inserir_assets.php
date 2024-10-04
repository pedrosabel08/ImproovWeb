<?php
include 'conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $imagem_id = isset($_POST['imagem_id']) ? $_POST['imagem_id'] : null;
    $colaborador_id = 1; 
    $funcao_id = 8; 
    $prazo = isset($_POST['prazo']) ? $_POST['prazo'] : null;
    $status = isset($_POST['status']) ? $_POST['status'] : null;

    $stmt = $conn->prepare("INSERT INTO funcao_imagem (idimagem, colaborador_id, funcao_id, prazo, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiss", $imagem_id, $colaborador_id, $funcao_id, $prazo, $status);

    if ($stmt->execute()) {
        echo "Dados inseridos com sucesso!";
    } else {
        echo "Erro: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();

<?php
// Inclua a conexão com o banco de dados
include('conexao.php');

// Verifique se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coletando dados do formulário
    $status = $_POST['status'];
    $prazo = $_POST['prazo'];
    $idfuncao_imagem = $_POST['idfuncao_imagem'];

    // Consulta de atualização (UPDATE)
    $sql = "UPDATE funcao_imagem 
            SET status = ?, prazo = ?
            WHERE idfuncao_imagem = ?";

    // Preparando a consulta para evitar SQL Injection
    if ($stmt = $conn->prepare($sql)) {
        // Vinculando os parâmetros
        $stmt->bind_param("ssi", $status, $prazo, $idfuncao_imagem);

        // Executa a consulta
        if ($stmt->execute()) {
            echo "Atualização feita com sucesso!";
        } else {
            echo "Erro ao atualizar: " . $stmt->error;
        }

        // Fecha a declaração
        $stmt->close();
    } else {
        echo "Erro de preparação da consulta: " . $conn->error;
    }
}

// Fecha a conexão
$conn->close();

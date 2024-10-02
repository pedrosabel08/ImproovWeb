<?php

header("Access-Control-Allow-Origin: *"); // Allows all domains
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Allow specific methods
header("Access-Control-Allow-Headers: Content-Type");

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// Verificar se os dados foram enviados via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obter os dados do POST
    $idcontato = isset($_POST['idcontato']) ? $_POST['idcontato'] : null; // ID do contato para verificar se já existe
    $idcliente = isset($_POST['idcliente']) ? $_POST['idcliente'] : null; // ID do cliente associado
    $email = $_POST['email'];
    $nome_contato = $_POST['nome_contato'];
    $cargo = $_POST['cargo'];

    // Se o idcontato for enviado, faz o UPDATE
    if ($idcontato) {
        $sql = "UPDATE contato_cliente 
                SET email = ?, nome_contato = ?, cargo = ? 
                WHERE idcontato = ?";

        // Preparar a consulta
        $stmt = $conn->prepare($sql);

        // Verificar se a preparação falhou
        if (!$stmt) {
            die("Erro na preparação da consulta: " . $conn->error);
        }

        // Bind dos parâmetros (adicionando o idcontato no final)
        $stmt->bind_param("sssi", $email, $nome_contato, $cargo, $idcontato);

        // Executar o UPDATE
        if ($stmt->execute()) {
            echo "Dados atualizados com sucesso!";
        } else {
            echo "Erro ao atualizar dados: " . $stmt->error;
        }
    } else {
        // Se não houver idcontato, faz o INSERT
        $sql = "INSERT INTO contato_cliente (email, nome_contato, cargo, cliente_id) 
                VALUES (?, ?, ?, ?)";

        // Preparar a consulta
        $stmt = $conn->prepare($sql);

        // Verificar se a preparação falhou
        if (!$stmt) {
            die("Erro na preparação da consulta: " . $conn->error);
        }

        // Bind dos parâmetros
        $stmt->bind_param("sssi", $email, $nome_contato, $cargo, $idcliente);

        // Executar o INSERT
        if ($stmt->execute()) {
            echo "Dados inseridos com sucesso!";
        } else {
            echo "Erro ao inserir dados: " . $stmt->error;
        }
    }

    $stmt->close();
    $conn->close();
}

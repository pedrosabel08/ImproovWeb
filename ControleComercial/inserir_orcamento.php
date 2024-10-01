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
    $resp = $_POST['resp'];
    $contato = $_POST['contato'];
    $construtora = $_POST['construtora'];
    $obra = $_POST['obra'];
    $valor = $_POST['valor'];
    $status = $_POST['status'];
    $mes = $_POST['mes'];
    $year = date("Y");

    $sql = "INSERT INTO controle_comercial (resp, contato, construtora, obra, valor, status, mes, ano) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    // Preparar a consulta
    $stmt = $conn->prepare($sql);

    // Verificar se a preparação falhou
    if (!$stmt) {
        die("Erro na preparação da consulta: " . $conn->error);
    }

    // Bind dos parâmetros
    $stmt->bind_param("ssssdsss", $resp, $contato, $construtora, $obra, $valor, $status, $mes, $year);

    // Tentar executar a declaração
    if ($stmt->execute()) {
        // Verificar se a inserção foi bem-sucedida
        if ($stmt->affected_rows > 0) {
            echo "Dados inseridos com sucesso!";
        } else {
            echo "Nenhum dado foi inserido.";
        }
    } else {
        echo "Erro ao inserir ou atualizar dados: " . $stmt->error; // Usar o erro do stmt
    }

    $stmt->close();
    $conn->close();
}

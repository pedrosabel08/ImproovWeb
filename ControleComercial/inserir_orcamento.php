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
    // Verificar se o idcontrole foi enviado
    $idcontrole = isset($_POST['idcontrole']) ? $_POST['idcontrole'] : null;
    $resp = $_POST['resp'];
    $contato = $_POST['contato'];
    $construtora = $_POST['construtora'];
    $obra = $_POST['obra'];
    $valor = $_POST['valor'];
    $status = $_POST['status'];
    $mes = $_POST['mes'];
    $year = date("Y");

    // Se o idcontrole for enviado, faz o UPDATE
    if ($idcontrole) {
        $sql = "UPDATE controle_comercial 
                SET resp = ?, contato = ?, construtora = ?, obra = ?, valor = ?, status = ?, mes = ?, ano = ? 
                WHERE idcontrole = ?";

        // Preparar a consulta
        $stmt = $conn->prepare($sql);

        // Verificar se a preparação falhou
        if (!$stmt) {
            die("Erro na preparação da consulta: " . $conn->error);
        }

        // Bind dos parâmetros (adicionando o idcontrole no final)
        $stmt->bind_param("ssssdsssi", $resp, $contato, $construtora, $obra, $valor, $status, $mes, $year, $idcontrole);

        // Executar o UPDATE
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo "Dados atualizados com sucesso!";
            } else {
                echo "Nenhum dado foi atualizado.";
            }
        } else {
            echo "Erro ao atualizar dados: " . $stmt->error;
        }
    } else {
        // Se não houver idcontrole, faz o INSERT
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

        // Executar o INSERT
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo "Dados inseridos com sucesso!";
            } else {
                echo "Nenhum dado foi inserido.";
            }
        } else {
            echo "Erro ao inserir dados: " . $stmt->error;
        }
    }

    $stmt->close();
    $conn->close();
}

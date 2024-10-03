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
    $colaborador_id = $_POST['final_id'];
    $cliente_id = $_POST['cliente_id'];
    $obra_id = $_POST['obra_id'];
    $data_anima = date('Y-m-d'); // Data atual no formato 'YYYY-MM-DD'
    $imagem_id = $_POST['imagem_id'];
    $status_cena = $_POST['status_cena'];
    $prazo_cena = $_POST['prazo_cena'];
    $status_render = $_POST['status_render'];
    $prazo_render = $_POST['prazo_render'];
    $status_pos = $_POST['status_pos'];
    $prazo_pos = $_POST['prazo_pos'];
    $duracao = $_POST['duracao'];
    $status_anima = $_POST['status_anima'];

    // Inserir ou atualizar dados

    if ($stmt->execute()) {
        echo "Dados inseridos ou atualizados com sucesso!";
    } else {
        echo "Erro ao inserir ou atualizar dados: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}

<?php
// Conexão com o banco de dados (substitua pelas suas configurações)
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

// Se o formulário for enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $obra_id = $_POST['obra_id'];
    $imagem_nome = $_POST['imagem_nome'];

    // Sanitizar os dados recebidos
    $obra_id = $conn->real_escape_string($obra_id);
    $imagem_nome = $conn->real_escape_string($imagem_nome);

    // Verificar se a imagem já existe na tabela `imagem_animacao`
    $sql_check = "SELECT * FROM imagem_animacao WHERE obra_id = '$obra_id' AND imagem_nome = '$imagem_nome'";
    $result_check = $conn->query($sql_check);

    if ($result_check->num_rows > 0) {
        // Imagem já existe, exibe um alerta em JavaScript
        echo "<script>alert('Já existe uma imagem com este nome para esta obra!');</script>";
    } else {
        // Inserir os dados na tabela `imagem_animacao`
        $sql_insert = "INSERT INTO imagem_animacao (obra_id, imagem_nome) VALUES ('$obra_id', '$imagem_nome')";

        if ($conn->query($sql_insert) === TRUE) {
            echo "<script>alert('Imagem inserida com sucesso!');</script>";
        } else {
            echo "Erro ao inserir imagem: " . $conn->error;
        }
    }
}

// Fechar a conexão com o banco de dados
$conn->close();

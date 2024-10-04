<?php
// Arquivo: conexao.php

// Dados de conexão
$servername = 'mysql.improov.com.br';
$username = 'improov';
$password = 'Impr00v';
$dbname = 'improov';

// Conexão com o banco de dados
$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica se houve erro na conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

// Define o charset para utf8mb4
$conn->set_charset('utf8mb4');

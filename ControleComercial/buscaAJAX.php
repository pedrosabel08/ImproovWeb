<?php

header("Access-Control-Allow-Origin: *"); // Allows all domains
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Allow specific methods
header("Access-Control-Allow-Headers: Content-Type");

$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

$conn->set_charset('utf8mb4');

// Verificar a conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $idSelecionado = $_GET['ajid'];

    // Proteção contra SQL Injection
    $idSelecionado = $conn->real_escape_string($idSelecionado);

    $sql = "SELECT idcontrole, resp, contato, construtora, obra, valor, status, mes 
            FROM controle_comercial 
            WHERE idcontrole = '$idSelecionado';";
    
    $result = $conn->query($sql);

    $response = array();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $response[] = $row;
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
}
?>

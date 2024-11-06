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
    $idImagemSelecionada = $_GET['ajid'];

    // Proteção contra SQL Injection
    $idImagemSelecionada = $conn->real_escape_string($idImagemSelecionada);

    $sql = "SELECT i.idimagens_cliente_obra, c.nome_cliente, o.nome_obra, i.recebimento_arquivos, i.data_inicio, i.prazo, MAX(i.imagem_nome) AS imagem_nome, i.prazo AS prazo_estimado, s.nome_status, i.tipo_imagem, i.antecipada FROM imagens_cliente_obra i 
            JOIN cliente c ON i.cliente_id = c.idcliente 
            JOIN obra o ON i.obra_id = o.idobra 
            LEFT JOIN funcao_imagem fi ON i.idimagens_cliente_obra = fi.imagem_id 
            LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao 
            LEFT JOIN colaborador co ON fi.colaborador_id = co.idcolaborador 
            LEFT JOIN status_imagem s ON i.status_id = s.idstatus 
            WHERE i.idimagens_cliente_obra = $idImagemSelecionada";

    $result = $conn->query($sql);

    $response = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $response[] = $row;
        }
    }
    header('Content-Type: application/json');
    echo json_encode($response);
}

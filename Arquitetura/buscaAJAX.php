<?php

header("Access-Control-Allow-Origin: *"); // Allows all domains
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Allow specific methods
header("Access-Control-Allow-Headers: Content-Type");

include 'conexao.php';

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $idLinhaSelecionada = $_GET['ajid'];

    // Proteção contra SQL Injection
    $idLinhaSelecionada = $conn->real_escape_string($idLinhaSelecionada);

    $sql = "SELECT f.idfuncao_imagem, col.nome_colaborador, cli.nome_cliente, o.nome_obra, o.idobra,
        i.imagem_nome, f.status, f.prazo
        FROM funcao_imagem f
        LEFT JOIN colaborador col ON f.colaborador_id = col.idcolaborador
        LEFT JOIN imagens_cliente_obra i ON f.imagem_id = i.idimagens_cliente_obra
        LEFT JOIN cliente cli ON i.cliente_id = cli.idcliente
        LEFT JOIN obra o ON i.obra_id = o.idobra
        WHERE f.idfuncao_imagem = $idLinhaSelecionada";

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

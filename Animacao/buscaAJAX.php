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
    $idAnimaSelecionada = $_GET['ajid'];

    // Proteção contra SQL Injection
    $idAnimaSelecionada = $conn->real_escape_string($idAnimaSelecionada);

    $sql = "SELECT a.idanimacao, col.nome_colaborador, cli.nome_cliente, o.nome_obra, a.duracao, 
		i.imagem_nome, a.status_anima, c.status as status_cena, c.prazo as prazo_cena, r.status as status_render, r.prazo as prazo_render,
		p.status as status_pos, p.prazo as prazo_pos from animacao a
        INNER JOIN colaborador col ON a.colaborador_id = col.idcolaborador
		INNER JOIN cliente cli ON a.cliente_id = cli.idcliente
        INNER JOIN obra o ON a.obra_id = o.idobra
        INNER JOIN imagem_animacao i ON a.imagem_id = i.idimagem_animacao
		INNER JOIN cena c on a.idanimacao = c.animacao_id
		INNER JOIN render r on a.idanimacao = c.animacao_id
		INNER JOIN pos p on a.idanimacao = c.animacao_id
        WHERE a.idanimacao = $idAnimaSelecionada";

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

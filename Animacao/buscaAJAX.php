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

    $sql = "SELECT 
    a.idanimacao,
    col.nome_colaborador,
    cli.nome_cliente,
    o.nome_obra,
    a.duracao,
    i.imagem_nome,
    a.status_anima,
    c.idcena,
    c.status AS status_cena,
    c.prazo AS prazo_cena,
    r.idrender,
    r.status AS status_render,
    r.prazo AS prazo_render,
    p.idpos,
    p.status AS status_pos,
    p.prazo AS prazo_pos
FROM animacao a
LEFT JOIN colaborador col ON a.colaborador_id = col.idcolaborador
LEFT JOIN cliente cli ON a.cliente_id = cli.idcliente
LEFT JOIN obra o ON a.obra_id = o.idobra
LEFT JOIN imagem_animacao i ON a.imagem_id = i.idimagem_animacao
LEFT JOIN cena c ON c.animacao_id = a.idanimacao
LEFT JOIN render r ON r.animacao_id = a.idanimacao
LEFT JOIN pos p ON p.animacao_id = a.idanimacao
WHERE a.idanimacao = $idAnimaSelecionada;";

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

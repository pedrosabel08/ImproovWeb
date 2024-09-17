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

    $sql = "SELECT p.idpos_producao, col.nome_colaborador, cli.nome_cliente, o.idobra, o.nome_obra, 
                    p.data_pos, i.imagem_nome, p.caminho_pasta, p.numero_bg, p.refs, p.obs, p.status_pos, s.nome_status 
            FROM pos_producao p
            INNER JOIN colaborador col ON p.colaborador_id = col.idcolaborador
            INNER JOIN cliente cli ON p.cliente_id = cli.idcliente
            INNER JOIN obra o ON p.obra_id = o.idobra
            INNER JOIN imagens_cliente_obra i ON p.imagem_id = i.idimagens_cliente_obra
            INNER JOIN status_imagem s ON p.status_id = s.idstatus
            WHERE p.idpos_producao = $idImagemSelecionada";
    
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
?>

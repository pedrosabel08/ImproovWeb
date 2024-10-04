<?php
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

$sql = "SELECT f.idfuncao_imagem, col.nome_colaborador, cli.nome_cliente, o.nome_obra, o.idobra,
        i.imagem_nome, f.status, f.prazo
        FROM funcao_imagem f
        LEFT JOIN colaborador col ON f.colaborador_id = col.idcolaborador
        LEFT JOIN imagens_cliente_obra i ON f.imagem_id = i.idimagens_cliente_obra
        LEFT JOIN cliente cli ON i.cliente_id = cli.idcliente
        LEFT JOIN obra o ON i.obra_id = o.idobra
        WHERE f.funcao_id = 1";

$result = $conn->query($sql);

$data = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

// Retorna os dados em formato JSON
echo json_encode($data);

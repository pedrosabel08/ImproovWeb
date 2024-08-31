<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('localhost', 'root', '', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $idImagemSelecionada = $_GET['ajid'];
}


$sql = "SELECT 
            c.nome_cliente, 
            o.nome_obra, 
            i.imagem_nome, 
            i.prazo AS prazo_estimado
        FROM imagens_cliente_obra i
        JOIN cliente c ON i.cliente_id = c.idcliente
        JOIN obra o ON i.obra_id = o.idobra
        WHERE i.idimagens_cliente_obra = $idImagemSelecionada";
$result = $conn->query($sql);

$data = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}
header('Content-Type: application/json');
echo json_encode($data);


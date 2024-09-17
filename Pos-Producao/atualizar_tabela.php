<?php
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

$sql = "SELECT p.idpos_producao, col.nome_colaborador, cli.nome_cliente, o.nome_obra, p.data_pos, 
                        i.imagem_nome, p.caminho_pasta, p.numero_bg, p.refs, p.obs, p.status_pos, s.nome_status 
                        FROM pos_producao p
                        INNER JOIN colaborador col ON p.colaborador_id = col.idcolaborador
                        INNER JOIN cliente cli ON p.cliente_id = cli.idcliente
                        INNER JOIN obra o ON p.obra_id = o.idobra
                        INNER JOIN imagens_cliente_obra i ON p.imagem_id = i.idimagens_cliente_obra
                        INNER JOIN status_imagem s ON p.status_id = s.idstatus";

$result = $conn->query($sql);

$data = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

// Retorna os dados em formato JSON
echo json_encode($data);
<?php

$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

$sql = "SELECT i.idimagens_cliente_obra, c.nome_cliente, o.nome_obra, i.recebimento_arquivos, i.data_inicio, i.prazo, MAX(i.imagem_nome) AS imagem_nome, i.prazo AS prazo_estimado, s.nome_status, i.tipo_imagem, i.antecipada FROM imagens_cliente_obra i 
        JOIN cliente c ON i.cliente_id = c.idcliente 
        JOIN obra o ON i.obra_id = o.idobra 
        LEFT JOIN funcao_imagem fi ON i.idimagens_cliente_obra = fi.imagem_id 
        LEFT JOIN funcao f ON fi.funcao_id = f.idfuncao 
        LEFT JOIN colaborador co ON fi.colaborador_id = co.idcolaborador 
        LEFT JOIN status_imagem s ON i.status_id = s.idstatus 
        GROUP BY i.idimagens_cliente_obra";

$result = $conn->query($sql);

$data = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

// Retorna os dados em formato JSON
echo json_encode($data);

<?php
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

$sql = "SELECT a.idanimacao, col.nome_colaborador, cli.nome_cliente, o.nome_obra, a.duracao, 
        i.imagem_nome, a.status_anima, c.status as status_cena, c.prazo as prazo_cena, r.status as status_render, r.prazo as prazo_render,
        p.status as status_pos, p.prazo as prazo_pos 
        FROM animacao a
        LEFT JOIN colaborador col ON a.colaborador_id = col.idcolaborador
        LEFT JOIN cliente cli ON a.cliente_id = cli.idcliente
        LEFT JOIN obra o ON a.obra_id = o.idobra
        LEFT JOIN imagem_animacao i ON a.imagem_id = i.idimagem_animacao
        LEFT JOIN cena c ON a.idanimacao = c.animacao_id
        LEFT JOIN render r ON a.idanimacao = c.animacao_id
        LEFT JOIN pos p ON a.idanimacao = c.animacao_id
        GROUP BY a.idanimacao";

$result = $conn->query($sql);

$data = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

// Retorna os dados em formato JSON
echo json_encode($data);

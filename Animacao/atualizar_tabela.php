<?php
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

$sql = "SELECT a.idanimacao, col.nome_colaborador, cli.nome_cliente, o.nome_obra, a.duracao, 
		i.imagem_nome, a.status_anima, c.status as status_cena, c.prazo as prazo_cena, r.status as status_render, r.prazo as prazo_render,
		p.status as status_pos, p.prazo as prazo_pos from animacao a
        INNER JOIN colaborador col ON a.colaborador_id = col.idcolaborador
		INNER JOIN cliente cli ON a.cliente_id = cli.idcliente
        INNER JOIN obra o ON a.obra_id = o.idobra
        INNER JOIN imagem_animcao i ON a.imagem_id = i.idimagem_animacao
		INNER JOIN cena c on a.idanimacao = c.animacao_id
		INNER JOIN render r on a.idanimacao = c.animacao_id
		INNER JOIN pos p on a.idanimacao = c.animacao_id";

$result = $conn->query($sql);

$data = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

// Retorna os dados em formato JSON
echo json_encode($data);

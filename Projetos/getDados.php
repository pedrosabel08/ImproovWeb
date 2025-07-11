<?php

include '../conexao.php';

// Query SQL com filtro pelos substatus desejados
$sql = 'SELECT 
        i.idimagens_cliente_obra,
        i.imagem_nome,
        i.prazo,
        i.status_id,
        o.idobra,
        o.nomenclatura,
        s.nome_status AS nome_status_imagem,
        ss.nome_substatus AS situacao,
        i.data_inicio
    FROM imagens_cliente_obra i
    JOIN obra o ON i.obra_id = o.idobra
    JOIN status_imagem s ON i.status_id = s.idstatus
    JOIN substatus_imagem ss ON i.substatus_id = ss.id
    WHERE ss.nome_substatus IN ("REN", "FIN", "RVW")
    ORDER BY i.prazo, i.status_id, o.idobra
';

// Executa a consulta
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

// Prepara os dados formatando datas
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id'           => $row['idimagens_cliente_obra'],
        'nome_imagem'  => $row['imagem_nome'],
        'prazo'        => !empty($row['prazo']) ? date('d/m/Y', strtotime($row['prazo'])) : '',
        'data_inicio'  => !empty($row['data_inicio']) ? date('d/m/Y', strtotime($row['data_inicio'])) : '',
        'status'       => $row['nome_status_imagem'],
        'situacao'     => $row['situacao'],
        'obra'         => $row['nomenclatura']
    ];
}

// Retorna em JSON
header('Content-Type: application/json');
echo json_encode($data);
exit;

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
        i.recebimento_arquivos,
        i.tipo_imagem
    FROM imagens_cliente_obra i
    JOIN obra o ON i.obra_id = o.idobra
    JOIN status_imagem s ON i.status_id = s.idstatus
    LEFT JOIN substatus_imagem ss ON i.substatus_id = ss.id
    WHERE o.status_obra = 0
    ORDER BY  o.nomenclatura, i.idimagens_cliente_obra, i.prazo, i.status_id;
';

// Executa a consulta
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

// Prepara os dados formatando datas
$data = [];
$indicadores = [
    'REN' => 0,
    'FIN' => 0,
    'RVW' => 0,
    'DRV' => 0,
    'atrasadas' => 0,
    'prazo_hoje' => 0,
    'total' => 0
];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id'                  => $row['idimagens_cliente_obra'],
        'nome_imagem'         => $row['imagem_nome'],
        'prazo'               => $row['prazo'],
        'recebimento_arquivos'         => $row['recebimento_arquivos'],
        'tipo_imagem'         => $row['tipo_imagem'],
        'nome_status_imagem'  => $row['nome_status_imagem'],
        'nome_status'  => $row['nome_status_imagem'],
        'situacao'            => $row['situacao'],
        'obra'                => $row['nomenclatura']
    ];

    $indicadores['total']++;

    $prazoRaw = $row['prazo'];
    $substatus = $row['situacao'];
    if (isset($indicadores[$substatus])) {
        $indicadores[$substatus]++;
    }


    // Verifica se o prazo é válido e diferente de 0000-00-00
    if (!empty($prazoRaw) && $prazoRaw !== '0000-00-00') {
        $prazoTimestamp = strtotime($prazoRaw);
        $hoje = strtotime(date('Y-m-d'));

        if ($prazoTimestamp !== false) {
            if ($prazoTimestamp < $hoje && $substatus !== 'FIN') {
                $indicadores['atrasadas']++;
            } elseif ($prazoTimestamp == $hoje) {
                $indicadores['prazo_hoje']++;
            }
        }
    }
}

// Retorna em JSON
header('Content-Type: application/json');
echo json_encode([
    'dados' => $data,
    'indicadores' => $indicadores
]);
exit;

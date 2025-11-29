<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

$entregaId = isset($_GET['entrega_id']) ? intval($_GET['entrega_id']) : 0;
if ($entregaId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parâmetro entrega_id inválido']);
    exit;
}

// Dados básicos da entrega
$infoSql = "SELECT e.id, e.obra_id, e.data_prevista, s.nome_status AS nome_etapa, o.nomenclatura
            FROM entregas e
            JOIN status_imagem s ON s.idstatus = e.status_id
            JOIN obra o ON o.idobra = e.obra_id
            WHERE e.id = ?";
$infoStmt = $conn->prepare($infoSql);
$infoStmt->bind_param('i', $entregaId);
$infoStmt->execute();
$info = $infoStmt->get_result()->fetch_assoc();
$infoStmt->close();

if (!$info) {
    echo json_encode(['success' => false, 'error' => 'Entrega não encontrada']);
    exit;
}

// Itens da entrega com última função/etapa e responsável atual
// Assumimos tabela funcao_imagem com colunas: id, imagem_id, funcao, responsavel, data_registro
// Pegamos o registro mais recente por imagem
$itemsSql = "SELECT 
    i.idimagens_cliente_obra AS imagem_id,
    i.imagem_nome AS nome_imagem,
    COALESCE(f.nome_funcao, 'Aguardando aprovação') AS etapa,
    c.nome_colaborador AS responsavel,
    i.substatus_id,
    ei.status AS status_entrega,
    lfi.status AS funcao_status,
    CASE WHEN ei.status NOT IN ('Pendente','Entrega pendente') THEN 1 ELSE 0 END AS entregue
FROM entregas_itens ei
JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = ei.imagem_id
LEFT JOIN (
    SELECT f1.imagem_id, f1.funcao_id, f1.colaborador_id, f1.prazo, f1.status
    FROM funcao_imagem f1
    JOIN (
        SELECT imagem_id, MAX(prazo) AS max_prazo
        FROM funcao_imagem
        GROUP BY imagem_id
    ) fm ON fm.imagem_id = f1.imagem_id AND fm.max_prazo = f1.prazo
) lfi ON lfi.imagem_id = i.idimagens_cliente_obra
LEFT JOIN funcao f ON f.idfuncao = lfi.funcao_id
LEFT JOIN colaborador c ON c.idcolaborador = lfi.colaborador_id
WHERE ei.entrega_id = ?
ORDER BY 
    CAST(SUBSTRING_INDEX(i.imagem_nome, '.', 1) AS UNSIGNED) ASC,
    nome_imagem ASC";

$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param('i', $entregaId);
$itemsStmt->execute();
$itemsRes = $itemsStmt->get_result();
$items = [];
while ($row = $itemsRes->fetch_assoc()) {
    $items[] = [
        'imagem_id' => intval($row['imagem_id']),
        'nome_imagem' => $row['nome_imagem'],
        'etapa' => $row['etapa'],
        'responsavel' => $row['responsavel'],
        'substatus_id' => isset($row['substatus_id']) ? intval($row['substatus_id']) : null,
        'funcao_status' => $row['funcao_status'],
        'status_entrega' => $row['status_entrega'],
        'entregue' => ($row['entregue'] == 1),
    ];
}
$itemsStmt->close();

$out = [
    'success' => true,
    'entrega' => [
        'id' => intval($info['id']),
        'obra_id' => intval($info['obra_id']),
        'data_prevista' => $info['data_prevista'],
        'nome_etapa' => $info['nome_etapa'],
        'nomenclatura' => $info['nomenclatura'],
    ],
    'itens' => $items,
];

echo json_encode($out);

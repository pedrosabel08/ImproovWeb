<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

$obra_id = isset($_GET['obra_id']) && is_numeric($_GET['obra_id']) ? intval($_GET['obra_id']) : null;

$where = '';
if ($obra_id !== null) {
    $where = "WHERE e.obra_id = " . $obra_id;
}

$sql = "SELECT 
    e.id,
    e.obra_id,
    e.data_prevista,
    e.data_conclusao,
    e.status,
    e.observacoes,
    s.nome_status as nome_etapa,
    o.nomenclatura,
    COUNT(ei.id) AS total_itens,
    SUM(CASE WHEN ei.status NOT IN ('Pendente', 'Entrega pendente') THEN 1 ELSE 0 END) AS entregues_count,
    (SUM(CASE WHEN ei.status NOT IN ('Pendente', 'Entrega pendente')  THEN 1 ELSE 0 END) / GREATEST(COUNT(ei.id),1)) * 100 AS pct_entregue,
    -- Conta imagens que estão finalizadas (substatus RVW/DRV) ou marcadas como 'Entrega pendente',
    -- mas exclui itens que já têm status de entrega (p.ex. 'Entregue no prazo', 'Entregue com atraso', 'Entrega antecipada')
    SUM(CASE WHEN (ei.status = 'Entrega pendente' OR ss.nome_substatus IN ('RVW','DRV'))
                 AND ei.status NOT IN ('Entregue no prazo', 'Entregue com atraso', 'Entrega antecipada')
                 THEN 1 ELSE 0 END) AS ready_count
FROM entregas e
LEFT JOIN entregas_itens ei ON ei.entrega_id = e.id
LEFT JOIN imagens_cliente_obra i ON ei.imagem_id = i.idimagens_cliente_obra
LEFT JOIN substatus_imagem ss ON ss.id = i.substatus_id
JOIN obra o ON e.obra_id = o.idobra
JOIN status_imagem s ON e.status_id = s.idstatus
" . PHP_EOL . $where . PHP_EOL . "GROUP BY e.id
HAVING total_itens > 0
ORDER BY ready_count DESC, e.data_conclusao DESC";

$res = $conn->query($sql);
$out = [];

$hoje = date('Y-m-d'); // data atual no formato YYYY-MM-DD

while ($row = $res->fetch_assoc()) {
    $statusCol = 'pendente';
    $total = intval($row['total_itens']);
    $entregues = intval($row['entregues_count']);
    $dataPrevista = $row['data_prevista'];

    // Definir status inicial (pendente / parcial / concluída)
    if ($total === 0) {
        $statusCol = 'pendente';
    } elseif ($entregues === 0) {
        $statusCol = 'pendente';
    } elseif ($entregues < $total) {
        $statusCol = 'parcial';
    } else {
        $statusCol = 'concluida';
    }

    // 🚨 NOVA REGRA: se o prazo já passou e ainda não estiver concluída → "atrasado"
    if ($dataPrevista < $hoje && ($statusCol === 'pendente' || $statusCol === 'parcial')) {
        $statusCol = 'atrasada';
    }

    $out[] = [
        'id' => intval($row['id']),
        'obra_id' => $row['obra_id'],
        'data_prevista' => $row['data_prevista'],
        'status' => $row['status'],
        'observacoes' => $row['observacoes'],
        'nome_etapa' => $row['nome_etapa'],
        'nomenclatura' => $row['nomenclatura'],
        'total_itens' => $total,
        'entregues' => $entregues,
        'pct_entregue' => round(floatval($row['pct_entregue']), 1),
        'ready_count' => intval($row['ready_count'] ?? 0),
        'kanban_status' => $statusCol
    ];
}

echo json_encode($out);

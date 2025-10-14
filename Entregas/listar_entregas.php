<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

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
  SUM(CASE WHEN ei.status <> 'Pendente' THEN 1 ELSE 0 END) AS entregues_count,
  (SUM(CASE WHEN ei.status <> 'Pendente' THEN 1 ELSE 0 END) / GREATEST(COUNT(ei.id),1)) * 100 AS pct_entregue
FROM entregas e
LEFT JOIN entregas_itens ei ON ei.entrega_id = e.id
JOIN obra o ON e.obra_id = o.idobra
JOIN status_imagem s ON e.status_id = s.idstatus
GROUP BY e.id
ORDER BY e.data_prevista DESC";

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
        'kanban_status' => $statusCol
    ];
}

echo json_encode($out);

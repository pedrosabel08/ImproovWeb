<?php
require_once __DIR__ . '/../conexao.php';

header('Content-Type: application/json; charset=utf-8');

$obraId = isset($_GET['obra_id']) ? (int) $_GET['obra_id'] : 0;
if ($obraId <= 0) {
    echo json_encode(array(
        'error' => 'obra_id inválido'
    ));
    exit;
}

// METRICAS por substatus_id em imagens_cliente_obra
$sqlMetricas = "SELECT substatus_id, COUNT(*) AS qtde
                FROM imagens_cliente_obra
                WHERE obra_id = ?
                GROUP BY substatus_id";

$stmtMetricas = $conn->prepare($sqlMetricas);
if (!$stmtMetricas) {
    echo json_encode(array('error' => 'Erro ao preparar métricas', 'details' => $conn->error));
    exit;
}

$stmtMetricas->bind_param('i', $obraId);
$stmtMetricas->execute();
$resultMetricas = $stmtMetricas->get_result();

$metricasRaw = array();
while ($row = $resultMetricas->fetch_assoc()) {
    $metricasRaw[] = $row;
}

$stmtMetricas->close();

    $total = 0;
    $hold = 0;
    $producao = 0;
    $liberadas = 0;

foreach ($metricasRaw as $row) {
    $sub = (int) $row['substatus_id'];
    $q = (int) $row['qtde'];
    $total += $q;
    // Ajuste esses mapeamentos conforme sua regra de negócio real:
    if (in_array($sub, array(7))) { // exemplo: HOLD
        $hold += $q;
    } elseif (in_array($sub, array(1, 2, 3, 4, 5))) { // exemplo: em produção
        $producao += $q;
    } elseif (in_array($sub, array(6, 9))) { // exemplo: liberadas
        $liberadas += $q;
    }
}

$metricas = array(
    'total' => $total,
    'hold' => $hold,
    'producao' => $producao,
    'liberadas' => $liberadas,
);

// TIMELINE por status a partir de entregas, entregas_itens e status_imagem
// aqui já agrupamos direto por status_id para montar algo como: "P00 - 6 imagens"
$sqlTimeline = "SELECT
        e.status_id AS status_id,
        s.nome_status AS status_nome,
        e.obra_id,
        COUNT(ei.id) AS total_imagens,
        MAX(ei.updated_at) AS ultima_data
    FROM entregas e
    JOIN entregas_itens ei ON ei.entrega_id = e.id
    LEFT JOIN status_imagem s ON s.idstatus = e.status_id
    WHERE e.obra_id = ?
    GROUP BY e.status_id, s.nome_status, e.obra_id
";

$stmtTimeline = $conn->prepare($sqlTimeline);
if (!$stmtTimeline) {
    echo json_encode(array('error' => 'Erro ao preparar timeline', 'details' => $conn->error));
    exit;
}

$stmtTimeline->bind_param('i', $obraId);
$stmtTimeline->execute();
$resultTimeline = $stmtTimeline->get_result();

$rows = array();
while ($r = $resultTimeline->fetch_assoc()) {
    $rows[] = $r;
}

$stmtTimeline->close();

// agrupar por status (status_id + nome_status)
$grupos = array();
foreach ($rows as $r) {
    $statusId = $r['status_id'];
    $statusNome = $r['status_nome'] ? $r['status_nome'] : 'SEM_STATUS';

    if (!isset($grupos[$statusId])) {
        $grupos[$statusId] = array(
            'status_id' => $statusId,
            'status' => $statusNome,
            'status_label' => $statusNome,
            'ultima_info' => '',
            'total_imagens' => 0,
            'imagens' => array(),
        );
    }

    $grupos[$statusId]['total_imagens'] += (int) $r['total_imagens'];
    if (!empty($r['ultima_data'])) {
        $grupos[$statusId]['ultima_info'] = $r['ultima_data'];
    }
}

// para cada status, buscar lista de imagens / itens
$sqlItens = "SELECT
        e.status_id AS status_id,
        s.nome_status AS status_nome,
        ei.id,
        i.imagem_nome,
        ei.updated_at as data_entregue,
        ei.created_at as data_criado
    FROM entregas e
    JOIN entregas_itens ei ON ei.entrega_id = e.id
    JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = ei.imagem_id
    LEFT JOIN status_imagem s ON s.idstatus = e.status_id
    WHERE e.obra_id = ?
    ORDER BY e.status_id, COALESCE(ei.data_entregue, ei.created_at)
";

$stmtItens = $conn->prepare($sqlItens);
if (!$stmtItens) {
    echo json_encode(array('error' => 'Erro ao preparar itens', 'details' => $conn->error));
    exit;
}

$stmtItens->bind_param('i', $obraId);
$stmtItens->execute();
$resultItens = $stmtItens->get_result();

while ($i = $resultItens->fetch_assoc()) {
    $statusId = $i['status_id'];
    $statusNome = $i['status_nome'] ? $i['status_nome'] : 'SEM_STATUS';

    if (!isset($grupos[$statusId])) {
        $grupos[$statusId] = array(
            'status_id' => $statusId,
            'status' => $statusNome,
            'status_label' => $statusNome,
            'ultima_info' => '',
            'total_imagens' => 0,
            'imagens' => array(),
        );
    }

    $grupos[$statusId]['imagens'][] = array(
        'id' => (int) $i['id'],
        'nome' => $i['imagem_nome'],
        'data_entregue' => $i['data_entregue'],
        'data_criado' => $i['data_criado'],
    );
}

$stmtItens->close();

// transformar em array indexado para o JSON
$timeline = array_values($grupos);

echo json_encode(array(
    'metricas' => $metricas,
    'timeline' => $timeline,
));

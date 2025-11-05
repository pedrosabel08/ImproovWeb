<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';

// Helpers
function get_last_month_ref() {
    $dt = new DateTime('first day of last month');
    return [$dt->format('Y'), $dt->format('n'), $dt->format('Y-m')];
}

$ano = isset($_GET['ano']) ? intval($_GET['ano']) : null;
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : null; // 1-12
if (!$ano || !$mes) {
    [$ano, $mes, $mes_ref] = get_last_month_ref();
} else {
    $mes_ref = sprintf('%04d-%02d', $ano, $mes);
}

// Carregar colaboradores
$cols = [];
$res = $conn->query("SELECT idcolaborador, nome_colaborador FROM colaborador ORDER BY nome_colaborador");
while ($row = $res->fetch_assoc()) {
    $cols[(int)$row['idcolaborador']] = [
        'colaborador_id' => (int)$row['idcolaborador'],
        'nome' => $row['nome_colaborador'],
        'mes_ref' => $mes_ref,
        'valor_pendente' => 0.0,
        'valor_mes' => 0.0,
        'valor_fixo' => 0.0,
        'status' => null,
        'ultima_atualizacao' => null,
        'pagamento_id' => null,
    ];
}

if (empty($cols)) {
    echo json_encode(['items' => [], 'mes_ref' => $mes_ref]);
    exit;
}

$ids = implode(',', array_map('intval', array_keys($cols)));

// Aggregate funcao_imagem
$sqlFI = "SELECT colaborador_id,
  SUM(CASE WHEN pagamento = 0 THEN IFNULL(valor,0) ELSE 0 END) AS valor_pendente,
  SUM(IFNULL(valor,0)) AS valor_mes,
  MAX(GREATEST(IFNULL(data_pagamento, '0000-00-00'), IFNULL(prazo, '0000-00-00'))) AS last_update
FROM funcao_imagem
WHERE colaborador_id IN ($ids) AND colaborador_id NOT IN (9, 21) AND YEAR(prazo) = $ano AND MONTH(prazo) = $mes
GROUP BY colaborador_id";

if ($r = $conn->query($sqlFI)) {
    while ($row = $r->fetch_assoc()) {
        $id = (int)$row['colaborador_id'];
        if (!isset($cols[$id])) continue;
        $cols[$id]['valor_pendente'] += (float)$row['valor_pendente'];
        $cols[$id]['valor_mes'] += (float)$row['valor_mes'];
        $cols[$id]['ultima_atualizacao'] = max($cols[$id]['ultima_atualizacao'] ?? '0000-00-00', $row['last_update'] ?? '0000-00-00');
    }
}

// Aggregate acompanhamento (uses column `data` for month/year)
$sqlAC = "SELECT colaborador_id,
  SUM(CASE WHEN pagamento = 0 THEN IFNULL(valor,0) ELSE 0 END) AS valor_pendente,
  SUM(IFNULL(valor,0)) AS valor_mes,
  MAX(GREATEST(IFNULL(data_pagamento, '0000-00-00'), IFNULL(data, '0000-00-00'))) AS last_update
FROM acompanhamento
WHERE colaborador_id IN ($ids) AND YEAR(data) = $ano AND MONTH(data) = $mes
GROUP BY colaborador_id";

if ($r = $conn->query($sqlAC)) {
    while ($row = $r->fetch_assoc()) {
        $id = (int)$row['colaborador_id'];
        if (!isset($cols[$id])) continue;
        $cols[$id]['valor_pendente'] += (float)$row['valor_pendente'];
        $cols[$id]['valor_mes'] += (float)$row['valor_mes'];
        $cols[$id]['ultima_atualizacao'] = max($cols[$id]['ultima_atualizacao'] ?? '0000-00-00', $row['last_update'] ?? '0000-00-00');
    }
}

// Aggregate animacao (uses column `data_anima`)
$sqlAN = "SELECT colaborador_id,
  SUM(CASE WHEN pagamento = 0 THEN IFNULL(valor,0) ELSE 0 END) AS valor_pendente,
  SUM(IFNULL(valor,0)) AS valor_mes,
  MAX(GREATEST(IFNULL(data_pagamento, '0000-00-00'), IFNULL(data_anima, '0000-00-00'))) AS last_update
FROM animacao
WHERE colaborador_id IN ($ids) AND YEAR(data_anima) = $ano AND MONTH(data_anima) = $mes
GROUP BY colaborador_id";

if ($r = $conn->query($sqlAN)) {
    while ($row = $r->fetch_assoc()) {
        $id = (int)$row['colaborador_id'];
        if (!isset($cols[$id])) continue;
        $cols[$id]['valor_pendente'] += (float)$row['valor_pendente'];
        $cols[$id]['valor_mes'] += (float)$row['valor_mes'];
        $cols[$id]['ultima_atualizacao'] = max($cols[$id]['ultima_atualizacao'] ?? '0000-00-00', $row['last_update'] ?? '0000-00-00');
    }
}

// Attach pagamentos table info
$stmt = $conn->prepare("SELECT idpagamento, colaborador_id, status, valor_total, atualizado_em FROM pagamentos WHERE mes_ref = ? AND colaborador_id IN ($ids)");
$stmt->bind_param('s', $mes_ref);
if ($stmt->execute()) {
    $rs = $stmt->get_result();
    while ($row = $rs->fetch_assoc()) {
        $id = (int)$row['colaborador_id'];
        if (!isset($cols[$id])) continue;
        $cols[$id]['status'] = $row['status'];
        $cols[$id]['pagamento_id'] = (int)$row['idpagamento'];
        // Prefer DB updated time
        $cols[$id]['ultima_atualizacao'] = $row['atualizado_em'] ?? $cols[$id]['ultima_atualizacao'];
        // If valor_total is set, use as display total; else keep computed
        if (!is_null($row['valor_total'])) {
            $cols[$id]['valor_mes'] = (float)$row['valor_total'];
        }
    }
}
$stmt->close();

// OPTIONAL: if colaborador table has a 'valor_fixo' column, fetch it and add to the collaborator totals
$check = $conn->query("SHOW COLUMNS FROM colaborador LIKE 'valor_fixo'");
if ($check && $check->num_rows > 0) {
    $sqlFixo = "SELECT idcolaborador, valor_fixo FROM colaborador WHERE idcolaborador IN ($ids)";
    if ($r = $conn->query($sqlFixo)) {
        while ($row = $r->fetch_assoc()) {
            $id = (int)$row['idcolaborador'];
            if (!isset($cols[$id])) continue;
            $cols[$id]['valor_fixo'] = (float)$row['valor_fixo'];
            // include fixed value in the monthly total so UI shows combined amount
            $cols[$id]['valor_mes'] += (float)$row['valor_fixo'];
            // if there is no pagamento record, consider it pending as well
            $cols[$id]['valor_pendente'] += (float)$row['valor_fixo'];
        }
    }
}

// Build items array, compute display status
$items = [];
foreach ($cols as $c) {
    // Only include rows that have any activity (valor or a pagamento record)
    $hasValor = ($c['valor_mes'] > 0) || ($c['valor_pendente'] > 0) || !is_null($c['pagamento_id']);
    if (!$hasValor) continue;

    $status = $c['status'];
    if (!$status) {
        $status = $c['valor_pendente'] > 0 ? 'PENDENTE' : 'PAGO';
    }

    $items[] = [
        'colaborador_id' => $c['colaborador_id'],
        'nome' => $c['nome'],
        'mes_ref' => $c['mes_ref'],
        'valor' => (float)$c['valor_pendente'],
        'valor_fixo' => (float)$c['valor_fixo'],
        'valor_mes' => (float)$c['valor_mes'],
        'status' => $status,
        'ultima_atualizacao' => $c['ultima_atualizacao'],
        'pagamento_id' => $c['pagamento_id']
    ];
}

echo json_encode(['items' => $items, 'mes_ref' => $mes_ref]);

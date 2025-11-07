<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

$colaborador_id = 21; // default requested
$ano = intval(date('Y'));
$mes = intval(date('n'));

// Allow overriding via GET (web) or CLI args (--colaborador_id=, --ano=, --mes=)
if (PHP_SAPI !== 'cli') {
    if (isset($_GET['colaborador_id'])) $colaborador_id = intval($_GET['colaborador_id']);
    if (isset($_GET['ano'])) $ano = intval($_GET['ano']);
    if (isset($_GET['mes'])) $mes = intval($_GET['mes']);
} else {
    // parse CLI args
    global $argv;
    foreach ($argv as $a) {
        if (strpos($a, '--colaborador_id=') === 0) $colaborador_id = intval(substr($a, strlen('--colaborador_id=')));
        if (strpos($a, '--col=') === 0) $colaborador_id = intval(substr($a, strlen('--col=')));
        if (strpos($a, '--ano=') === 0) $ano = intval(substr($a, strlen('--ano=')));
        if (strpos($a, '--mes=') === 0) $mes = intval(substr($a, strlen('--mes=')));
    }
}
$mes_ref = sprintf('%04d-%02d', $ano, $mes);

$out = [
    'meta' => [
        'colaborador_id' => $colaborador_id,
        'ano' => $ano,
        'mes' => $mes,
        'mes_ref' => $mes_ref,
        'timestamp' => date('c')
    ],
    'pagamento' => null,
    'items' => [ 'funcao_imagem' => [], 'acompanhamento' => [], 'animacao' => [] ],
    'valor_total' => 0.0,
    'actions' => []
];

try {
    // check if pagamentos exists
    $stmt = $conn->prepare("SELECT idpagamento, status, valor_total FROM pagamentos WHERE colaborador_id = ? AND mes_ref = ?");
    $stmt->bind_param('is', $colaborador_id, $mes_ref);
    $stmt->execute();
    $r = $stmt->get_result();
    $pag = $r->fetch_assoc();
    $stmt->close();

    if ($pag) {
        $out['pagamento'] = ['exists' => true, 'idpagamento' => (int)$pag['idpagamento'], 'status' => $pag['status'], 'valor_total_current' => (float)$pag['valor_total']];
        $pagamento_ref = (int)$pag['idpagamento'];
    } else {
        $out['pagamento'] = ['exists' => false, 'would_create' => true, 'create_values' => ['colaborador_id' => $colaborador_id, 'mes_ref' => $mes_ref, 'status' => 'pendente_envio'] ];
        $pagamento_ref = '<<NEW>>';
    }

    // funcao_imagem
    $q = $conn->prepare("SELECT fi.idfuncao_imagem, IFNULL(fi.valor,0) AS valor, i.status_id, fi.prazo, fi.funcao_id, fi.imagem_id FROM funcao_imagem fi LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra WHERE fi.colaborador_id = ? AND fi.pagamento = 0 AND YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?");
    $q->bind_param('iii', $colaborador_id, $ano, $mes);
    $q->execute();
    $rs = $q->get_result();
    while ($row = $rs->fetch_assoc()) {
        $item = [
            'idfuncao_imagem' => (int)$row['idfuncao_imagem'],
            'valor' => (float)$row['valor'],
            'status_id' => isset($row['status_id']) ? (int)$row['status_id'] : null,
            'prazo' => $row['prazo'],
            'funcao_id' => isset($row['funcao_id']) ? (int)$row['funcao_id'] : null,
            'imagem_id' => isset($row['imagem_id']) ? (int)$row['imagem_id'] : null
        ];
        $out['items']['funcao_imagem'][] = $item;
        $out['valor_total'] += $item['valor'];
    }
    $q->close();

    // acompanhamento
    $q = $conn->prepare("SELECT idacompanhamento, IFNULL(valor,0) AS valor, data FROM acompanhamento WHERE colaborador_id = ? AND pagamento = 0 AND YEAR(data) = ? AND MONTH(data) = ?");
    $q->bind_param('iii', $colaborador_id, $ano, $mes);
    $q->execute();
    $rs = $q->get_result();
    while ($row = $rs->fetch_assoc()) {
        $item = ['idacompanhamento' => (int)$row['idacompanhamento'], 'valor' => (float)$row['valor'], 'data' => $row['data']];
        $out['items']['acompanhamento'][] = $item;
        $out['valor_total'] += $item['valor'];
    }
    $q->close();

    // animacao
    $q = $conn->prepare("SELECT idanimacao, IFNULL(valor,0) AS valor, data_anima FROM animacao WHERE colaborador_id = ? AND pagamento = 0 AND YEAR(data_anima) = ? AND MONTH(data_anima) = ?");
    $q->bind_param('iii', $colaborador_id, $ano, $mes);
    $q->execute();
    $rs = $q->get_result();
    while ($row = $rs->fetch_assoc()) {
        $item = ['idanimacao' => (int)$row['idanimacao'], 'valor' => (float)$row['valor'], 'data_anima' => $row['data_anima']];
        $out['items']['animacao'][] = $item;
        $out['valor_total'] += $item['valor'];
    }
    $q->close();

    // Build intended actions (no writes)
    // Mark origins as paid
    if (!empty($out['items']['funcao_imagem'])) {
        $ids = array_map(function($i){return $i['idfuncao_imagem'];}, $out['items']['funcao_imagem']);
        $out['actions'][] = ['action' => 'UPDATE funcao_imagem', 'set' => ['pagamento' => 1, 'data_pagamento' => 'NOW()'], 'where' => ['idfuncao_imagem IN' => $ids]];
    }
    if (!empty($out['items']['acompanhamento'])) {
        $ids = array_map(function($i){return $i['idacompanhamento'];}, $out['items']['acompanhamento']);
        $out['actions'][] = ['action' => 'UPDATE acompanhamento', 'set' => ['pagamento' => 1, 'data_pagamento' => 'NOW()'], 'where' => ['idacompanhamento IN' => $ids]];
    }
    if (!empty($out['items']['animacao'])) {
        $ids = array_map(function($i){return $i['idanimacao'];}, $out['items']['animacao']);
        $out['actions'][] = ['action' => 'UPDATE animacao', 'set' => ['pagamento' => 1, 'data_pagamento' => 'NOW()'], 'where' => ['idanimacao IN' => $ids]];
    }

    // Inserts into pagamento_itens
    $toInsert = [];
    // prepare a small check for Pré-Finalização existence per imagem
    $chk = $conn->prepare("SELECT COUNT(1) AS cnt FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao WHERE fi_sub.imagem_id = ? AND f_sub.nome_funcao = 'Pré-Finalização'");
    foreach ($out['items']['funcao_imagem'] as $it) {
        $hasPrefinal = false;
        if ($it['imagem_id']) {
            $chk->bind_param('i', $it['imagem_id']);
            $chk->execute();
            $r = $chk->get_result();
            $c = $r->fetch_assoc();
            $hasPrefinal = isset($c['cnt']) && intval($c['cnt']) > 0;
            $r->free();
        }
        $isFinalizacaoFunc = (isset($it['funcao_id']) && intval($it['funcao_id']) === 4);
        $obs = ($isFinalizacaoFunc && ( (isset($it['status_id']) && intval($it['status_id']) === 1) || $hasPrefinal )) ? 'Finalização Parcial' : null;
        $toInsert[] = ['pagamento_id' => $pagamento_ref, 'origem' => 'funcao_imagem', 'origem_id' => $it['idfuncao_imagem'], 'valor' => $it['valor'], 'observacao' => $obs];
    }
    if (isset($chk) && $chk) $chk->close();
    foreach ($out['items']['acompanhamento'] as $it) {
        $toInsert[] = ['pagamento_id' => $pagamento_ref, 'origem' => 'acompanhamento', 'origem_id' => $it['idacompanhamento'], 'valor' => $it['valor'], 'observacao' => null];
    }
    foreach ($out['items']['animacao'] as $it) {
        $toInsert[] = ['pagamento_id' => $pagamento_ref, 'origem' => 'animacao', 'origem_id' => $it['idanimacao'], 'valor' => $it['valor'], 'observacao' => null];
    }

    $out['actions'][] = ['action' => 'INSERT pagamento_itens (simulated)', 'rows' => $toInsert];

    // Update pagamentos aggregate
    $out['actions'][] = ['action' => 'UPDATE pagamentos (simulated)', 'set' => ['status' => 'pago', 'valor_total' => $out['valor_total'], 'data_pagamento' => 'NOW()', 'pago_em' => 'NOW()'], 'where' => ['idpagamento' => $pagamento_ref]];

    // Event
    $out['actions'][] = ['action' => 'INSERT pagamento_eventos (simulated)', 'row' => ['pagamento_id' => $pagamento_ref, 'tipo' => 'pago', 'descricao' => 'Pagamento marcado como PAGO e itens confirmados ('.(count($out['items']['funcao_imagem'])+count($out['items']['acompanhamento'])+count($out['items']['animacao'])).' itens)']];

    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

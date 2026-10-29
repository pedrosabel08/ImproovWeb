<?php

/** Diagnóstico de origens e ledger: exclusivamente CLI e leitura. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__.'/../config/pagamento_fechamento.php';
require __DIR__.'/../Pagamento/services/FechamentoFinanceiroRepository.php';
$ref = $argv[1] ?? '2026-10';
FechamentoFinanceiroRules::periodo($ref);
$conn = pagamento_fechamento_connection();
echo json_encode(['schema' => $conn->query("SELECT TABLE_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('pagamento_fechamento_decisao','pagamento_fechamento_operacao') AND COLUMN_NAME='tipo'")->fetch_all(MYSQLI_ASSOC)], JSON_UNESCAPED_UNICODE).PHP_EOL;
$colabs = $conn->query("SELECT idcolaborador,nome_colaborador,tipo_remuneracao FROM colaborador WHERE nome_colaborador IN ('Pedro Henrique','Heverton')")->fetch_all(MYSQLI_ASSOC);
foreach ($colabs as $c) {
    $b = (int)$c['idcolaborador'];
    $read = (new FechamentoFinanceiroRepository($conn, true))->carregar($b, $ref);
    $r = (new FechamentoFinanceiroRules())->calcular($read['dados'], $b, $ref, $read['snapshot'], true);
    echo json_encode(['colaborador' => $c,'itens' => count($r['itens_analisados']),'pendencias' => count($r['pendencias'])], JSON_UNESCAPED_UNICODE).PHP_EOL;
    foreach ($r['itens_analisados'] as $s) {
        if (!$s['divergencias']) {
            continue;
        }
        $id = $s['identidade'];
        $orig = array_values(array_filter($read['dados']['origens'], fn ($o) => $o['origem'] === $id['origem'] && (int)$o['origem_id'] === $id['origem_id']))[0];
        $q = $conn->prepare('SELECT pi.idpagamento_item,pi.origem,pi.origem_id,pi.valor,pi.observacao,p.colaborador_id,p.mes_ref,p.status FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id WHERE pi.origem=? AND pi.origem_id=?');
        $q->bind_param('si', $id['origem'], $id['origem_id']);
        $q->execute();
        echo json_encode(['identidade' => $id,'descricao' => $s['descricao'],'base' => $s['base_centavos'],'flag' => $s['flag_legada_pagamento'],'parcial' => $orig['finalizacao_parcial'] ?? null,'codigo' => array_column($s['divergencias'], 'codigo'),'todos_lancamentos' => $q->get_result()->fetch_all(MYSQLI_ASSOC)], JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
}
$conn->close();

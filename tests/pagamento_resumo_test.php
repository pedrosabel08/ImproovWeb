<?php
// Offline financial scenarios. No connection or writes to the application database.
require_once __DIR__ . '/../Pagamento/resumo_geral.php';
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$checks = 0;
function payment_check($actual, $expected, string $label): void
{
    global $checks;
    $checks++;
    if ($actual !== $expected) throw new RuntimeException($label . ': ' . json_encode($actual) . ' != ' . json_encode($expected));
}
$GLOBALS['_custo_tarefa_contexto'] = ['funcao_map' => [23 => [4 => 300.0, 3 => 100.0], 40 => [4 => 300.0]]];
$task = ['colaborador_id' => 23, 'origem' => 'funcao_imagem', 'origem_id' => 101, 'funcao_id' => 4, 'nome_funcao' => 'Finalização', 'obra_id' => 1, 'valor' => '300.00', 'pagamento' => 0, 'parcial' => 0, 'imagem_nome' => 'Fachada', 'tipo_imagem' => 'Fachada', 'valor_aprovado' => 0];
$entry = ['colaborador_id' => 23, 'origem' => 'funcao_imagem', 'origem_id' => 101, 'valor' => '150.00', 'observacao' => 'Finalização Parcial', 'mes_ref' => '2026-08'];
$items = pagamento_projetar_itens([$task], []);
$s = pagamento_agregar_itens($items);
payment_check($s['total'], 30000, 'Snapshot cost, not document total');
payment_check($s['pendente'], 30000, 'Unpaid snapshot');
payment_check($s['divergencias'], 0, 'Matching tariff');
$s = pagamento_agregar_itens(pagamento_projetar_itens([$task], [$entry]));
payment_check([$s['total'], $s['pago'], $s['pendente']], [30000, 15000, 15000], 'Installment from another settlement month');
payment_check([$s['itens_pagos'], $s['itens_pendentes']], [0, 1], 'Partially settled stays pending');
payment_check($s['percentual_pago'], 50.0, 'Financial percentage');
$complement = $entry; $complement['observacao'] = 'Pago Completa';
$s = pagamento_agregar_itens(pagamento_projetar_itens([$task], [$entry, $complement]));
payment_check([$s['pago'], $s['pendente'], $s['divergencias']], [30000, 0, 0], 'Valid pair of installments');
payment_check($s['itens_pagos'], 1, 'One item paid with two entries');
$commission = $task; $commission['colaborador_id'] = 8; $commission['comissao_gestor'] = true;
$commissionEntry = $entry; $commissionEntry['colaborador_id'] = 8; $commissionEntry['valor'] = '100'; $commissionEntry['observacao'] = 'Comissão Gestor';
$items = pagamento_projetar_itens([$task, $commission], [$entry, $commissionEntry]);
$s = pagamento_agregar_itens($items);
payment_check([$s['total'], $s['pago'], $s['pendente']], [40000, 25000, 15000], 'Owner payment and commission kept separate');
payment_check(financeiro_snapshot($commission + ['unused' => true]), 10000, 'Existing facade commission');
$commission['imagem_nome'] = 'Fachada embasamento';
payment_check(financeiro_snapshot($commission), 8000, 'Existing commission exception');
$excess = $entry; $excess['valor'] = '350.00';
$s = pagamento_agregar_itens(pagamento_projetar_itens([$task], [$excess]));
payment_check([$s['total'], $s['pago'], $s['excesso']], [30000, 35000, 5000], 'Overpayment stays visible');
payment_check([$s['consistente'], $s['grafico_financeiro_disponivel']], [false, false], 'Do not force distribution for overpayment');
payment_check($s['divergencias_financeiras'], 1, 'Financial discrepancy');
$s = pagamento_agregar_itens(pagamento_projetar_itens([$task], [$entry, $entry]));
payment_check($s['divergencias_financeiras'], 1, 'Duplicate installment');
$approved = $task; $approved['valor'] = '280';
$s = pagamento_agregar_itens(pagamento_projetar_itens([$approved], []));
payment_check($s['divergencias_tarifa'], 1, 'Existing tariff discrepancy');
payment_check($s['total'], 28000, 'Tariff does not replace saved amount');
$approved['valor_aprovado'] = 1;
payment_check(pagamento_agregar_itens(pagamento_projetar_itens([$approved], []))['divergencias'], 0, 'Approved amount exemption');
$marked = $task; $marked['pagamento'] = 1;
payment_check(pagamento_agregar_itens(pagamento_projetar_itens([$marked], []))['divergencias_financeiras'], 1, 'Paid flag without ledger');
$partial = $task; $partial['parcial'] = 1;
payment_check(pagamento_projetar_itens([$partial], []), [], 'Unpaid partial finalization remains excluded');
$partialSummary = pagamento_agregar_itens(pagamento_projetar_itens([$partial], [$entry]));
payment_check([$partialSummary['total'], $partialSummary['pago'], $partialSummary['pendente']], [30000, 15000, 15000], 'Paid partial finalization remains visible in general summary');
$zero = $task; $zero['valor'] = '0'; $zero['valor_aprovado'] = 1;
$s = pagamento_agregar_itens(pagamento_projetar_itens([$zero], []));
payment_check([$s['itens_pendentes'], $s['percentual_pago']], [1, null], 'Zero amount is not a paid item');
$negative = $task; $negative['valor'] = '-5';
$s = pagamento_agregar_itens(pagamento_projetar_itens([$negative], []));
payment_check([$s['total'], $s['divergencias_financeiras'], $s['grafico_financeiro_disponivel']], [-500, 1, false], 'Negative saved value is preserved and flagged');
$s = pagamento_agregar_itens([]);
payment_check([$s['total'], $s['itens'], $s['percentual_pago']], [0, 0, null], 'Empty competence');
$animation = $task; $animation['origem'] = 'funcao_animacao'; $animation['nome_funcao'] = 'Animação'; $animation['valor'] = '120';
payment_check(pagamento_agregar_itens(pagamento_projetar_itens([$animation], []))['divergencias_tarifa'], 0, 'Animation uses saved value without image tariff');
echo "$checks financial scenario checks passed.\n";

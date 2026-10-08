<?php
/** Completa somente a fixture isolada utilizada no navegador. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/fixtures/pagamento_adendos_documental.php';
require_once __DIR__.'/../Pagamento/services/FechamentoCompetenciaService.php';
$f = json_decode(file_get_contents(__DIR__.'/../output/competencia-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^pagamento_1cb_test_[a-f0-9]{10}$/D', $f['db'] ?? '')) throw new RuntimeException('Fixture inválida.');
$c = documental_test_connection($f['db']);
$s = new FechamentoCompetenciaService($c, 1);
$r = $s->resumo('2026-09');
if ($r['estado'] !== 'CONCLUIDO') throw new RuntimeException('Conclua o fechamento sintético no navegador primeiro.');
foreach ($r['colaboradores'] as $p) {
    if ($p['pagamento_status'] !== 'PAGO') $s->pagar('2026-09', $p['colaborador_id'], 'browser-quitacao-'.$p['colaborador_id'], '2026-10-07', 'Quitação exclusivamente sintética');
}
$r = $s->resumo('2026-09');
if ($r['situacao'] !== 'QUITADO' || $r['pendente_centavos'] !== 0) throw new RuntimeException('Quitação incompleta.');
$checklists = $c->query("SELECT entity_type,status FROM checklist_operacional WHERE module_key='pagamentos' AND entity_id=".(int)$r['ciclo_id'])->fetch_all(MYSQLI_ASSOC);
foreach ($checklists as $ch) if ($ch['status'] !== 'concluido') throw new RuntimeException('Pendência financeira ainda aberta.');
echo json_encode(['db'=>$f['db'],'situacao'=>$r['situacao'],'total_centavos'=>$r['total_fechado_centavos'],'pago_centavos'=>$r['pago_centavos'],'pagos'=>$r['quantidade_pagos'],'aptos'=>$r['quantidade'],'pendencias'=>$checklists], JSON_UNESCAPED_UNICODE).PHP_EOL;

<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__.'/fixtures/fechamento_mensal_v1.php';
require_once __DIR__.'/../Pagamento/services/FechamentoInterfaceService.php';
$fixture = mensal_test_seed();
$c = documental_test_connection($fixture['db']);
file_put_contents(__DIR__.'/../output/mensal-fixture.json', json_encode($fixture, JSON_UNESCAPED_SLASHES));
$service = new FechamentoInterfaceService($c, $fixture['root'], 1, true);
$checks = 0;
function check(bool $ok, string $label): void
{
    global $checks;
    if (!$ok) {
        throw new RuntimeException($label);
    }$checks++;
}
$m = $service->mensal('2026-09', true, 'mensal-fixture-seed');
check($m['quantidade'] === 7, 'Apenas ativos com participação explícita Sim');
check(array_column($m['pendencias_configuracao'], 'colaborador_id') === [23], 'Ativo sem definição aparece em configuração, mesmo com tipo/fixo');
check(!in_array(13, array_column($m['colaboradores'], 'colaborador_id'), true), 'Não participante sem remuneração não cria atenção');
check((int)$c->query('SELECT COUNT(*) n FROM pagamento_fechamento f JOIN pagamento_fechamento_revisao r ON r.fechamento_id=f.id WHERE f.colaborador_id IN (13,23,40)')->fetch_assoc()['n'] === 0, 'Iniciar não prepara Não, NULL nem inativo');
foreach ([13,23,40] as $id) {
    $blocked = false;
    try {
        $service->preparar($id, '2026-09', 0, 'ineligible-'.$id);
    } catch (DomainException $e) {
        $blocked = true;
    }
    check($blocked, 'Preparação direta bloqueada '.$id);
}
check($m['contagens'] === ['NAO_REVISADO' => 5,'ATENCAO' => 2,'CONFIRMADO' => 0], 'Status reais');
check($c->query('SELECT COUNT(*) n FROM pagamento_fechamento_documento')->fetch_assoc()['n'] == 0, 'Iniciar não gera PDF');
check($service->mensal('2026-09', true, 'mensal-fixture-seed') === $m, 'Retry idempotente lote');
foreach ([1 => 150000,2 => 846000,3 => 467500,6 => 1428000,8 => 590000] as $b => $total) {
    $r = $service->obter($b, '2026-09')['revisao'];
    check($r['total_final_centavos'] === (string)$total, "Total $b");
}
$r = $service->obter(2, '2026-09')['revisao'];
check($r['bonus_produtividade']['quantidade_finalizacao_r0'] === 21, 'Só uma conclusão R00 por imagem');
$r8 = $service->obter(8, '2026-09')['revisao'];
check($r8['bonus_produtividade']['quantidade_finalizacao_r0'] === 10, 'Bruna 10 finalizações');
check($r8['bonus_produtividade']['valor_centavos'] === '0', 'Bruna sem bônus');
$paid = array_values(array_filter($r8['financeiro_servicos']['itens_analisados'], fn ($i) => $i['identidade']['origem_id'] === 903))[0];
check($paid['situacao'] === 'QUITADO' && $paid['saldo_centavos'] === '0', 'Caso Bruna Pago Completa é quitado');
$brunaDoc = $service->gerar(8, '2026-09', $r8['fechamento_id'], $r8['id'], 'bruna-settled-preview');
$brunaModel = json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.(int)$brunaDoc['document_id'])->fetch_assoc()['modelo_json'], true);
check(!array_filter($brunaModel['servicos'], fn ($i) => $i['identidade']['origem_id'] === 903), 'Brinquedoteca quitada não consta no PDF');
$doc = $service->gerar(2, '2026-09', $r['fechamento_id'], $r['id'], 'integration-preview');
$model = json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.(int)$doc['document_id'])->fetch_assoc()['modelo_json'], true);
check((bool)array_filter($model['rubricas'], fn ($e) => str_starts_with($e['categoria'], 'Bônus produtividade') && $e['valor_centavos'] === 38000), 'PDF apresenta bônus separado');
$blocked = false;
try {
    $service->confirmar(2, '2026-09', $doc['document_id'], $r['id'], $doc['pdf_hash'], 'before-view');
} catch (DomainException $e) {
    $blocked = true;
}check($blocked, 'Exige visualização humana');
$bytes = $service->visualizar(2, '2026-09', $doc['document_id'], 'integration-view')['bytes'];
$confirmed = $service->confirmar(2, '2026-09', $doc['document_id'], $r['id'], $doc['pdf_hash'], 'integration-confirm');
check($confirmed['estado'] === 'CONFIRMADO', 'Confirma documento visualizado');
check(hash('sha256', $bytes) === $confirmed['pdf_hash'], 'Confirma exatamente os bytes visualizados');
check($service->mensal('2026-09')['contagens']['CONFIRMADO'] === 1, 'Progresso persistido');
check($service->confirmar(2, '2026-09', $doc['document_id'], $r['id'], $doc['pdf_hash'], 'integration-confirm')['document_id'] === $doc['document_id'], 'Retry confirmação');
$fixed = $service->decidir(1, '2026-09', 1, 'fixed-extra-test', 'BONUS', ['estado' => 'DEFINIDO','motivo' => 'Reconhecimento sintético','itens' => [['categoria' => 'Extra manual fixo','referencia' => 'fixed-quality-1','valor' => '500,00']]]);
check($fixed['revisao']['total_final_centavos'] === '200000', 'FIXO soma extra manual');
check($fixed['revisao']['bonus_produtividade']['valor_centavos'] === '0', 'FIXO continua sem bônus automático');
$fixedDoc = $service->gerar(1, '2026-09', $fixed['revisao']['fechamento_id'], $fixed['revisao']['id'], 'fixed-extra-preview');
$fixedModel = json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.(int)$fixedDoc['document_id'])->fetch_assoc()['modelo_json'], true);
check((bool)array_filter($fixedModel['rubricas'], fn ($e) => $e['categoria'] === 'Extra manual fixo' && $e['valor_centavos'] === 50000), 'Extra de FIXO aparece no PDF');
check(!array_filter($fixedModel['rubricas'], fn ($e) => $e['categoria'] === 'Valor fixo'), 'Fixo não aparece como categoria no mensal');
check(count(array_filter($fixedModel['rubricas'], fn ($e) => $e['categoria'] === 'Acompanhamento' && $e['valor_centavos'] === 150000)) === 1, 'Acompanhamento da Nicolle aparece uma vez');
check(count($fixedModel['servicos']) === 2 && !array_filter($fixedModel['servicos'], fn ($i) => $i['valor_centavos'] !== 0), 'Tarefas de FIXO aparecem sem remuneração duplicada');
check((bool)array_filter($fixedModel['servicos'], fn ($i) => $i['identidade']['origem_id'] === 901 && $i['funcao'] === 'Finalização parcial'), 'Etapa P0 preserva nome da Finalização parcial no PDF');
check((int)$c->query("SELECT COUNT(*) n FROM pagamento_fechamento_decisao d JOIN pagamento_fechamento f ON f.id=d.fechamento_id WHERE f.colaborador_id=1 AND d.tipo='BONUS'")->fetch_assoc()['n'] === 1, 'Extra de FIXO mantém auditoria');
$service->visualizar(1, '2026-09', $fixedDoc['document_id'], 'fixed-extra-view');
$c->query('UPDATE colaborador SET participa_fechamento_mensal=0 WHERE idcolaborador=1');
$blocked = false;
try {
    $service->confirmar(1, '2026-09', $fixedDoc['document_id'], $fixedDoc['revision_id'], $fixedDoc['pdf_hash'], 'nonparticipant-confirm');
} catch (DomainException $e) {
    $blocked = true;
}
check($blocked, 'Mudança para Não impede confirmar preview antigo');
check($service->mensal('2026-09')['quantidade'] === 6, 'Mudança para Não atualiza fila');
$c->query('UPDATE colaborador SET participa_fechamento_mensal=1 WHERE idcolaborador=1');
$removed = $service->decidir(1, '2026-09', 2, 'fixed-extra-remove', 'BONUS', ['estado' => 'SEM_BONUS','motivo' => 'Remoção sintética','itens' => []]);
check($removed['revisao']['total_final_centavos'] === '150000', 'Remoção auditada de extra no FIXO');
check($removed['revisao']['extras']['subtotal_centavos'] === '0', 'FIXO sem extra retorna zero');
$variable = $service->decidir(6, '2026-09', 1, 'variable-extra-test', 'BONUS', ['estado' => 'DEFINIDO','motivo' => 'Reconhecimento sintético','itens' => [['categoria' => 'Extra variável','referencia' => 'variable-quality-1','valor' => '500,00']]]);
check($variable['revisao']['total_final_centavos'] === '1478000', 'VARIAVEL soma serviços, R0 e extra manual');
$next = $service->decidir(3, '2026-09', 1, 'extra-test', 'BONUS', ['estado' => 'DEFINIDO','motivo' => 'Qualidade sintética','itens' => [['categoria' => 'Bônus qualidade','referencia' => 'quality-1','valor' => '500,00']]]);
check($next['revisao']['total_final_centavos'] === '517500', 'Extra opcional auditado');
$d = $service->gerar(3, '2026-09', $next['revisao']['fechamento_id'], $next['revisao']['id'], 'preview-before-update');
$model = json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.(int)$d['document_id'])->fetch_assoc()['modelo_json'], true);
check((bool)array_filter($model['rubricas'], fn ($e) => $e['categoria'] === 'Bônus qualidade' && $e['valor_centavos'] === 50000), 'PDF apresenta extra manual');
check(count($model['servicos']) === 2 && count(array_filter($model['servicos'], fn ($i) => $i['valor_centavos'] === 0)) === 1, 'Tarefa zero de FIXO_VARIAVEL aparece no adendo');
$service->visualizar(3, '2026-09', $d['document_id'], 'view-before-update');
$service->preparar(3, '2026-09', 2, 'update-after-preview');
$blocked = false;
try {
    $service->confirmar(3, '2026-09', $d['document_id'], $d['revision_id'], $d['pdf_hash'], 'stale-confirm');
} catch (DomainException $e) {
    $blocked = true;
}check($blocked, 'Nova revisão impede confirmar preview obsoleto');
// Outro mês não reconta eventos concluídos em setembro.
$oct = $service->preparar(2, '2026-10', 0, 'october');
check($oct['revisao']['bonus_produtividade']['quantidade_finalizacao_r0'] === 0, 'Sem repetição entre meses');
$c->query('UPDATE colaborador SET participa_fechamento_mensal=1 WHERE idcolaborador=23');
$classified = $service->mensal('2026-09', true, 'after-classification');
check($classified['quantidade'] === 8 && $classified['pendencias_configuracao'] === [], 'Classificação explícita Sim acrescenta participante e resolve configuração');
check($service->obter(23, '2026-09')['revisao']['total_final_centavos'] === '250000', 'Iniciar prepara somente depois da classificação');
$c->query('UPDATE colaborador SET participa_fechamento_mensal=0 WHERE ativo=1');
$c->query('UPDATE colaborador SET participa_fechamento_mensal=NULL WHERE idcolaborador=23');
$empty = $service->mensal('2026-09', true, 'no-participants');
check($empty['quantidade'] === 0 && $empty['contagens'] === ['NAO_REVISADO' => 0,'ATENCAO' => 0,'CONFIRMADO' => 0], 'Fila vazia não inclui Não/NULL');
check(array_column($empty['pendencias_configuracao'], 'colaborador_id') === [23], 'Configuração permanece visível mesmo sem participantes');
$c->query('UPDATE colaborador SET participa_fechamento_mensal=1 WHERE idcolaborador IN (1,2,3,4,5,6,8)');
file_put_contents(__DIR__.'/../output/mensal-fixture.json', json_encode($fixture, JSON_UNESCAPED_SLASHES));
echo "$checks verificações de integração passaram. Fixture sintética preservada para teste de navegador.\n";

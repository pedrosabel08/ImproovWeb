<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__.'/PlanejamentoAlteracaoTest.php';
$config = flow_pac_config_validar(['capacidade_padrao' => 25,'niveis' => [1 => 5,2 => 10,3 => 15,4 => 20,5 => 25],'colaboradores' => [2 => 20,3 => 0]]);
pa_assert($config['colaboradores'][3] === 0.0, 'Override zero representa indisponibilidade');
foreach ([0,-1,'abc',10001] as $n) {
    pa_fails(fn () => flow_pac_config_validar(['capacidade_padrao' => $n,'niveis' => $config['niveis']]), 'números válidos');
}
$items = [];
foreach ([1,1,1,2,2,2,3,3] as $idx => $n) {
    $items[] = ['entrega_item_id' => $idx + 1,'nivel_complexidade_snapshot' => $n,'responsavel_planejado_id' => null,'fim_planejado' => null];
}
$items = flow_pac_pontuar($items, $config);
pa_assert(array_sum(array_column($items, 'pontos_snapshot')) === 75.0, 'Demanda N1×3 N2×3 N3×2 = 75');
$newConfig = $config;
$newConfig['niveis'][3] = 18;
pa_assert(flow_pac_pontuar($items, $newConfig, $items)[7]['pontos_snapshot'] === 15.0, 'Novo peso não muda o snapshot de rodada confirmada');
pa_assert(flow_pac_pontuar($items, $newConfig)[7]['pontos_snapshot'] === 18, 'Plano novo usa peso novo');
$ds = flow_pac_dias('2026-10-28', '2026-11-06');
pa_assert(!in_array('2026-10-31', $ds) && !in_array('2026-11-01', $ds) && !in_array('2026-11-02', $ds), 'Calendário exclui fim de semana e feriado');
$ps = [['id' => 1,'tipo_atuacao' => 'PRINCIPAL'],['id' => 2,'tipo_atuacao' => 'SECUNDARIA'],['id' => 3,'tipo_atuacao' => 'SECUNDARIA']];
$agenda = [];
foreach ($ps as $p) {
    foreach ($ds as $d) {
        $agenda[$p['id']][$d] = ['capacidade' => 25,'comprometidos' => 0,'avisos' => []];
    }
}
$tipos = $items;
foreach ($tipos as $idx => &$item) {
    $item['tipo_imagem'] = $idx < 4 ? 'Unidade' : 'Fachada';
}
unset($item);
$prioridade = flow_pac_sugerir($tipos, [$ps[0]], $agenda, $ds, '2026-11-06', 'MAIOR', [], ['Unidade','Fachada']);
$prioridadeMapa = array_column($prioridade['itens'], null, 'entrega_item_id');
pa_assert(max(array_column(array_slice($prioridadeMapa, 0, 4), 'fim_planejado')) <= min(array_column(array_slice($prioridadeMapa, 4), 'fim_planejado')), 'Prioridade por tipo prevalece sobre complexidade com uma pessoa');
pa_assert($prioridade['itens'][0]['tipo_imagem'] === 'Unidade', 'Tipo prioritário é processado primeiro');
pa_fails(fn () => flow_pac_sugerir($tipos, $ps, $agenda, $ds, '2026-11-06', 'MAIOR', [], ['Unidade','Unidade']), 'Ordem de tipos inválida');
pa_fails(fn () => flow_pac_sugerir($tipos, $ps, $agenda, $ds, '2026-11-06', 'MAIOR', [], ['Inexistente']), 'não pertence');
$porTipo = flow_pac_sugerir($tipos, $ps, $agenda, $ds, '2026-11-06', 'MAIOR', ['Unidade' => 2,'Fachada' => 1]);
foreach ($porTipo['itens'] as $item) {
    pa_assert($item['responsavel_planejado_id'] === ($item['tipo_imagem'] === 'Unidade' ? 2 : 1), 'Regra por tipo é respeitada');
}
pa_assert(!flow_pac_sobrecargas($porTipo['itens'], $agenda), 'Regra por tipo respeita capacidade');
pa_fails(fn () => flow_pac_sugerir($tipos, [$ps[0]], $agenda, $ds, '2026-11-06', 'MAIOR', ['Unidade' => 2]), 'colaborador presente');
pa_fails(fn () => flow_pac_sugerir($tipos, $ps, $agenda, $ds, '2026-10-28', 'MAIOR', ['Unidade' => 2,'Fachada' => 1]), 'Não foi encontrada');
$tipos[0]['fixado'] = true;
$tipos[0]['responsavel_planejado_id'] = 1;
$tipos[0]['fim_planejado'] = $ds[0];
pa_fails(fn () => flow_pac_sugerir($tipos, $ps, $agenda, $ds, '2026-11-06', 'MAIOR', ['Unidade' => 2]), 'imagem fixada');
$ordenacao = [
    ['entrega_item_id' => 101,'nivel_complexidade_snapshot' => 1,'pontos_snapshot' => 20,'responsavel_planejado_id' => null,'fim_planejado' => null],
    ['entrega_item_id' => 102,'nivel_complexidade_snapshot' => 5,'pontos_snapshot' => 5,'responsavel_planejado_id' => null,'fim_planejado' => null],
];
$agendaOrdem = $agenda;
foreach ($ds as $d) {
    $agendaOrdem[1][$d]['capacidade'] = 20;
}
$maior = flow_pac_sugerir($ordenacao, [$ps[0]], $agendaOrdem, $ds, '2026-11-06', 'MAIOR');
$menor = flow_pac_sugerir($ordenacao, [$ps[0]], $agendaOrdem, $ds, '2026-11-06', 'MENOR');
$mapaMaior = array_column($maior['itens'], null, 'entrega_item_id');
$mapaMenor = array_column($menor['itens'], null, 'entrega_item_id');
pa_assert($mapaMaior[102]['fim_planejado'] === $ds[0] && $mapaMaior[101]['fim_planejado'] === $ds[1], 'Maior prioriza N5 mesmo com pontos configurados menores');
pa_assert($mapaMenor[101]['fim_planejado'] === $ds[0] && $mapaMenor[102]['fim_planejado'] === $ds[1], 'Menor prioriza N1 e altera a distribuição');
pa_assert(flow_pac_sugerir($ordenacao, [$ps[0]], $agendaOrdem, $ds, '2026-11-06')['itens'] === $maior['itens'], 'Sem preferência mantém maior complexidade primeiro');
pa_fails(fn () => flow_pac_sugerir($ordenacao, [$ps[0]], $agendaOrdem, $ds, '2026-11-06', 'INVALIDO'), 'Ordem de complexidade inválida');
$ordenacao[1]['fixado'] = true;
$ordenacao[1]['responsavel_planejado_id'] = 1;
$ordenacao[1]['fim_planejado'] = $ds[1];
$fixada = flow_pac_sugerir($ordenacao, [$ps[0]], $agendaOrdem, $ds, '2026-11-06', 'MENOR');
pa_assert(array_column($fixada['itens'], null, 'entrega_item_id')[102]['fim_planejado'] === $ds[1], 'Ordem menor preserva imagem fixada');
foreach ([1,2,3] as $n) {
    $s = flow_pac_sugerir($items, array_slice($ps, 0, $n), $agenda, $ds, '2026-11-06');
    pa_assert(count(array_unique(array_column($s['itens'], 'responsavel_planejado_id'))) === 1, 'Equipe mínima com '.$n.' candidatos');
    pa_assert(max(array_column($s['itens'], 'fim_planejado')) === '2026-10-30', 'Distribuição real termina em três dias');
    pa_assert(!flow_pac_sobrecargas($s['itens'], $agenda), 'Sugestão nunca excede capacidade');
}
$s = flow_pac_sugerir($items, $ps, $agenda, $ds, '2026-10-29');
pa_assert(count(array_unique(array_column($s['itens'], 'responsavel_planejado_id'))) === 2, 'Prazo curto utiliza duas pessoas');
$s = flow_pac_sugerir($items, $ps, $agenda, $ds, '2026-10-28');
pa_assert(count(array_unique(array_column($s['itens'], 'responsavel_planejado_id'))) === 3, 'Prazo de um dia requer três pessoas');
pa_fails(fn () => flow_pac_sugerir($items, [$ps[0]], $agenda, $ds, '2026-10-28'), 'Não foi encontrada');
$fixed = $items;
$fixed[7]['fixado'] = true;
$fixed[7]['responsavel_planejado_id'] = 2;
$fixed[7]['fim_planejado'] = '2026-10-29';
$s = flow_pac_sugerir($fixed, $ps, $agenda, $ds, '2026-11-06');
$byId = array_column($s['itens'], null, 'entrega_item_id');
pa_assert($byId[8]['fim_planejado'] === '2026-10-29' && $byId[8]['responsavel_planejado_id'] === 2, 'Decisão fixada permanece');
$partial = $agenda;
$partial[1]['2026-10-28']['comprometidos'] = 15;
$s = flow_pac_sugerir($items, [$ps[0]], $partial, $ds, '2026-11-06');
pa_assert(!flow_pac_sobrecargas($s['itens'], $partial), 'Considera compromisso confirmado de outra R01');
$unavailable = $agenda;
foreach ($ds as $d) {
    $unavailable[1][$d]['capacidade'] = 0;
}
$s = flow_pac_sugerir($items, $ps, $unavailable, $ds, '2026-11-06');
pa_assert(!in_array(1, array_column($s['itens'], 'responsavel_planejado_id')), 'Ignora colaborador indisponível');
$unknown = $agenda;
foreach ($ds as $d) {
    $unknown[1][$d]['avisos'] = ['R00 sem ocupação confiável'];
}
$s = flow_pac_sugerir($items, $ps, $unknown, $ds, '2026-11-06');
pa_assert(!in_array(1, array_column($s['itens'], 'responsavel_planejado_id')), 'Não trata capacidade desconhecida como livre');
$exact = [['entrega_item_id' => 1,'pontos_snapshot' => 25,'responsavel_planejado_id' => 1,'fim_planejado' => $ds[0]]];
pa_assert(!flow_pac_sobrecargas($exact, $agenda), '25 pontos exatos');
$exact[0]['pontos_snapshot'] = 20;
pa_assert(!flow_pac_sobrecargas($exact, $agenda), 'Abaixo da capacidade');
$exact[0]['pontos_snapshot'] = 30;
pa_assert(flow_pac_sobrecargas($exact, $agenda)[0]['excesso'] === 5.0, 'Sobrecarga exata de 5 pontos');
$different = $agenda;
foreach ($ds as $d) {
    $different[1][$d]['capacidade'] = 10;
}
$s = flow_pac_sugerir($items, $ps, $different, $ds, '2026-10-29');
pa_assert(!flow_pac_sobrecargas($s['itens'], $different), 'Capacidades diferentes');
$facts = flow_pac_resumir_realizado(
    [['entrega_item_id' => 1,'data_entregue' => '2026-11-06']],
    [['entrega_item_id' => 1,'responsavel_planejado_id' => 1,'fim_planejado' => '2026-10-28']],
    [['entrega_item_id' => 1,'responsavel_planejado_id' => 2,'fim_planejado' => '2026-10-29','pontos_snapshot' => 15]],
    [['id' => 1,'entrega_item_id' => 1,'inicio_em' => '2026-10-29 08:00:00','encerrado_em' => '2026-10-30 16:00:00','situacao' => 'ENCERRADO','motivo_encerramento' => 'ENVIO_APROVACAO','responsavel_atual_id' => 3,'executor_nome' => 'Executor']]
);
pa_assert($facts['itens'][0]['baseline']['responsavel_planejado_id'] === 1 && $facts['itens'][0]['vigente']['responsavel_planejado_id'] === 2 && $facts['itens'][0]['executor_id'] === 3, 'Baseline, vigente e executor são fatos distintos');
pa_assert($facts['itens'][0]['dias_uteis_atraso'] === 1 && $facts['itens'][0]['entrega_cliente'] === '2026-11-06', 'Conclusão humana e entrega ao cliente permanecem distintas');
pa_assert($facts['por_dia'][3]['2026-10-30']['concluidos'] === 15.0 && $facts['por_dia'][2]['2026-10-29']['pendentes_do_planejado'] === 15.0, 'Realizado por dia preserva pontos e pendências');
$none = flow_pac_resumir_realizado([['entrega_item_id' => 2]], [], [], [['entrega_item_id' => 1,'inicio_em' => '2026-10-29']]);
pa_assert($none['itens'][0]['executor_id'] === null && $none['itens'][0]['conclusao_alteracao'] === null, 'Outra rodada não fornece evidência de execução');
require_once dirname(__DIR__, 2).'/helpers/planejamento_alocacao_helper.php';
$r00 = ['grupos' => [['projetos' => [['codigo_etapa' => 'COMPOSICAO','inicio' => '2026-10-28','limite' => '2026-10-29','duracao_dias_uteis' => 1,'carga_total_planejada_pessoa_dia' => 1,'planejado' => 5,'pessoas' => [['id' => 1]],'tarefas_operacionais' => array_fill(0, 3, ['responsaveis' => [['id' => 1]]])]]]]];
$nominal = [1 => ['2026-10-28' => ['nominal' => 25,'capacidade' => 25,'comprometidos' => 0,'ocupacao_r00' => 0,'avisos' => []]]];
$reduced = flow_pac_adaptar_r00($nominal, $r00, ['2026-10-28']);
pa_assert($reduced[1]['2026-10-28']['capacidade'] === 10.0, '60% de ocupação R00 reduz oferta de 25 para 10 pontos');
$r00['grupos'][0]['projetos'][0]['codigo_etapa'] = 'MODELAGEM_FACHADA';
$unknown = flow_pac_adaptar_r00($nominal, $r00, ['2026-10-28']);
pa_assert($unknown[1]['2026-10-28']['capacidade'] === 25.0 && count($unknown[1]['2026-10-28']['avisos']) === 1, 'Janela sem medida confiável preserva nominal com alerta explícito');
pa_assert($unknown[1]['2026-10-28']['disponivel'] === null,'Disponibilidade desconhecida não é devolvida como número preciso');
$historico = flow_pac_decisoes_sugestao([['responsavel_planejado_id' => 99,'fixado' => false],['responsavel_planejado_id' => 99,'fixado' => true],['responsavel_planejado_id' => 1]],$ps);
pa_assert($historico[0]['responsavel_planejado_id'] === null && $historico[1]['responsavel_planejado_id'] === 99 && $historico[2]['responsavel_planejado_id'] === 1,'Sugestão redistribui histórico inelegível somente quando não fixado');
$legacyFacts = flow_pac_resumir_realizado([['entrega_item_id' => 1]],[],[['entrega_item_id' => 1,'responsavel_planejado_id' => 1,'fim_planejado' => '2026-10-29']],[]);
pa_assert($legacyFacts['por_dia'][1]['2026-10-29']['planejados'] === null && $legacyFacts['por_dia'][1]['2026-10-29']['pendentes_do_planejado'] === null,'Métricas históricas sem snapshot não afirmam zero pontos');
echo "OK: $checks verificações acumuladas de pontos, snapshot, calendário e distribuição.\n";

<?php
require_once __DIR__ . '/../helpers/imagem_contexto_operacional_helper.php';
function ico_assert(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function ico_task(int $id, int $funcao, string $status): array {
    return ['idfuncao_imagem' => $id, 'imagem_id' => 7, 'funcao_id' => $funcao, 'funcao_nome' => "Função $funcao", 'status' => $status, 'responsavel_id' => 8, 'responsavel_nome' => 'Maria', 'prazo' => '2026-10-15'];
}
$image = ['id' => 7];
$tasks = [ico_task(10, 2, 'Finalizado'), ico_task(11, 3, 'Em andamento'), ico_task(12, 4, 'Em aprovação'), ico_task(13, 5, 'Não iniciado'), ico_task(14, 6, 'HOLD'), ico_task(15, 4, 'Ajuste'), ico_task(16, 9, 'Cancelado'), ico_task(17, 7, 'Não se aplica')];
$logs = [10 => [['data' => '2026-10-14', 'status_novo' => 'Finalizado']], 15 => [['data' => '2026-10-10', 'status_novo' => 'Finalizado'], ['data' => '2026-10-14', 'status_novo' => 'Ajuste']]];
$requirements = array_fill_keys(array_column($tasks, 'idfuncao_imagem'), ['elegivel' => true]);
$requirements[11] = ['elegivel' => false];
$plan = ['planejamento_disponivel' => true, 'etapa_codigo' => 'COMPOSICAO', 'janela_fim' => '2026-10-16', 'prazo_necessario' => '2026-10-16', 'timeline' => [['codigo' => 'COMPOSICAO', 'estado' => 'CONCLUIDA']]];
$result = flow_imagem_contexto_operacional_montar($image, $tasks, $logs, [11 => $plan], $requirements);
$byId = array_column($result['etapas'], null, 'funcao_imagem_id');
ico_assert($result['imagem']['atualizado_em'] === '2026-10-14', 'Última atualização deve vir dos logs reais, sem usar o horário da consulta.');
ico_assert($result['etapas_ativas'] === [11, 12, 15], 'Todas as etapas ativas devem ser preservadas, sem prioridade artificial.');
ico_assert($result['etapas_concluidas'] === [10], 'Conclusão pertence à própria tarefa.');
ico_assert($byId[11]['estado_visual'] === 'ATIVA', 'Conclusão coletiva ou bloqueio de início não pode apagar trabalho real.');
ico_assert($byId[11]['avisos'] === ['ATIVA_COM_RESTRICAO_DE_INICIO'], 'Conflito operacional deve ficar explícito.');
ico_assert($byId[15]['execucao_individual']['conclusao_real'] === null, 'Reabertura não preserva conclusão ativa.');
ico_assert($byId[14]['estado_visual'] === 'BLOQUEADA', 'HOLD é bloqueado.');
ico_assert($byId[13]['estado_visual'] === 'PENDENTE', 'Não iniciado e elegível é pendente.');
ico_assert(count($result['etapas_nao_operacionais']) === 2, 'Cancelado e dispensado não são ativos nem concluídos.');
ico_assert(count($result['etapas']) === count($tasks), 'Nenhum registro pode ser descartado.');
ico_assert($result['avisos'][0]['funcao_imagem_ids'] === [12, 15], 'Funções repetidas devem ser explicitadas.');
ico_assert($byId[11]['prazo'] === '2026-10-15' && $byId[11]['contexto_planejamento']['prazo_planejado'] === '2026-10-16', 'Prazo operacional e planejado permanecem separados.');
ico_assert(!isset($byId[11]['contexto_planejamento']['timeline']), 'Não expor estado coletivo da entrega.');
$requirements[13] = ['elegivel' => false];
$blocked = flow_imagem_contexto_operacional_montar($image, [$tasks[3]], [], [], $requirements);
ico_assert($blocked['etapas_bloqueadas'] === [13], 'Requisitos impedem início pendente.');
$empty = flow_imagem_contexto_operacional_montar($image, [], [], [], []);
foreach ([1, 3] as $quantity) {
    $unassigned = array_map(static function($id) { $task = ico_task($id, 4, 'Não iniciado'); $task['responsavel_id'] = null; $task['responsavel_nome'] = null; return $task; }, range(31, 30 + $quantity));
    $ctx = flow_imagem_contexto_operacional_montar($image, $unassigned, [], [], array_fill_keys(array_column($unassigned, 'idfuncao_imagem'), ['elegivel' => true]));
    ico_assert(count($ctx['etapas_pendentes']) === $quantity, 'Ausência de responsável não dispensa nem remove funções.');
}
ico_assert($empty['etapas'] === [] && $empty['etapas_ativas'] === [], 'Imagem sem funções não pode ganhar etapas inventadas.');
$isolated = flow_imagem_contexto_operacional_montar($image, [$tasks[0]], $logs, [999 => $plan], $requirements);
ico_assert($isolated['etapas'][0]['ordem'] === 20, 'Plano de outra imagem no lote não pode contaminar a ordenação.');
foreach (['Finalizado', 'Aprovado', 'Aprovado com ajustes'] as $status) {
    $done = flow_imagem_contexto_operacional_montar($image, [ico_task(1, 2, $status)], [], [], []);
    ico_assert($done['etapas_concluidas'] === [1], 'Reutilizar todos os estados finais existentes.');
    ico_assert($done['etapas'][0]['execucao_individual']['conclusao_real'] === null, 'Não inventar data de conclusão.');
}
try {
    flow_imagem_contexto_operacional_montar(['id' => 8], [$tasks[0]], [], [], []);
    throw new RuntimeException('Deveria rejeitar tarefa de outra imagem.');
} catch (InvalidArgumentException $expected) {}
echo "OK: estados individuais, etapas simultâneas, HOLD, requisitos, retornos, repetição, datas, ausência de tarefas e isolamento por imagem.\n";

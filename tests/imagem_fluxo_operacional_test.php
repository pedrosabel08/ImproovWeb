<?php
require_once __DIR__ . '/../helpers/imagem_contexto_operacional_helper.php';
function fluxo_assert($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$image = ['id' => 3267, 'tipo_imagem' => 'Unidade'];
$tasks = [
    ['idfuncao_imagem'=>1,'imagem_id'=>3267,'funcao_id'=>1,'funcao_nome'=>'Caderno','status'=>'Finalizado'],
    ['idfuncao_imagem'=>2,'imagem_id'=>3267,'funcao_id'=>8,'funcao_nome'=>'Filtro de assets','status'=>'Em aprovação'],
    ['idfuncao_imagem'=>3,'imagem_id'=>3267,'funcao_id'=>3,'funcao_nome'=>'Composição','status'=>'Não iniciado'],
];
$context = flow_imagem_contexto_operacional_montar($image, $tasks, [], [], []);
$planned = [['imagem_id'=>3267,'funcao_id'=>4,'funcao_nome'=>'Finalização','status'=>'TODO']];
$plan = ['versao_id'=>14,'plano_versao'=>2,'snapshot'=>['etapas'=>[
    ['codigo'=>'CADERNO_FILTRO','inicio'=>'2026-09-16','limite'=>'2026-10-01'],
    ['codigo'=>'COMPOSICAO','inicio'=>'2026-10-14','limite'=>'2026-10-26'],
    ['codigo'=>'FINALIZACAO_INTERNA','inicio'=>'2026-10-26','limite'=>'2026-11-03'],
]]];
$flow = flow_imagem_fluxo_operacional_montar($context, $planned, $plan);
fluxo_assert(count($flow['etapas'])===3, 'Uma etapa Caderno/Filtro e uma Finalização prevista.');
$combo = $flow['etapas'][0];
fluxo_assert($combo['estado_visual']==='ATIVA', 'Filtro em aprovação não pode ser escondido pela conclusão do Caderno.');
fluxo_assert(count($combo['tarefas'])===2 && count($combo['componentes'])===2, 'Preservar as tarefas e ações reais.');
$finish = $flow['etapas'][2];
fluxo_assert($finish['funcao_imagem_id']===null && $finish['responsavel_id']===null, 'Não inventar tarefa nem responsável.');
fluxo_assert($finish['contexto_planejamento']['prazo_planejado']==='2026-11-03', 'Usar janela real do snapshot vigente.');
fluxo_assert($finish['execucao_individual']['conclusao_real']===null, 'Não inventar execução.');
$tasks[] = ['idfuncao_imagem'=>4,'imagem_id'=>3267,'funcao_id'=>4,'funcao_nome'=>'Finalização','status'=>'Não iniciado'];
$materialized = flow_imagem_contexto_operacional_montar($image, $tasks, [], [], []);
$flow = flow_imagem_fluxo_operacional_montar($materialized, $planned, $plan);
fluxo_assert(count(array_filter($flow['etapas'], static fn ($e)=>$e['funcao_id']===4))===1, 'Materialização não duplica Finalização.');
fluxo_assert(count(flow_imagem_fluxo_operacional_montar($context, [], $plan)['etapas'])===2, 'Snapshot não cria funções sem declaração real.');
$empty = flow_imagem_contexto_operacional_montar($image, [], [], [], []);
fluxo_assert(count(flow_imagem_fluxo_operacional_montar($empty,$planned,$plan)['etapas'])===1, 'Imagem sem tarefas mantém função prevista.');
echo "OK: funções previstas reais, união Caderno/Filtro, aprovação preservada, datas e materialização sem duplicação.\n";
$multiple = array_merge($planned, [['imagem_id'=>3267,'funcao_id'=>5,'funcao_nome'=>'Pós-produção','status'=>'TODO']]);
fluxo_assert(count(flow_imagem_fluxo_operacional_montar($empty,$multiple,$plan)['etapas'])===2, 'Várias funções previstas permanecem visíveis sem responsáveis.');
$mixed = flow_imagem_contexto_operacional_montar($image, [$tasks[1]], [], [], []);
$pendingCaderno = [['imagem_id'=>3267,'funcao_id'=>1,'funcao_nome'=>'Caderno','status'=>'TODO']];
$mixedFlow = flow_imagem_fluxo_operacional_montar($mixed,$pendingCaderno,$plan);
fluxo_assert($mixedFlow['etapas'][0]['estado_visual']==='ATIVA' && !$mixedFlow['etapas'][0]['somente_planejada'], 'União mista não oculta tarefa real ativa.');

<?php

require_once dirname(__DIR__, 2) . '/helpers/planejamento_fila_operacional_helper.php';

function fila_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function fila_task(int $id, int $image, int $function, int $person, string $status = 'Não iniciado', int $priority = 3): array
{
    return [
        'tarefa_id' => $id,
        'imagem_id' => $image,
        'funcao_id' => $function,
        'colaborador_id' => $person,
        'status' => $status,
        'prioridade' => $priority,
        'tipo_imagem' => 'Imagem Interna',
        'entrega_id' => 1,
        'prazo_entrega' => '2026-09-30',
    ];
}

// Caderno/Filtro de uma imagem é uma unidade; responsabilidade divergente é explícita.
$unidades = flow_fila_unidades_logicas([fila_task(1, 10, 1, 7), fila_task(2, 10, 8, 7)]);
fila_assert(count($unidades) === 1 && !$unidades[0]['responsabilidade_divergente'], 'Caderno/Filtro deve deduplicar por imagem e responsável.');
$divergente = flow_fila_unidades_logicas([fila_task(3, 11, 1, 7), fila_task(4, 11, 8, 8)]);
fila_assert($divergente[0]['responsabilidade_divergente'] && count($divergente[0]['responsaveis']) === 2, 'Responsáveis diferentes precisam ficar explícitos.');

// Ordem derivada: prioridade, prazo e finalmente imagem/tarefa.
$ordem = [fila_task(10, 20, 2, 7, 'Não iniciado', 3), fila_task(11, 21, 2, 7, 'Não iniciado', 1)];
foreach ($ordem as &$item) $item['entrega_id'] = 2;
unset($item);
$unidadesOrdem = flow_fila_unidades_logicas($ordem);
flow_fila_ordenar_unidades($unidadesOrdem, [2 => ['MODELAGEM_INTERNA' => ['inicio' => '2026-09-01', 'ordem_apresentacao' => 2]]]);
fila_assert($unidadesOrdem[0]['prioridade'] === 1, 'Prioridade menor deve entrar antes na fila derivada.');

$plan = [
    'data_entrega' => '2026-09-30',
    'etapas' => [
        ['codigo' => 'CADERNO_FILTRO', 'nome' => 'Caderno', 'volume' => 2, 'pessoas_alocadas' => 2, 'inicio' => '2026-08-20', 'limite' => '2026-08-25', 'dependencias' => []],
        ['codigo' => 'MODELAGEM_INTERNA', 'nome' => 'Modelagem', 'volume' => 1, 'pessoas_alocadas' => 1, 'inicio' => '2026-08-25', 'limite' => '2026-08-28', 'dependencias' => ['CADERNO_FILTRO']],
    ],
];
$units = [
    'CADERNO_FILTRO' => [
        ['chave' => 'cf:1', 'responsaveis' => [7]],
        ['chave' => 'cf:2', 'responsaveis' => [8]],
    ],
    'MODELAGEM_INTERNA' => [['chave' => 'mi:1', 'responsaveis' => [9]]],
];
$estimates = [
    'cf:1' => ['pessoa_dias' => 2, 'confianca' => 'ALTA'],
    'cf:2' => ['pessoa_dias' => 2, 'confianca' => 'ALTA'],
    'mi:1' => ['pessoa_dias' => 1, 'confianca' => 'MEDIA'],
];
$availability = [
    7 => ['disponivel_em' => '2026-09-03'],
    8 => ['disponivel_em' => '2026-09-01'],
    9 => ['disponivel_em' => '2026-08-26'],
];
$projection = flow_fila_projetar_etapas($plan, $plan, $units, $estimates, $availability, '2026-08-26');
fila_assert($projection['CADERNO_FILTRO']['inicio_operacional_projetado'] === '2026-09-01', 'Frentes paralelas devem iniciar na disponibilidade de cada responsável.');
$fimFrenteLenta = flow_fila_adicionar_esforco('2026-09-03', 2);
fila_assert($projection['CADERNO_FILTRO']['fim_operacional_projetado'] === $fimFrenteLenta, 'Fim da etapa deve aguardar a frente mais lenta.');
fila_assert($projection['MODELAGEM_INTERNA']['inicio_operacional_projetado'] === $fimFrenteLenta, 'Dependência deve propagar o fim operacional.');
fila_assert($projection['CADERNO_FILTRO']['status_operacional'] === 'MARGEM_CONSUMIDA', 'Atraso do marco sem ultrapassar entrega consome margem.');

// Esforço fracionado da Pós não pode virar um dia por item.
fila_assert(flow_fila_adicionar_esforco('2026-09-01', 0.8) === '2026-09-02', 'Quatro tarefas de 0,2 pessoa-dia devem ocupar um dia útil.');
fila_assert(flow_fila_margem_operacional('2026-10-20', '2026-10-16') === -2, 'Margem negativa deve manter o sinal do atraso.');

$db = mysqli_init(); // A regra de Finalização é fixa e não executa consultas históricas.
$hold = fila_task(30, 40, 4, 7, 'HOLD');
$hold['tipo_imagem'] = 'Planta Humanizada';
$holdUnit = flow_fila_unidades_logicas([$hold])[0];
$holdPlan = ['codigo' => 'FINALIZACAO_PLANTA', 'volume' => 1, 'pessoas_alocadas' => 1, 'estrategia_duracao' => 'OPERACIONAL_POR_TAREFA', 'inicio' => '2026-08-20', 'limite' => '2026-08-25', 'dependencias' => []];
$cache = [];
$holdEstimate = flow_fila_estimativa_unidade($db, $holdUnit, $holdPlan, [], $cache, '2026-08-26');
fila_assert($holdEstimate['pessoa_dias'] === 1.0 && !$holdEstimate['bloqueada'], 'HOLD deve conservar o esforço estimado e entrar na projeção.');
fila_assert($holdEstimate['tarefas_em_hold'] === [30], 'A tarefa pausada deve permanecer identificada no resultado.');
$follow = fila_task(31, 41, 4, 7);
$follow['entrega_id'] = 2;
$follow['tipo_imagem'] = 'Planta Humanizada';
$followUnit = flow_fila_unidades_logicas([$follow])[0];
$queue = flow_fila_disponibilidade_por_responsavel([$holdUnit, $followUnit], [$holdUnit['chave'] => $holdEstimate, $followUnit['chave'] => ['pessoa_dias' => 1, 'confianca' => 'MEDIA']], 2, '2026-08-26');
fila_assert(!$queue[7]['bloqueada_por_fila'] && $queue[7]['disponivel_em'] === '2026-08-27', 'HOLD anterior ocupa apenas o esforço estimado e não trava a agenda.');
$planHold = ['data_entrega' => '2026-09-30', 'etapas' => [
    $holdPlan,
    ['codigo' => 'FINALIZACAO_GLOBAL', 'nome' => 'Marco', 'dependencias' => ['FINALIZACAO_PLANTA']],
    ['codigo' => 'POS_PRODUCAO', 'nome' => 'Pós', 'volume' => 1, 'limite' => '2026-09-20', 'dependencias' => ['FINALIZACAO_GLOBAL']],
]];
$post = ['chave' => 'post', 'responsaveis' => [8]];
$projectionHold = flow_fila_projetar_etapas($planHold, $planHold, ['FINALIZACAO_PLANTA' => [$holdUnit], 'POS_PRODUCAO' => [$post]], [$holdUnit['chave'] => $holdEstimate, 'post' => ['pessoa_dias' => 1, 'confianca' => 'MEDIA']], [7 => ['disponivel_em' => '2026-08-26'], 8 => ['disponivel_em' => '2026-08-26']], '2026-08-26');
fila_assert($projectionHold['FINALIZACAO_PLANTA']['fim_operacional_projetado'] === '2026-08-27', 'A planta em HOLD deve receber uma data estimada.');
fila_assert($projectionHold['FINALIZACAO_PLANTA']['tarefas_em_hold'] === [30], 'O aviso de HOLD deve aparecer na etapa.');
fila_assert($projectionHold['FINALIZACAO_GLOBAL']['fim_operacional_projetado'] === '2026-08-27' && $projectionHold['POS_PRODUCAO']['fim_operacional_projetado'] === '2026-08-28', 'A data da planta deve propagar para finalização global e pós.');
$hold['impedimentos_flow_block'] = [['id' => 8, 'status' => 'ABERTA']];
$blockedUnit = flow_fila_unidades_logicas([$hold])[0];
$blockEstimate = flow_fila_estimativa_unidade($db, $blockedUnit, $holdPlan, [], $cache, '2026-08-26');
fila_assert(count($blockEstimate['impedimentos']) === 1 && !$blockEstimate['bloqueada'], 'O impedimento real deve ser distinto do HOLD e tornar a estimativa condicional.');
$queueBlocked = flow_fila_disponibilidade_por_responsavel([$blockedUnit, $followUnit], [$blockedUnit['chave'] => $blockEstimate, $followUnit['chave'] => ['pessoa_dias' => 1, 'confianca' => 'MEDIA']], 2, '2026-08-26');
fila_assert(!$queueBlocked[7]['bloqueada_por_fila'] && $queueBlocked[7]['disponivel_em'] === '2026-08-26', 'Um impedimento real em outra tarefa não deve ocupar nem bloquear a agenda.');
$conditional = flow_fila_projetar_etapas($planHold, $planHold, ['FINALIZACAO_PLANTA' => [$blockedUnit]], [$blockedUnit['chave'] => $blockEstimate], [7 => ['disponivel_em' => '2026-08-26']], '2026-08-26');
fila_assert($conditional['FINALIZACAO_PLANTA']['projecao_condicional'] && count($conditional['FINALIZACAO_PLANTA']['impedimentos_ativos']) === 1, 'A data deve explicitar que depende da resolução do Flow Block.');
$blocked = flow_fila_projetar_etapas($plan, $plan, ['CADERNO_FILTRO' => [['chave' => 'sem-responsavel', 'responsaveis' => []]]], ['sem-responsavel' => ['pessoa_dias' => 1, 'confianca' => 'MEDIA']], [], '2026-08-26');
fila_assert($blocked['CADERNO_FILTRO']['status_operacional'] === 'BLOQUEADO', 'Uma tarefa sem responsável deve continuar sem uma data confiável.');

echo "OK: fila derivada, Caderno/Filtro, paralelismo, dependências, esforço, margem e HOLD validados.\n";

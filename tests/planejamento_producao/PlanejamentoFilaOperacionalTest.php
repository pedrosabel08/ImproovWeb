<?php

require_once dirname(__DIR__, 2) . '/helpers/planejamento_fila_operacional_helper.php';

function fila_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function fila_task(int $id, int $image, int $function, int $person, string $status = 'Não iniciado', int $priority = 3): array
{
    return [
        'tarefa_id' => $id, 'imagem_id' => $image, 'funcao_id' => $function,
        'colaborador_id' => $person, 'status' => $status, 'prioridade' => $priority,
        'tipo_imagem' => 'Imagem Interna', 'entrega_id' => 1, 'prazo_entrega' => '2026-09-30',
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

$blocked = flow_fila_projetar_etapas($plan, $plan, ['CADERNO_FILTRO' => [['chave' => 'hold', 'responsaveis' => [7]]]], ['hold' => ['pessoa_dias' => null, 'confianca' => 'INSUFICIENTE', 'bloqueada' => true]], [7 => ['disponivel_em' => '2026-08-26']], '2026-08-26');
fila_assert($blocked['CADERNO_FILTRO']['status_operacional'] === 'BLOQUEADO', 'HOLD deve bloquear a projeção, não gerar data inventada.');

echo "OK: fila derivada, Caderno/Filtro, paralelismo, dependências, esforço, margem e HOLD validados.\n";

<?php

require_once dirname(__DIR__, 2) . '/helpers/planejamento_capacidade_simulacao_helper.php';

function simulacao_assert(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function simulacao_plano(int $versao, int $obra, string $inicio, string $fim, int $pessoas = 2, int $margem = 5): array
{
    return [
        'planejamento_id' => $versao,
        'entrega_id' => 1000 + $versao,
        'estado' => 'CONFIRMADO',
        'versao_atual_id' => $versao,
        'versao_id' => $versao,
        'versao_vigente' => 1,
        'versao_numero' => 1,
        'margem_dias_uteis' => $margem,
        'status_plano' => 'VIAVEL',
        'status_id' => 2,
        'entrega_status' => 'Pendente',
        'arquivada' => 0,
        'obra_id' => $obra,
        'nomenclatura' => 'OBRA_' . $obra,
        'nome_obra' => 'Obra ' . $obra,
        'status_obra' => 0,
        'etapas' => [[
            'codigo_etapa' => 'COMPOSICAO',
            'data_inicio' => $inicio,
            'data_limite' => $fim,
            'pessoas_alocadas' => $pessoas,
            'capacidade_editavel' => 1,
            'metadados_json' => ['nao_aplicavel' => false],
        ]],
    ];
}

function simulacao_recalcular(array $plano, array $pessoas, array $deslocamentos): array
{
    $etapa = $plano['etapas'][0];
    $dias = (int) ($deslocamentos['COMPOSICAO'] ?? 0);
    $inicio = flow_planejamento_adicionar_dias_uteis($etapa['data_inicio'], $dias);
    $fim = flow_planejamento_adicionar_dias_uteis($etapa['data_limite'], $dias);
    $capacidade = (int) ($pessoas['COMPOSICAO'] ?? $etapa['pessoas_alocadas']);
    return [
        'margem_dias_uteis' => (int) $plano['margem_dias_uteis'] - $dias,
        'status_plano' => ((int) $plano['margem_dias_uteis'] - $dias) < 0 ? 'INVIAVEL' : 'VIAVEL',
        'fim_previsto' => $fim,
        'etapas' => [[
            'codigo' => 'COMPOSICAO',
            'inicio' => $inicio,
            'limite' => $fim,
            'pessoas_alocadas' => $capacidade,
            'capacidade_editavel' => true,
            'nao_aplicavel' => false,
        ]],
    ];
}

$planos = [
    simulacao_plano(1, 10, '2026-09-14', '2026-09-18', 2, 5),
    simulacao_plano(2, 11, '2026-09-14', '2026-09-18', 2, 1),
];
$configuracoes = ['COMPOSICAO' => ['capacidade_principal' => 3, 'capacidade_secundaria' => 0]];

$resolvido = flow_capacidade_simular_com_planos(
    $planos,
    $configuracoes,
    '2026-09-14',
    '2026-10-09',
    'COMPOSICAO',
    '2026-09-14',
    [['tipo' => 'DESLOCAR_ETAPA', 'entrega_id' => 1001, 'codigo_etapa' => 'COMPOSICAO', 'dias_uteis' => 5]],
    'simulacao_recalcular'
);
simulacao_assert($resolvido['classificacao'] === 'RESOLVE', 'Deslocar uma etapa deve resolver o conflito original sem criar outro.');
simulacao_assert($resolvido['comparacao']['deficit_antes'] === 1.0 && $resolvido['comparacao']['deficit_depois'] === 0.0, 'O comparativo deve expor déficit antes/depois.');
simulacao_assert($resolvido['planos_afetados'][0]['depois']['margem_dias_uteis'] === 0, 'O deslocamento deve consumir a margem da própria R00.');

$transferido = flow_capacidade_simular_com_planos(
    [
        simulacao_plano(1, 10, '2026-09-14', '2026-09-18', 2, 8),
        simulacao_plano(2, 11, '2026-09-14', '2026-09-18', 2, 8),
        simulacao_plano(3, 12, '2026-09-21', '2026-09-25', 2, 8),
    ],
    $configuracoes,
    '2026-09-14',
    '2026-10-09',
    'COMPOSICAO',
    '2026-09-14',
    [['tipo' => 'DESLOCAR_ETAPA', 'entrega_id' => 1001, 'codigo_etapa' => 'COMPOSICAO', 'dias_uteis' => 5]],
    'simulacao_recalcular'
);
simulacao_assert($transferido['classificacao'] === 'TRANSFERE_PROBLEMA' && count($transferido['novos_conflitos']) === 1, 'O sandbox deve detectar conflito indireto criado depois do deslocamento.');

$inviavel = flow_capacidade_simular_com_planos(
    $planos,
    $configuracoes,
    '2026-09-14',
    '2026-10-30',
    'COMPOSICAO',
    '2026-09-14',
    [['tipo' => 'DESLOCAR_ETAPA', 'entrega_id' => 1002, 'codigo_etapa' => 'COMPOSICAO', 'dias_uteis' => 3]],
    'simulacao_recalcular'
);
simulacao_assert($inviavel['classificacao'] === 'INVIAVEL', 'Margem negativa deve tornar o cenário inviável, mesmo que alivie o conflito.');

$apoio = flow_capacidade_simular_com_planos(
    $planos,
    ['COMPOSICAO' => ['capacidade_principal' => 3, 'capacidade_secundaria' => 1]],
    '2026-09-14',
    '2026-10-09',
    'COMPOSICAO',
    '2026-09-14',
    [['tipo' => 'APOIO_SECUNDARIO', 'codigo_etapa' => 'COMPOSICAO', 'quantidade' => 2]],
    'simulacao_recalcular'
);
simulacao_assert(!empty($apoio['excecoes']) && $apoio['excecoes'][0]['codigo'] === 'APOIO_SECUNDARIO_INSUFICIENTE', 'Apoio acima dos elegíveis deve ficar explícito no sandbox.');

$planosOriginais = $planos;
$externo = flow_capacidade_simular_com_planos(
    $planos,
    $configuracoes,
    '2026-09-14',
    '2026-10-09',
    'COMPOSICAO',
    '2026-09-14',
    [[
        'tipo' => 'CAPACIDADE_EXTERNA', 'codigo_etapa' => 'COMPOSICAO',
        'data_inicio' => '2026-09-14', 'data_fim' => '2026-09-18', 'pessoas' => 1,
        'distribuicao' => [
            ['data' => '2026-09-14', 'pessoas' => 1], ['data' => '2026-09-15', 'pessoas' => 1],
            ['data' => '2026-09-16', 'pessoas' => 1], ['data' => '2026-09-17', 'pessoas' => 1],
            ['data' => '2026-09-18', 'pessoas' => 1],
        ],
    ]],
    'simulacao_recalcular'
);
simulacao_assert($externo['classificacao'] === 'RESOLVE_COM_VALIDACAO', 'Capacidade externa deve eliminar o déficit sem alterar plano individual.');
simulacao_assert($externo['intervencoes_capacidade']['externa'][0]['pessoa_dias'] === 5 && $externo['intervencoes_capacidade']['externa'][0]['pico_pessoas'] === 1, 'Freelancer deve expor pessoa-dias e pico separadamente.');
simulacao_assert($planos === $planosOriginais, 'A simulação externa não pode alterar os planos de origem.');

$sabado = flow_capacidade_simular_com_planos(
    $planos,
    $configuracoes,
    '2026-09-14',
    '2026-10-09',
    'COMPOSICAO',
    '2026-09-14',
    [['tipo' => 'CAPACIDADE_EXTRAORDINARIA', 'codigo_etapa' => 'COMPOSICAO', 'data' => '2026-09-19', 'pessoas' => 5]],
    'simulacao_recalcular'
);
simulacao_assert($sabado['classificacao'] === 'RESOLVE_COM_VALIDACAO', 'Produção extraordinária de sábado deve gerar crédito explícito de pessoa-dia.');
$datasNormais = array_column($sabado['simulado']['global']['etapas'][0]['dias'], 'data');
simulacao_assert(!in_array('2026-09-19', $datasNormais, true), 'Sábado extraordinário não pode virar dia útil global.');

$combinado = flow_capacidade_simular_com_planos(
    $planos,
    $configuracoes,
    '2026-09-14',
    '2026-10-09',
    'COMPOSICAO',
    '2026-09-14',
    [
        ['tipo' => 'APOIO_SECUNDARIO', 'codigo_etapa' => 'COMPOSICAO', 'quantidade' => 1],
        ['tipo' => 'CAPACIDADE_EXTERNA', 'codigo_etapa' => 'COMPOSICAO', 'data_inicio' => '2026-09-14', 'data_fim' => '2026-09-18', 'pessoas' => 1],
    ],
    'simulacao_recalcular'
);
simulacao_assert(count($combinado['acoes']) === 2 && $combinado['classificacao'] === 'RESOLVE_COM_VALIDACAO', 'Combinação limitada de apoio e externo deve ser simulada em memória.');

echo "OK: sandbox global compara déficit, margem, conflitos indiretos e apoio secundário sem persistência.\n";

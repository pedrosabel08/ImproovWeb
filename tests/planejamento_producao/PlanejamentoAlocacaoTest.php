<?php

require_once dirname(__DIR__, 2) . '/helpers/planejamento_alocacao_helper.php';

function alocacao_assert(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function alocacao_tarefa(int $id, int $imagem, int $funcao, ?int $responsavel = null): array
{
    return [
        'tarefa_id' => $id,
        'imagem_id' => $imagem,
        'funcao_id' => $funcao,
        'imagem_nome' => 'Imagem ' . $imagem,
        'tipo_imagem' => 'Imagem Interna',
        'colaborador_id' => $responsavel,
        'responsavel_nome' => $responsavel ? 'Pessoa ' . $responsavel : null,
        'responsavel_ativo' => 1,
        'responsavel_elegivel_capacidade' => 1,
    ];
}

// Caderno + Filtro da mesma imagem e mesma pessoa continuam duas tarefas
// reais, mas representam apenas uma unidade de capacidade/alocação.
$cadernoMesmoResponsavel = flow_alocacao_resumo_unidades('CADERNO_FILTRO', [
    alocacao_tarefa(1, 100, 1, 10),
    alocacao_tarefa(2, 100, 8, 10),
]);
alocacao_assert($cadernoMesmoResponsavel['tarefas_reais'] === 2, 'Caderno/Filtro precisa preservar as duas tarefas reais.');
alocacao_assert($cadernoMesmoResponsavel['materializadas'] === 1 && $cadernoMesmoResponsavel['alocadas'] === 1, 'Caderno/Filtro da mesma imagem deve contar uma unidade alocada.');
alocacao_assert($cadernoMesmoResponsavel['responsabilidades_divergentes'] === 0, 'Mesmo responsável não pode gerar divergência.');

// Sem pesos confiáveis, responsáveis diferentes são informados como carga
// compartilhada e não podem ser tratados como duas unidades nem 50/50.
$cadernoDivergente = flow_alocacao_resumo_unidades('CADERNO_FILTRO', [
    alocacao_tarefa(3, 101, 1, 10),
    alocacao_tarefa(4, 101, 8, 11),
]);
alocacao_assert($cadernoDivergente['materializadas'] === 1 && $cadernoDivergente['responsabilidades_divergentes'] === 1, 'Responsáveis diferentes devem gerar RESPONSABILIDADE_DIVERGENTE em uma única unidade.');
alocacao_assert($cadernoDivergente['pessoas'][10]['unidades_compartilhadas'] === 1 && $cadernoDivergente['pessoas'][11]['unidades_compartilhadas'] === 1, 'A unidade divergente deve permanecer compartilhada sem peso individual.');

$semResponsavel = flow_alocacao_resumo_unidades('COMPOSICAO', [alocacao_tarefa(5, 102, 3)]);
alocacao_assert($semResponsavel['materializadas'] === 1 && $semResponsavel['sem_responsavel'] === 1, 'Tarefa real sem responsável deve ser contabilizada como SEM_RESPONSAVEL.');

alocacao_assert(
    flow_alocacao_status_etapa(['materializado' => 0, 'alocado' => 0, 'sem_responsavel' => 0, 'pessoas' => []], 2, 12, false, false) === FLOW_ALOCACAO_STATUS_PENDENTE_MATERIALIZACAO,
    'Demanda planejada sem tarefa real deve ser PENDENTE_MATERIALIZACAO, não SEM_RESPONSAVEL.'
);
alocacao_assert(
    flow_alocacao_status_etapa(['materializado' => 12, 'alocado' => 9, 'sem_responsavel' => 3, 'pessoas' => []], 2, 0, false, false) === 'PARCIALMENTE_ALOCADO',
    'Tarefas reais parcialmente atribuídas devem ser PARCIALMENTE_ALOCADO.'
);

// A janela de carga usa a duração persistida e ignora feriado/fim de semana;
// o limite é marco de conclusão, não um dia adicional de produção.
$dias = flow_alocacao_dias_planejados_etapa('2026-09-04', '2026-09-10', 3);
alocacao_assert($dias === ['2026-09-04', '2026-09-08', '2026-09-09'], 'Carga deve respeitar o calendário canônico e manter exatamente a duração planejada.');

$carga = flow_alocacao_carga_na_janela([
    'dias' => ['2026-09-08' => 0.6, '2026-09-09' => 1.2],
    'referencias' => ['2026-09-08' => [['obra' => 'A']], '2026-09-09' => [['obra' => 'A'], ['obra' => 'B']]],
], ['2026-09-08', '2026-09-09']);
alocacao_assert($carga['pico_carga'] === 1.2 && count($carga['conflitos']) === 1, 'Conflito de pessoa precisa ser calculado por dia útil dentro da janela da etapa.');

// Estados de capacidade são distintos: a validação não reduz o valor
// matemático e uma validação antiga não cobre um contexto alterado.
alocacao_assert(flow_alocacao_status_carga(1.0) === FLOW_ALOCACAO_STATUS_NORMAL, 'Carga exatamente em 100% deve ser NORMAL.');
alocacao_assert(flow_alocacao_status_carga(1.1667) === FLOW_ALOCACAO_STATUS_SOBRECARGA_NAO_VALIDADA, 'Carga acima de 100% deve exigir validação.');
alocacao_assert(flow_alocacao_status_carga(1.1667, true) === FLOW_ALOCACAO_STATUS_SOBRECARGA_VALIDADA, 'Sobrecarga validada deve manter o estado excepcional.');
alocacao_assert(flow_alocacao_status_carga(1.1667, false, true) === FLOW_ALOCACAO_STATUS_VALIDACAO_DESATUALIZADA, 'Contexto alterado deve invalidar a validação anterior.');

$movimentos = flow_alocacao_normalizar_movimentos([
    ['tarefa_id' => 50, 'de_colaborador_id' => 10, 'para_colaborador_id' => 11],
]);
alocacao_assert($movimentos[0]['tarefa_id'] === 50 && $movimentos[0]['para_colaborador_id'] === 11, 'Movimento deve ser normalizado com origem e destino.');
$duplicadoRejeitado = false;
try {
    flow_alocacao_normalizar_movimentos([
        ['tarefa_id' => 50, 'para_colaborador_id' => 11],
        ['tarefa_id' => 50, 'para_colaborador_id' => 12],
    ]);
} catch (InvalidArgumentException) {
    $duplicadoRejeitado = true;
}
alocacao_assert($duplicadoRejeitado, 'A mesma tarefa não pode ser simulada duas vezes.');

$etapaContexto = [
    'planejamento_id' => 1, 'versao_id' => 2, 'entrega_id' => 3, 'obra_id' => 4,
    'codigo_etapa' => 'FINALIZACAO_INTERNA', 'inicio' => '2026-09-11',
    'limite' => '2026-09-21', 'duracao_dias_uteis' => 6, 'pessoas_planejadas' => 2,
    'planejado' => 12,
];
$pessoaContexto = ['id' => 33];
$tarefasContexto = [['tarefas' => [alocacao_tarefa(99, 100, 4, 33)]]];
$fingerprintA = flow_alocacao_fingerprint_pessoa($etapaContexto, $pessoaContexto, [['data' => '2026-09-11', 'carga' => 1.0]], $tarefasContexto);
$tarefasContexto[0]['tarefas'][0]['colaborador_id'] = 6;
$fingerprintB = flow_alocacao_fingerprint_pessoa($etapaContexto, $pessoaContexto, [['data' => '2026-09-11', 'carga' => 1.0]], $tarefasContexto);
alocacao_assert($fingerprintA !== $fingerprintB, 'Fingerprint deve mudar quando o responsável real muda.');

echo "OK: materialização, deduplicação Caderno/Filtro, estados, fingerprint e carga planejada validados.\n";

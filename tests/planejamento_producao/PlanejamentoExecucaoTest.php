<?php

require_once dirname(__DIR__, 2) . '/helpers/planejamento_execucao_helper.php';

function exec_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function exec_stage(array $execution, string $code): array
{
    foreach ($execution['etapas'] as $stage) {
        if ($stage['codigo'] === $code) {
            return $stage;
        }
    }
    throw new RuntimeException('Etapa de execução não encontrada: ' . $code);
}

function exec_log(string $date, string $to): array
{
    return ['data' => $date . ' 09:00:00', 'status_novo' => $to];
}

function exec_item(int $task, int $image, int $function, string $type, string $status): array
{
    return ['tarefa_id' => $task, 'imagem_id' => $image, 'funcao_id' => $function, 'tipo_imagem' => $type, 'status' => $status, 'origem' => 'TAREFA'];
}

function exec_plan(): array
{
    $defs = [
        ['CADERNO_FILTRO', 2, 2, '2026-08-03', '2026-08-05', [], 'HISTORICO_POR_PESSOA', 1],
        ['MODELAGEM_FACHADA', 1, 7, '2026-08-03', '2026-08-12', [], 'JANELA_FIXA', 1],
        ['MODELAGEM_INTERNA', 2, 2, '2026-08-05', '2026-08-07', ['CADERNO_FILTRO'], 'HISTORICO_POR_PESSOA', 1],
        ['COMPOSICAO', 2, 2, '2026-08-07', '2026-08-11', ['MODELAGEM_INTERNA'], 'HISTORICO_POR_PESSOA', 1],
        ['FINALIZACAO_EXTERNA', 1, 1, '2026-08-11', '2026-08-12', ['COMPOSICAO'], 'OPERACIONAL_POR_TAREFA', 1],
        ['FINALIZACAO_INTERNA', 2, 2, '2026-08-11', '2026-08-13', ['COMPOSICAO'], 'OPERACIONAL_POR_TAREFA', 1],
        ['FINALIZACAO_PLANTA', 1, 1, '2026-08-11', '2026-08-12', ['COMPOSICAO'], 'OPERACIONAL_POR_TAREFA', 1],
        ['FINALIZACAO_GLOBAL', 4, 2, '2026-08-11', '2026-08-13', ['FINALIZACAO_EXTERNA', 'FINALIZACAO_INTERNA', 'FINALIZACAO_PLANTA'], null, 1],
        ['POS_PRODUCAO', 2, 1, '2026-08-13', '2026-08-14', ['FINALIZACAO_GLOBAL'], 'OPERACIONAL_POR_TAXA', 5],
    ];
    $stages = [];
    foreach ($defs as [$code, $volume, $duration, $start, $limit, $deps, $strategy, $rate]) {
        $stages[] = ['codigo' => $code, 'nome' => $code, 'volume' => $volume, 'duracao_dias_uteis' => $duration, 'inicio' => $start, 'limite' => $limit, 'dependencias' => $deps, 'estrategia_duracao' => $strategy, 'pessoas_alocadas' => 1, 'metrica' => ['tarefas_por_dia_util_pessoa' => $rate]];
    }
    return ['fonte' => 'VERSAO_CONFIRMADA', 'data_hoje' => '2026-08-10', 'data_entrega' => '2026-08-20', 'fim_previsto' => '2026-08-14', 'margem_dias_uteis' => 4, 'etapas' => $stages];
}

function exec_fixture(string $internalStatus = 'Não iniciado'): array
{
    return [
        exec_item(1, 1, 1, 'Imagem Interna', 'Finalizado'), exec_item(2, 1, 8, 'Imagem Interna', 'Finalizado'),
        exec_item(3, 2, 1, 'Imagem Interna', 'Finalizado'), exec_item(4, 2, 8, 'Imagem Interna', 'Finalizado'),
        exec_item(5, 1, 2, 'Imagem Interna', $internalStatus), exec_item(6, 2, 2, 'Imagem Interna', 'Finalizado'),
        exec_item(7, 3, 2, 'Fachada', 'Finalizado'), exec_item(8, 1, 3, 'Imagem Interna', 'Não iniciado'), exec_item(9, 2, 3, 'Imagem Interna', 'Não iniciado'),
        exec_item(10, 3, 4, 'Imagem Externa', 'Não iniciado'), exec_item(11, 1, 4, 'Imagem Interna', 'Não iniciado'), exec_item(12, 2, 4, 'Imagem Interna', 'Não iniciado'), exec_item(13, 4, 4, 'Planta Humanizada', 'Não iniciado'),
        exec_item(14, 1, 5, 'Imagem Interna', 'Não iniciado'), exec_item(15, 2, 5, 'Imagem Interna', 'Não iniciado'),
    ];
}

$logs = [
    1 => [exec_log('2026-08-03', 'Em andamento'), exec_log('2026-08-04', 'Finalizado')], 2 => [exec_log('2026-08-03', 'Em andamento'), exec_log('2026-08-04', 'Finalizado')],
    3 => [exec_log('2026-08-03', 'Em andamento'), exec_log('2026-08-05', 'Finalizado')], 4 => [exec_log('2026-08-03', 'Em andamento'), exec_log('2026-08-05', 'Finalizado')],
    5 => [exec_log('2026-08-05', 'Em andamento')], 6 => [exec_log('2026-08-05', 'Em andamento'), exec_log('2026-08-07', 'Finalizado')],
    7 => [exec_log('2026-08-03', 'Em andamento'), exec_log('2026-08-10', 'Finalizado')],
];
$execution = flow_planejamento_monitorar_execucao_com_dados(exec_plan(), exec_fixture('Em andamento'), $logs, ['data_hoje' => '2026-08-10']);
$caderno = exec_stage($execution, 'CADERNO_FILTRO');
$modelagem = exec_stage($execution, 'MODELAGEM_INTERNA');
exec_assert($caderno['volume_atual'] === 2 && $caderno['concluidas'] === 2, 'Caderno/Filtro deve usar uma unidade por imagem, sem duplicar Filtro.');
exec_assert($caderno['conclusao_real'] === '2026-08-05' && $caderno['condicao_prazo'] === 'NO_PRAZO', 'Conclusão da etapa deve ser a última conclusão real.');
exec_assert($modelagem['execucao'] === 'EM_ANDAMENTO' && $modelagem['concluidas'] === 1 && $modelagem['pendentes'] === 1, 'Progresso parcial deve vir exclusivamente do estado atual das tarefas.');
exec_assert($modelagem['inicio_real'] === '2026-08-05', 'Início real deve usar a primeira transição operacional válida.');
exec_assert($modelagem['fim_projetado'] >= '2026-08-11', 'Projeção deve respeitar hoje e dependências, sem prever o passado.');

// Reabertura mantém o fato histórico, mas derruba a conclusão corrente.
$reaberta = exec_fixture('Em andamento');
$logsReaberta = $logs;
$logsReaberta[6] = [exec_log('2026-08-05', 'Em andamento'), exec_log('2026-08-07', 'Finalizado'), exec_log('2026-08-08', 'Em andamento')];
$reaberto = flow_planejamento_monitorar_execucao_com_dados(exec_plan(), $reaberta, $logsReaberta, ['data_hoje' => '2026-08-10']);
exec_assert(exec_stage($reaberto, 'MODELAGEM_INTERNA')['execucao'] === 'EM_ANDAMENTO', 'Tarefa reaberta não pode manter etapa concluída.');

// Paralelismo: a frente interna mais longa deve definir o Global e a Pós.
$todos = exec_fixture('Finalizado');
foreach ([8, 9, 10, 11, 12, 13, 14, 15] as $id) {
    foreach ($todos as &$item) {
        if ($item['tarefa_id'] === $id) {
            $item['status'] = 'Finalizado';
        }
    }
} unset($item);
$logsTodos = $logs;
foreach ([8, 9] as $id) {
    $logsTodos[$id] = [exec_log('2026-08-08', 'Em andamento'), exec_log('2026-08-11', 'Finalizado')];
}
$logsTodos[10] = [exec_log('2026-08-08', 'Em andamento'), exec_log('2026-08-12', 'Finalizado')];
$logsTodos[11] = [exec_log('2026-08-08', 'Em andamento'), exec_log('2026-08-14', 'Finalizado')];
$logsTodos[12] = [exec_log('2026-08-08', 'Em andamento'), exec_log('2026-08-14', 'Finalizado')];
$logsTodos[13] = [exec_log('2026-08-08', 'Em andamento'), exec_log('2026-08-12', 'Finalizado')];
$logsTodos[14] = [exec_log('2026-08-14', 'Em andamento'), exec_log('2026-08-15', 'Finalizado')];
$logsTodos[15] = [exec_log('2026-08-14', 'Em andamento'), exec_log('2026-08-15', 'Finalizado')];
$concluido = flow_planejamento_monitorar_execucao_com_dados(exec_plan(), $todos, $logsTodos, ['data_hoje' => '2026-08-15']);
exec_assert(exec_stage($concluido, 'FINALIZACAO_GLOBAL')['conclusao_real'] === '2026-08-14', 'Global deve concluir no maior término entre os pools.');
exec_assert($concluido['fim_projetado'] === '2026-08-15', 'Pós deve partir do término global, não somar os pools.');
exec_assert($concluido['margem_projetada_dias_uteis'] === 4, 'Margem projetada precisa usar fim projetado e entrega, não soma de atrasos.');

// Condições de prazo e saúde: atraso local sem consumir toda margem é atenção; déficit é risco.
exec_assert(flow_execucao_condicao_prazo('CONCLUIDA', '2026-08-10', '2026-08-09', '2026-08-10') === 'ADIANTADA', 'Conclusão anterior ao marco precisa ser adiantada.');
exec_assert(flow_execucao_condicao_prazo('EM_ANDAMENTO', '2026-08-10', null, '2026-08-10') === 'LIMITE_HOJE', 'Etapa pendente no marco deve ser limite hoje.');
exec_assert(flow_execucao_condicao_prazo('EM_ANDAMENTO', '2026-08-09', null, '2026-08-10') === 'ATRASADA', 'Etapa pendente após o marco deve ser atrasada.');
exec_assert($concluido['saude'] === 'CONCLUIDA', 'Todas as tarefas concluídas devem fechar a saúde da execução.');

echo "OK: execução, reabertura, paralelismo, projeção e margem validados.\n";

<?php

require_once dirname(__DIR__, 2) . '/helpers/unidade_trabalho_helper.php';

function wip_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

wip_assert(flow_wip_status_ativo('Em andamento'), 'Em andamento deve consumir WIP.');
wip_assert(flow_wip_status_ativo('Ajuste'), 'Ajuste deve consumir WIP.');
wip_assert(flow_wip_status_bloqueia_inicio_ajuste('Em andamento'), 'Em andamento deve bloquear o início de um ajuste.');
wip_assert(!flow_wip_status_bloqueia_inicio_ajuste('Ajuste'), 'Outro Ajuste não pode bloquear o início de um ajuste.');
wip_assert(flow_wip_statuses_ativos() === ['Em andamento', 'Ajuste'], 'O resumo geral de WIP deve continuar incluindo Ajuste.');
wip_assert(flow_wip_statuses_ativos(false) === ['Em andamento'], 'A retomada de Ajuste deve consultar apenas tarefas Em andamento.');
wip_assert(!flow_wip_status_ativo('Em aprovação'), 'Em aprovação não deve consumir WIP.');
wip_assert(!flow_wip_status_ativo('HOLD'), 'HOLD não deve consumir WIP.');
wip_assert(!flow_wip_status_ativo('Finalizado'), 'Finalizado não deve consumir WIP.');

wip_assert(flow_unidade_consumo_wip([['status' => 'Em andamento']]) === 1, 'Uma tarefa ativa deve contar como uma unidade.');
wip_assert(flow_unidade_consumo_wip([['status' => 'Em andamento'], ['status' => 'Em andamento']]) === 1, 'Dois membros ativos agrupados devem contar como uma unidade.');
wip_assert(flow_unidade_consumo_wip([['status' => 'Finalizado'], ['status' => 'Em andamento']]) === 1, 'Unidade divergente com membro em andamento deve consumir WIP.');
wip_assert(flow_unidade_consumo_wip([['status' => 'Finalizado'], ['status' => 'Em aprovação']]) === 0, 'Unidade sem ação produtiva não deve consumir WIP.');
wip_assert(flow_unidade_consumo_wip([['status' => 'Finalizado'], ['status' => 'Ajuste']]) === 1, 'Retorno de membro para Ajuste deve reativar o WIP.');

$active = [
    ['key' => 'FUNCAO_IMAGEM:10'],
    ['key' => 'FUNCAO_IMAGEM:20'],
];
wip_assert(count(flow_wip_unidades_bloqueantes([], null)) === 0, 'Sem trabalho ativo deve permitir nova puxada.');
wip_assert(count(flow_wip_unidades_bloqueantes($active, null)) === 2, 'Trabalho ativo deve bloquear nova puxada.');
wip_assert(count(flow_wip_unidades_bloqueantes($active, 'FUNCAO_IMAGEM:10')) === 1, 'A própria unidade não deve bloquear continuação interna.');

echo "UnidadeTrabalhoTest: OK\n";

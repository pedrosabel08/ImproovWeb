<?php

require_once dirname(__DIR__, 2) . '/helpers/planejamento_fila_confirmada_helper.php';

function fila_confirmada_assert(bool $condicao, string $mensagem): void
{
    if (!$condicao) throw new RuntimeException($mensagem);
}

$ordem = flow_fila_confirmada_normalizar_ordem([
    ['entrega_id' => 10, 'codigo_etapa' => 'CADERNO_FILTRO'],
    ['entrega_id' => 10, 'codigo_etapa' => 'caderno_filtro'],
    ['entrega_id' => 11, 'codigo_etapa' => 'CADERNO_FILTRO'],
    ['entrega_id' => -1, 'codigo_etapa' => 'INVALIDA'],
]);
fila_confirmada_assert(count($ordem) === 2, 'A ordem deve deduplicar blocos e ignorar entradas inválidas.');
fila_confirmada_assert(
    flow_fila_chave_operacional(['entrega_id' => 0, 'codigo_etapa' => 'CADERNO_FILTRO', 'representante' => ['obra_id' => 100]]) === 'OBRA:100:CADERNO_FILTRO',
    'Trabalho sem entrega deve manter uma chave própria por obra na fila.'
);

$linha = [[
    'chave' => '293:CADERNO_FILTRO', 'prioridade' => 3,
    'tarefas_contexto' => [['id' => 100, 'funcao_id' => 1, 'colaborador_id' => 36, 'status' => 'Pendente']],
]];
$fingerprint = flow_fila_confirmada_fingerprint_linha($linha);
$linhaConclusao = $linha;
$linhaConclusao[0]['tarefas_contexto'][0]['status'] = 'Concluída';
fila_confirmada_assert($fingerprint === flow_fila_confirmada_fingerprint_linha($linhaConclusao), 'Conclusão antecipada atualiza projeção, mas não deve invalidar a ordem confirmada.');
$linhaResponsavel = $linha;
$linhaResponsavel[0]['tarefas_contexto'][0]['colaborador_id'] = 99;
fila_confirmada_assert($fingerprint !== flow_fila_confirmada_fingerprint_linha($linhaResponsavel), 'Troca de responsável deve invalidar a ordem confirmada.');

$antes = [[
    'entrega_id' => 293, 'fim_operacional_projetado' => '2026-10-20', 'margem_operacional_dias_uteis' => -2, 'status_operacional' => 'ATRASO_PROJETADO',
], [
    'entrega_id' => 294, 'fim_operacional_projetado' => '2026-09-10', 'margem_operacional_dias_uteis' => 3, 'status_operacional' => 'NO_PLANO',
]];
$resolve = [[
    'entrega_id' => 293, 'fim_operacional_projetado' => '2026-10-16', 'margem_operacional_dias_uteis' => 0, 'status_operacional' => 'NO_PLANO',
], [
    'entrega_id' => 294, 'fim_operacional_projetado' => '2026-09-11', 'margem_operacional_dias_uteis' => 2, 'status_operacional' => 'NO_PLANO',
]];
fila_confirmada_assert(flow_fila_confirmada_classificar($antes, $resolve, 293) === 'RESOLVE', 'Cenário que elimina o atraso sem criar outro deve ser RESOLVE.');
$transfere = $resolve;
$transfere[1]['margem_operacional_dias_uteis'] = -1;
$transfere[1]['status_operacional'] = 'ATRASO_PROJETADO';
fila_confirmada_assert(flow_fila_confirmada_classificar($antes, $transfere, 293) === 'TRANSFERE_PROBLEMA', 'Novo atraso em outra entrega deve ser TRANSFERE_PROBLEMA.');
$semGanho = $antes;
fila_confirmada_assert(flow_fila_confirmada_classificar($antes, $semGanho, 293) === 'SEM_GANHO', 'Fim inalterado deve ser SEM_GANHO.');

echo "OK: ordem confirmada, fingerprint contextual e classificação de cenários validados.\n";

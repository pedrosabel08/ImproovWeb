<?php

require_once dirname(__DIR__, 2) . '/helpers/planejamento_entrega_real_helper.php';

function entrega_real_assert(bool $condicao, string $mensagem): void
{
    if (!$condicao) throw new RuntimeException($mensagem);
}

$entrega = ['status_id' => 2, 'status' => 'Parcial', 'data_conclusao' => '2026-10-01', 'data_prevista' => '2026-10-16'];
$parcial = flow_planejamento_entrega_real_com_dados($entrega, [
    ['data_entregue' => '2026-10-01 10:00:00'],
    ['data_entregue' => null],
]);
entrega_real_assert($parcial['estado'] === 'EM_ANDAMENTO', 'Uma entrega parcial com data_conclusao não pode encerrar o plano.');
$semEntrega = flow_planejamento_entrega_real_com_dados($entrega, []);
entrega_real_assert($semEntrega['entrega_realizada_em'] === null, 'Ausência de eventos de entrega não pode ser substituída por progresso de funções.');
$concluida = flow_planejamento_entrega_real_com_dados($entrega, [
    ['data_entregue' => '2026-10-01 10:00:00'],
    ['data_entregue' => '2026-10-09 16:42:11'],
]);
entrega_real_assert($concluida['entrega_realizada_em'] === '2026-10-09 16:42:11', 'O último registro real dos itens deve fechar a entrega, preservando a hora.');
entrega_real_assert($concluida['fonte'] === 'ENTREGAS_ITENS', 'A origem do evento real deve ser identificada.');
$resultado = flow_planejamento_comparar_entrega_real($concluida, ['fim_previsto' => '2026-09-28', 'data_entrega' => '2026-10-16']);
entrega_real_assert($resultado['desvio_plano_dias_uteis'] === 9, 'Entrega após o plano deve ter desvio positivo de nove dias úteis.');
entrega_real_assert($resultado['antecedencia_prazo_dias_corridos'] === 7, 'A mesma entrega pode cumprir o prazo do cliente com sete dias corridos de antecedência.');
entrega_real_assert($resultado['antecedencia_prazo_dias_uteis'] > 0, 'Resultado contra o prazo é independente do desvio contra o plano.');
$bloqueado = false;
try {
    flow_planejamento_exigir_entrega_aberta($concluida);
} catch (RuntimeException $e) {
    $bloqueado = true;
}
entrega_real_assert($bloqueado, 'R00 entregue deve bloquear confirmação/replanejamento no servidor.');
flow_planejamento_exigir_entrega_aberta($parcial);
$entrega['status'] = 'Entrega antecipada';
$entrega['data_conclusao'] = '2026-10-09';
$legado = flow_planejamento_entrega_real_com_dados($entrega, []);
entrega_real_assert($legado['estado'] === 'CONCLUIDO' && $legado['fonte'] === 'ENTREGAS_STATUS_FINAL', 'Status final com timestamp real permite fallback legado.');
$entrega['data_conclusao'] = null;
entrega_real_assert(flow_planejamento_entrega_real_com_dados($entrega, [])['estado'] === 'EM_ANDAMENTO', 'Status final sem data não pode inventar uma conclusão.');
$entrega['status_id'] = 3;
entrega_real_assert(flow_planejamento_entrega_real_com_dados($entrega, [['data_entregue' => '2026-10-09']])['estado'] === 'EM_ANDAMENTO', 'Entrega de outra revisão não deve encerrar uma R00.');
echo "OK: entrega parcial, evento real, fallback legado, comparação de prazos e bloqueio de replanejamento validados.\n";

<?php

putenv('FLOW_REVIEW_ESPERA_SEVERA_HORAS=48');
putenv('FLOW_REVIEW_ESPERA_ATENCAO_HORAS=24');
require_once __DIR__ . '/../helpers/flow_review_prioridade_helper.php';

function check_priority(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$base = ['aprovacao_unico_bloqueio' => false, 'proxima_funcao_nome' => null];

// A: 50h sem sucessora recebe precedência severa.
$a = flow_review_prioridade_classificar($base + ['tempo_espera_minutos' => 50 * 60, 'sla_horas' => 24]);
check_priority($a['prioridade_nivel'] === 0 && $a['prioridade_tipo'] === 'ESPERA_SEVERA' && $a['espera_severa'], 'Caso A');

// B: nível severo prevalece, mas mantém desbloqueio e SLA nos motivos.
$b = flow_review_prioridade_classificar(array_merge($base, [
    'tempo_espera_minutos' => 50 * 60,
    'sla_horas' => 24,
    'aprovacao_unico_bloqueio' => true,
    'proxima_funcao_nome' => 'Finalização',
]));
check_priority($b['prioridade_nivel'] === 0 && $b['prioridade_tipo'] === 'ESPERA_SEVERA', 'Caso B nível');
check_priority(count($b['motivos_prioridade']) === 3 && str_contains($b['motivos_prioridade'][1], 'Libera Finalização') && str_contains($b['motivos_prioridade'][2], 'SLA'), 'Caso B motivos');

// C: aging não vence a classificação de SLA.
$c = flow_review_prioridade_classificar($base + ['tempo_espera_minutos' => 30 * 60, 'sla_horas' => 8]);
check_priority($c['prioridade_nivel'] === 2 && $c['prioridade_tipo'] === 'SLA_EXCEDIDO', 'Caso C');

// D: aging classifica tarefas sem SLA.
$d = flow_review_prioridade_classificar($base + ['tempo_espera_minutos' => 30 * 60, 'sla_horas' => null]);
check_priority($d['prioridade_nivel'] === 2 && $d['prioridade_tipo'] === 'ESPERA_PROLONGADA' && !$d['sla_excedido'], 'Caso D');

// E: destravamento confirmado vence aging de 24h, abaixo do limite severo.
$e = flow_review_prioridade_classificar(array_merge($base, [
    'tempo_espera_minutos' => 18 * 60,
    'sla_horas' => 24,
    'aprovacao_unico_bloqueio' => true,
    'proxima_funcao_nome' => 'Finalização',
]));
check_priority($e['prioridade_nivel'] === 1 && $e['prioridade_tipo'] === 'DESBLOQUEIA_TRABALHO', 'Caso E');

// F: SLA excedido sem sucessora fica no nível de atenção.
$f = flow_review_prioridade_classificar($base + ['tempo_espera_minutos' => 18 * 60, 'sla_horas' => 4]);
check_priority($f['prioridade_nivel'] === 2 && $f['prioridade_tipo'] === 'SLA_EXCEDIDO', 'Caso F');

// G: tarefa recente, dentro do SLA, permanece normal.
$g = flow_review_prioridade_classificar($base + ['tempo_espera_minutos' => 2 * 60, 'sla_horas' => 4]);
check_priority($g['prioridade_nivel'] === 3 && $g['prioridade_tipo'] === 'NORMAL', 'Caso G');

$approval = ['codigo' => 'APROVACAO_ETAPA_ANTERIOR', 'origem_id' => 101, 'bloqueia_inicio' => true];
$render = ['codigo' => 'render_aprovado', 'bloqueia_inicio' => true];
$candidate = ['idfuncao_imagem' => 202, 'status' => 'Não iniciado', 'imagem_status_id' => 1, 'imagem_substatus_id' => 4];

// H: predecessor é requisito, mas outro bloqueio impede classificação como destravamento.
$h = flow_review_prioridade_vinculos($candidate, ['requisitos_avaliados' => [$approval, $render]], [101 => 0]);
check_priority(isset($h[101]) && !$h[101]['only_blocker'] && $h[101]['pending_count'] === 2, 'Caso H');

// I: sucessora ativa não é destravamento e a integração assinala estado inconsistente.
$active = $candidate;
$active['status'] = 'Em andamento';
$i = flow_review_prioridade_vinculos($active, ['requisitos_avaliados' => [$approval]], [101 => 0]);
$inconsistentStatus = flow_review_prioridade_estado_sucessora_inconsistente(array_values($i));
check_priority(isset($i[101]) && !$i[101]['only_blocker'] && $inconsistentStatus === 'Em andamento', 'Caso I em andamento');
$active['status'] = 'Em aprovação';
$iApproval = flow_review_prioridade_vinculos($active, ['requisitos_avaliados' => [$approval]], [101 => 0]);
check_priority(flow_review_prioridade_estado_sucessora_inconsistente(array_values($iApproval)) === 'Em aprovação', 'Caso I em aprovação');

// Desempates: severa, destravamento, atenção, normal; regras lexicográficas por nível.
$fila = flow_review_prioridade_fila_recomendada([
    ['idfuncao_imagem' => 40, 'status' => 'Em aprovação', 'prioridade_nivel' => 1, 'tempo_espera_minutos' => 18 * 60, 'tempo_acima_sla_minutos' => 14 * 60],
    ['idfuncao_imagem' => 20, 'status' => 'Em aprovação', 'prioridade_nivel' => 2, 'sla_excedido' => true, 'tempo_acima_sla_minutos' => 6 * 60, 'tempo_espera_minutos' => 30 * 60],
    ['idfuncao_imagem' => 10, 'status' => 'Em aprovação', 'prioridade_nivel' => 0, 'tempo_espera_minutos' => 50 * 60, 'aprovacao_unico_bloqueio' => false, 'tempo_acima_sla_minutos' => 0],
    ['idfuncao_imagem' => 11, 'status' => 'Em aprovação', 'prioridade_nivel' => 0, 'tempo_espera_minutos' => 50 * 60, 'aprovacao_unico_bloqueio' => true, 'tempo_acima_sla_minutos' => 0],
    ['idfuncao_imagem' => 30, 'status' => 'Em aprovação', 'prioridade_nivel' => 1, 'tempo_espera_minutos' => 20 * 60, 'tempo_acima_sla_minutos' => 10 * 60],
    ['idfuncao_imagem' => 21, 'status' => 'Em aprovação', 'prioridade_nivel' => 2, 'sla_excedido' => false, 'tempo_acima_sla_minutos' => 0, 'tempo_espera_minutos' => 30 * 60],
    ['idfuncao_imagem' => 31, 'status' => 'Em aprovação', 'prioridade_nivel' => 1, 'tempo_espera_minutos' => 20 * 60, 'tempo_acima_sla_minutos' => 10 * 60],
    ['idfuncao_imagem' => 50, 'status' => 'Em aprovação', 'prioridade_nivel' => 3, 'tempo_espera_minutos' => 2 * 60],
    ['idfuncao_imagem' => 60, 'status' => 'Ajuste', 'prioridade_nivel' => 0, 'tempo_espera_minutos' => 100 * 60],
]);
check_priority(array_column($fila, 'idfuncao_imagem') === [11, 10, 30, 31, 40, 20, 21, 50], 'Desempates e escopo');
$exampleOrder = flow_review_prioridade_fila_recomendada([
    ['idfuncao_imagem' => 70, 'status' => 'Em aprovação', 'prioridade_nivel' => 2, 'tempo_espera_minutos' => 27 * 60, 'sla_excedido' => false],
    ['idfuncao_imagem' => 71, 'status' => 'Em aprovação', 'prioridade_nivel' => 1, 'tempo_espera_minutos' => 18 * 60, 'aprovacao_unico_bloqueio' => true],
]);
check_priority(array_column($exampleOrder, 'idfuncao_imagem') === [71, 70], 'Unlock precede aging entre 24h e 48h');
check_priority(!flow_review_prioridade_elegivel(['idfuncao_imagem' => 5, 'status' => 'Ajuste']), 'Ajuste fora da fila');
check_priority(!flow_review_prioridade_elegivel(['idfuncao_imagem' => 5, 'status' => 'Em aprovação', 'tipo_tarefa' => 'animacao']), 'Animação fora da V1');

echo "Flow Review prioridade: OK\n";

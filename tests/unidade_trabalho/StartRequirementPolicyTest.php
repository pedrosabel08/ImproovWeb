<?php

$root = dirname(__DIR__, 2);

function start_requirement_policy_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$helper = file_get_contents($root . '/helpers/inicio_operacional_helper.php');
start_requirement_policy_assert($helper !== false, 'Não foi possível ler o helper de início operacional.');

$start = strpos($helper, 'function flow_inicio_operacional_validar_requisitos');
$end = strpos($helper, '/**', $start);
$validator = $start !== false && $end !== false ? substr($helper, $start, $end - $start) : '';

start_requirement_policy_assert(
    str_contains($validator, 'motor_requisitos_tem_bloqueio_producao'),
    'O início deve continuar bloqueando pendências de Produção.'
);
start_requirement_policy_assert(
    !str_contains($validator, "['elegivel']") && !str_contains($validator, 'naoConfirmavel'),
    'Requisitos não produtivos não devem impedir o início da tarefa.'
);

$kanban = file_get_contents($root . '/PaginaPrincipal/scriptIndex.js');
start_requirement_policy_assert($kanban !== false, 'Não foi possível ler o script do Kanban.');
start_requirement_policy_assert(
    !str_contains($kanban, 'title: "Pendências ativas"'),
    'O Kanban não deve pedir confirmação para pendências não produtivas.'
);

echo "StartRequirementPolicyTest: OK\n";

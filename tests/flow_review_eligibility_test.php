<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/flow_review_eligibility_helper.php';
require_once __DIR__ . '/../helpers/flow_block_helper.php';

function expect_flow_review(bool $condition, string $scenario): void
{
    if (!$condition) {
        throw new RuntimeException("Falhou: {$scenario}");
    }
}

$approvalOpen = [['tipo_codigo' => 'APROVACAO_PENDENTE', 'status' => 'ABERTA']];
$photoMissingOpen = [['tipo_codigo' => 'FOTOGRAFICO_FALTANTE', 'status' => 'ABERTA']];
$approvalResolved = [['tipo_codigo' => 'APROVACAO_PENDENTE', 'status' => 'RESOLVIDA']];

expect_flow_review(!flow_review_is_eligible(false, 'HOLD', $photoMissingOpen), '1: outro Flow Block aberto não entra');
expect_flow_review(flow_review_is_eligible(false, 'HOLD', $approvalOpen), '2: aprovação pendente aberta entra');
expect_flow_review(!flow_review_is_eligible(false, 'HOLD', $approvalResolved), '3: aprovação pendente resolvida não entra');
expect_flow_review(!flow_review_is_eligible(false, 'HOLD', [
    ['tipo_codigo' => 'APROVACAO_PENDENTE', 'status' => 'RESOLVIDA'],
    ['tipo_codigo' => 'DUVIDA_TECNICA', 'status' => 'ABERTA'],
]), '4: aprovação antiga resolvida não entra');
expect_flow_review(flow_review_is_eligible(false, 'HOLD', [
    ...$approvalOpen,
    ['tipo_codigo' => 'DUVIDA_TECNICA', 'status' => 'ABERTA'],
]), '5: ao menos uma aprovação aberta entra');
expect_flow_review(flow_review_is_eligible(true, 'Em aprovação'), '6: elegibilidade normal permanece');
expect_flow_review(!flow_review_is_eligible(false, 'HOLD'), '7: HOLD sem bloqueio não entra');

$sql = flow_review_hold_approval_block_sql('fi');
expect_flow_review(str_contains($sql, 'EXISTS ('), '5: regra SQL usa EXISTS e não multiplica tarefas');
expect_flow_review(str_contains($sql, "fr_type.codigo = 'APROVACAO_PENDENTE'"), 'tipo é identificado por código estrutural');
expect_flow_review(str_contains($sql, "'ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA'"), 'somente Flow Blocks ativos são elegíveis');

// Uma decisão de Review conclui a pendência de aprovação da própria tarefa.
expect_flow_review(flow_block_review_decision_resolves_pending_approval('Aprovado'), 'R00: aprovado encerra aprovação pendente');
expect_flow_review(flow_block_review_decision_resolves_pending_approval('Aprovado com ajustes'), 'R00: aprovado com ajustes encerra aprovação pendente');
expect_flow_review(flow_block_review_decision_resolves_pending_approval('Ajuste'), 'R00/P00: ajuste encerra aprovação pendente');
expect_flow_review(flow_block_review_decision_resolves_pending_approval('Ângulo definitivo alterado'), 'P00: troca de ângulo encerra aprovação pendente');
expect_flow_review(!flow_block_review_decision_resolves_pending_approval('Aguardando Direção'), 'aprovação intermediária não encerra a pendência');

echo "OK: cenários de elegibilidade do Flow Review validados.\n";

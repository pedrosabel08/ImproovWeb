<?php

declare(strict_types=1);

namespace FlowConnect\Application;

use FlowConnect\Contracts\EventEnvelope;

final class OperationalPendingEventFactory
{
    public static function lifecycle(string $moduleKey, string $action, string $entityType, string|int $entityId, array $payload, string $policyKey, ?int $actorId = null): array
    {
        $action = strtolower($action);
        $type = $moduleKey === 'flow_block'
            ? 'flow_block.bloqueio.' . ['criada' => 'registrado', 'resolvida' => 'resolvido', 'cancelada' => 'cancelado'][$action]
            : $moduleKey . '.pendencia.' . $action;
        $cycleId = (string) ($payload['cycle_id'] ?? $entityId);
        return EventEnvelope::normalize([
            'event_type' => $type, 'source_module' => $moduleKey, 'entity_type' => $entityType, 'entity_id' => (string) $entityId,
            'actor_id' => $actorId,
            'idempotency_key' => "operacional:pendencia:{$moduleKey}:{$entityType}:{$entityId}:policy:{$policyKey}:cycle:{$cycleId}:action:{$action}",
            'payload' => $payload + ['module_key' => $moduleKey, 'cycle_id' => $cycleId],
            'metadata' => ['policy_key' => $policyKey, 'producer' => 'operational_pending'],
        ]);
    }

    public static function milestone(array $cycle, string $milestone, array $context): array
    {
        $module = (string) $cycle['module_key']; $entityType = (string) $cycle['entity_type']; $entityId = (string) $cycle['entity_id'];
        $policy = (string) $cycle['policy_key']; $cycleId = (string) $cycle['cycle_id'];
        return EventEnvelope::normalize([
            'event_type' => 'operacional.pendencia.sla_marco_atingido', 'source_module' => $module, 'entity_type' => $entityType, 'entity_id' => $entityId,
            'idempotency_key' => "operacional:pendencia:{$module}:{$entityType}:{$entityId}:policy:{$policy}:cycle:{$cycleId}:milestone:{$milestone}",
            'payload' => $context + ['module_key' => $module, 'cycle_id' => $cycleId, 'milestone' => $milestone],
            'metadata' => ['policy_key' => $policy, 'producer' => 'operational_scheduler'],
        ]);
    }
}

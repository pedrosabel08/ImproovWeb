<?php

declare(strict_types=1);

namespace FlowConnect\Application;

final class RecipientResolver
{
    public function __construct(private array $config) {}

    public function resolve(string $strategy, array $event): array
    {
        $payload = $event['payload'] ?? [];
        $ids = [];
        $kind = 'DM';
        switch ($strategy) {
            case 'mentioned_user':
                $ids = [(int) ($payload['mencionado_id'] ?? 0)];
                break;
            case 'task_responsible':
                $ids = [(int) ($payload['colaborador_responsavel_id'] ?? 0)];
                break;
            case 'animation_responsible':
                $ids = [(int) ($payload['colaborador_responsavel_id'] ?? 0)];
                break;
            case 'direction_group':
                $kind = 'GROUP';
                $ids = $this->config['flow_review']['roles']['direction_group'] ?? [];
                break;
            case 'flow_review_managers':
                $kind = 'GROUP';
                $ids = $this->config['flow_review']['roles']['flow_review_managers'] ?? [];
                break;
            case 'technical_admins':
                $kind = 'ADMIN';
                $ids = $this->config['flow_review']['roles']['technical_admins'] ?? [];
                break;
            case 'legacy_payload_destination':
                $recipientId = (int) ($payload['recipient_collaborator_id'] ?? 0);
                if ($recipientId > 0) {
                    return [['destination_kind' => 'DM', 'external_id' => null, 'collaborator_id' => $recipientId]];
                }
                $webhookEnv = trim((string) ($payload['webhook_env'] ?? ''));
                return $webhookEnv === '' ? [] : [['destination_kind' => 'WEBHOOK', 'external_id' => $webhookEnv, 'collaborator_id' => null]];
            case 'review_channel':
                $channel = trim((string) ($this->config['flow_review']['review_channel_id'] ?? ''));
                return $channel === '' ? [] : [['destination_kind' => 'CHANNEL', 'external_id' => $channel, 'collaborator_id' => null]];
            case 'operational_pending_audience':
                $ids = [
                    (int) ($payload['responsavel_id'] ?? 0),
                    (int) ($payload['responsavel_cobranca_id'] ?? 0),
                ];
                $moduleKey = (string) ($payload['module_key'] ?? '');
                $ids = array_merge($ids, $this->config['operational']['manager_roles'][$moduleKey] ?? []);
                break;
            case 'sla_overdue_webhook':
                $envKey = (string) ($this->config['operational']['overdue_webhook_env'] ?? '');
                return $envKey === '' ? [] : [['destination_kind' => 'WEBHOOK', 'external_id' => $envKey, 'collaborator_id' => null]];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        return array_map(static fn(int $id): array => ['destination_kind' => $kind, 'external_id' => null, 'collaborator_id' => $id], $ids);
    }

    /** Centraliza destinatarios naturais e gestores do Flow Review. */
    public function resolveForEvent(string $naturalStrategy, array $event): array
    {
        $recipients = $this->resolve($naturalStrategy, $event);
        $eventType = (string) ($event['event_type'] ?? '');
        if (str_starts_with($eventType, 'review.') && $eventType !== 'review.mencao.criada') {
            $recipients = array_merge($recipients, $this->resolve('flow_review_managers', $event));
        }

        $unique = [];
        foreach ($recipients as $recipient) {
            $collaboratorId = (int) ($recipient['collaborator_id'] ?? 0);
            $externalId = trim((string) ($recipient['external_id'] ?? ''));
            $key = $collaboratorId > 0 ? 'collaborator:' . $collaboratorId : 'external:' . $externalId;
            if ($key !== 'external:' && !isset($unique[$key])) $unique[$key] = $recipient;
        }
        return array_values($unique);
    }
}

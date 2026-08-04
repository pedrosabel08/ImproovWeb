<?php

declare(strict_types=1);

namespace FlowConnect\Application;

use FlowConnect\Infrastructure\DeadLetterRepository;
use FlowConnect\Infrastructure\DeliveryRepository;
use FlowConnect\Infrastructure\NotificationRepository;
use FlowConnect\Infrastructure\SlackIdentityRepository;
use mysqli;
use RuntimeException;

final class EventPlanner
{
    private array $definitions;
    private NotificationRepository $notifications;
    private DeliveryRepository $deliveries;
    private SlackIdentityRepository $identities;
    private DeadLetterRepository $deadLetters;
    private RecipientResolver $recipients;
    private TemplateRenderer $templates;

    public function __construct(private mysqli $conn, private array $config)
    {
        $this->definitions = array_merge(
            require dirname(__DIR__) . '/config/events/flow_review.php',
            require dirname(__DIR__) . '/config/events/immediate_legacy.php',
            require dirname(__DIR__) . '/config/events/operational_pending.php',
            require dirname(__DIR__) . '/config/events/pending_summary.php'
        );
        $this->notifications = new NotificationRepository($conn);
        $this->deliveries = new DeliveryRepository($conn, (int) ($config['claim_ttl_seconds'] ?? 300));
        $this->identities = new SlackIdentityRepository($conn);
        $this->deadLetters = new DeadLetterRepository($conn);
        $this->recipients = new RecipientResolver($config);
        $this->templates = new TemplateRenderer();
    }

    public function plan(array $event): array
    {
        $definition = $this->definitions[$event['event_type']] ?? null;
        if ($definition === null) throw new RuntimeException('flow_connect_event_definition_missing');
        $producerMode = strtolower((string) ($event['metadata']['flow_connect_mode'] ?? 'shadow'));
        $strategy = (string) $definition['recipient_strategy'];
        if (($event['payload']['tipo_fluxo'] ?? null) === 'animacao' && str_starts_with((string) $event['event_type'], 'review.tarefa.')) $strategy = 'animation_responsible';
        $configuredMode = (string) $definition['delivery_mode'];
        $deliveryMode = $producerMode === 'shadow' && !in_array($configuredMode, ['HISTORY_ONLY', 'SUPPRESSED'], true) ? 'SHADOW' : $configuredMode;

        $this->conn->begin_transaction();
        try {
            $primary = $this->createNotification($event, $definition, $deliveryMode, (string) $definition['template'], $strategy, '');
            $notificationIds = [$primary['notification_id']];
            $deliveryIds = $primary['delivery_ids'];
            $milestone = (string) ($event['payload']['milestone'] ?? '');
            if (in_array($milestone, $definition['overdue_webhook_milestones'] ?? [], true)) {
                $secondary = $this->createNotification($event, $definition, $deliveryMode, (string) $definition['overdue_webhook_template'], 'sla_overdue_webhook', ':overdue-webhook');
                $notificationIds[] = $secondary['notification_id'];
                $deliveryIds = array_merge($deliveryIds, $secondary['delivery_ids']);
            }
            $this->applyScheduleEffects($event);
            $this->conn->commit();
            return ['notification_id' => $primary['notification_id'], 'notification_ids' => $notificationIds, 'delivery_ids' => $deliveryIds, 'delivery_mode' => $deliveryMode, 'template' => $definition['template']];
        } catch (\Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    private function createNotification(array $event, array $definition, string $deliveryMode, string $template, string $strategy, string $suffix): array
    {
        $rendered = $this->templates->render($template, $event);
        $notificationId = $this->notifications->create([
            'event_id' => (int) $event['id'], 'notification_key' => $event['event_uuid'] . ':' . $template . ':v1' . $suffix,
            'severity' => $definition['severity'], 'category' => $definition['category'], 'delivery_mode' => $deliveryMode,
            'template_code' => $template, 'recipient_strategy' => $strategy,
            'payload' => ['event_type' => $event['event_type'], 'entity_type' => $event['entity_type'], 'entity_id' => $event['entity_id']],
        ]);
        $deliveryIds = [];
        if (in_array($deliveryMode, ['HISTORY_ONLY', 'SUPPRESSED'], true)) return ['notification_id' => $notificationId, 'delivery_ids' => $deliveryIds];
        $recipients = $strategy === 'sla_overdue_webhook' ? $this->recipients->resolve($strategy, $event) : $this->recipients->resolveForEvent($strategy, $event);
        if ($recipients === []) $this->deadLetters->record((int) $event['id'], $notificationId, null, 'recipient_not_resolved', ['strategy' => $strategy]);
        foreach ($recipients as $recipient) {
            $collaboratorId = $recipient['collaborator_id'] ?? null;
            $kind = (string) $recipient['destination_kind'];
            $externalId = $recipient['external_id'] ?? null;
            $slackUserId = $externalId;
            if ($collaboratorId) $slackUserId = ($this->identities->findActiveByCollaborator((int) $collaboratorId)['slack_user_id'] ?? null);
            $destinationKey = $collaboratorId ? 'slack:collaborator:' . (int) $collaboratorId : ($kind === 'WEBHOOK' ? (string) $externalId : 'slack:channel:' . (string) $slackUserId);
            $resolved = $kind === 'WEBHOOK' ? trim((string) getenv((string) $externalId)) !== '' : (bool) $slackUserId;
            $deliveryId = $this->deliveries->create([
                'notification_id' => $notificationId, 'destination_kind' => $kind, 'destination_key' => $destinationKey,
                'collaborator_id' => $collaboratorId, 'slack_user_id' => $kind === 'WEBHOOK' ? null : $slackUserId,
                'rendered_text' => $rendered['text'], 'rendered_blocks' => $rendered['blocks'], 'status' => $resolved ? 'PENDING' : 'UNRESOLVED',
            ]);
            $deliveryIds[] = $deliveryId;
            if (!$resolved) $this->deadLetters->record((int) $event['id'], $notificationId, $deliveryId,
                $kind === 'WEBHOOK' ? 'webhook_destination_unresolved' : 'slack_identity_unresolved',
                $kind === 'WEBHOOK' ? ['destination' => 'sla_overdue_webhook'] : ['collaborator_id' => $collaboratorId]);
        }
        return ['notification_id' => $notificationId, 'delivery_ids' => $deliveryIds];
    }

    private function applyScheduleEffects(array $event): void
    {
        if (str_starts_with((string) $event['event_type'], 'review.tarefa.') || $event['event_type'] === 'review.direcao.validacao_solicitada') {
            $stmt = $this->conn->prepare("UPDATE flow_connect_schedules SET resolved_at=COALESCE(resolved_at, UTC_TIMESTAMP(6)), status='RESOLVED', claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL WHERE event_type='review.aprovacao.sla_excedido' AND entity_type=? AND entity_id=? AND resolved_at IS NULL AND cancelled_at IS NULL");
            $stmt->bind_param('ss', $event['entity_type'], $event['entity_id']);
            $stmt->execute();
            $stmt->close();
        }
    }
}

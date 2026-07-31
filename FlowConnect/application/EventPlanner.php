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
            require dirname(__DIR__) . '/config/events/immediate_legacy.php'
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
        if ($definition === null) {
            throw new RuntimeException('flow_connect_event_definition_missing');
        }
        $producerMode = strtolower((string) ($event['metadata']['flow_connect_mode'] ?? 'shadow'));
        $recipientStrategy = (string) $definition['recipient_strategy'];
        if (($event['payload']['tipo_fluxo'] ?? null) === 'animacao' && str_starts_with((string) $event['event_type'], 'review.tarefa.')) {
            $recipientStrategy = 'animation_responsible';
        }
        $configuredDeliveryMode = (string) $definition['delivery_mode'];
        // Shadow preserva eventos que nunca deveriam comunicar. Para os
        // demais, cria a mesma delivery lógica do modo ativo e o worker é
        // responsável por bloquear a chamada externa.
        $deliveryMode = $producerMode === 'shadow' && !in_array($configuredDeliveryMode, ['HISTORY_ONLY', 'SUPPRESSED'], true)
            ? 'SHADOW'
            : $configuredDeliveryMode;
        $rendered = $this->templates->render((string) $definition['template'], $event);
        $plan = [
            'event_id' => (int) $event['id'],
            'notification_key' => $event['event_uuid'] . ':' . $definition['template'] . ':v1',
            'severity' => $definition['severity'],
            'category' => $definition['category'],
            'delivery_mode' => $deliveryMode,
            'template_code' => $definition['template'],
            'recipient_strategy' => $recipientStrategy,
            'payload' => ['event_type' => $event['event_type'], 'entity_type' => $event['entity_type'], 'entity_id' => $event['entity_id']],
        ];

        $this->conn->begin_transaction();
        try {
            $notificationId = $this->notifications->create($plan);
            $deliveryIds = [];
            if (!in_array($deliveryMode, ['HISTORY_ONLY', 'SUPPRESSED'], true)) {
                $logicalRecipients = $this->recipients->resolveForEvent($recipientStrategy, $event);
                if ($logicalRecipients === []) {
                    $this->deadLetters->record((int) $event['id'], $notificationId, null, 'recipient_not_resolved', ['strategy' => $recipientStrategy]);
                }
                foreach ($logicalRecipients as $recipient) {
                    $collaboratorId = $recipient['collaborator_id'] ?? null;
                    $slackUserId = $recipient['external_id'] ?? null;
                    if ($collaboratorId) {
                        $identity = $this->identities->findActiveByCollaborator((int) $collaboratorId);
                        $slackUserId = $identity['slack_user_id'] ?? null;
                    }
                    $destinationKey = $collaboratorId
                        ? 'slack:collaborator:' . (int) $collaboratorId
                        : 'slack:channel:' . (string) $slackUserId;
                    // A delivery lógica é igual em shadow e active; o worker
                    // decide se pode chamar o canal externo.
                    $status = $slackUserId ? 'PENDING' : 'UNRESOLVED';
                    $deliveryId = $this->deliveries->create([
                        'notification_id' => $notificationId,
                        'destination_kind' => $recipient['destination_kind'],
                        'destination_key' => $destinationKey,
                        'collaborator_id' => $collaboratorId,
                        'slack_user_id' => $slackUserId,
                        'rendered_text' => $rendered['text'],
                        'rendered_blocks' => $rendered['blocks'],
                        'status' => $status,
                    ]);
                    $deliveryIds[] = $deliveryId;
                    if (!$slackUserId) {
                        $this->deadLetters->record((int) $event['id'], $notificationId, $deliveryId, 'slack_identity_unresolved', ['collaborator_id' => $collaboratorId]);
                    }
                }
            }
            $this->applyScheduleEffects($event);
            $this->conn->commit();
            return ['notification_id' => $notificationId, 'delivery_ids' => $deliveryIds, 'delivery_mode' => $deliveryMode, 'template' => $definition['template']];
        } catch (\Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
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

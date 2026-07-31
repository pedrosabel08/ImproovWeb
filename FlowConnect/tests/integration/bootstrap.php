<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/conexaoMain.php';
require_once dirname(__DIR__, 2) . '/config/test_mode.php';

use FlowConnect\Application\EventPlanner;
use FlowConnect\Contracts\EventEnvelope;

final class FlowConnectIntegrationContext
{
    public mysqli $conn;
    public string $prefix;
    public array $evidence = [];
    public array $testConfig;

    public function __construct()
    {
        $this->conn = conectarBanco();
        $this->prefix = 'it:flow-connect:' . gmdate('YmdHis') . ':' . getmypid();
        $this->testConfig = flow_connect_test_config();
    }

    public function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public function activeCollaboratorId(): int
    {
        if ($this->testConfig['enabled'] && $this->testConfig['collaborator_id'] > 0) {
            return $this->testConfig['collaborator_id'];
        }
        $row = $this->conn->query("SELECT colaborador_id FROM flow_connect_slack_identities WHERE status='ACTIVE' AND slack_user_id IS NOT NULL AND TRIM(slack_user_id)<>'' ORDER BY colaborador_id LIMIT 1")->fetch_assoc();
        $this->assert($row !== null, 'Nenhuma identidade ACTIVE disponível para a integração.');
        return (int) $row['colaborador_id'];
    }

    public function unresolvedCollaboratorId(): int
    {
        $row = $this->conn->query("SELECT colaborador_id FROM flow_connect_slack_identities WHERE status<>'ACTIVE' OR slack_user_id IS NULL OR TRIM(COALESCE(slack_user_id,''))='' ORDER BY colaborador_id LIMIT 1")->fetch_assoc();
        return $row ? (int) $row['colaborador_id'] : 999991;
    }

    public function publishAndPlan(array $event, string $case): array
    {
        $event['idempotency_key'] = $this->prefix . ':' . $case . ':' . substr((string) $event['idempotency_key'], 0, 120);
        $event['metadata']['flow_connect_mode'] = 'shadow';
        $this->conn->begin_transaction();
        try {
            $eventId = flow_connect_publish_in_transaction($this->conn, $event);
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
        $stored = $this->event($eventId);
        $plan = (new EventPlanner($this->conn, flow_connect_config()))->plan($stored);
        $this->evidence[$case] = ['event_id' => $eventId, 'notification_id' => $plan['notification_id'], 'delivery_ids' => $plan['delivery_ids']];
        return [$stored, $plan];
    }

    public function event(int $eventId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM flow_connect_events WHERE id=?');
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $this->assert($row !== null, "Evento {$eventId} não encontrado.");
        $row['id'] = (int) $row['id'];
        $row['event_version'] = (int) $row['event_version'];
        $row['actor_id'] = $row['actor_id'] === null ? null : (int) $row['actor_id'];
        $row['payload'] = json_decode((string) $row['payload_json'], true) ?: [];
        $row['metadata'] = json_decode((string) $row['metadata_json'], true) ?: [];
        return $row;
    }

    public function delivery(int $deliveryId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM flow_connect_deliveries WHERE id=?');
        $stmt->bind_param('i', $deliveryId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $this->assert($row !== null, "Delivery {$deliveryId} não encontrada.");
        return $row;
    }

    public function attemptsForDelivery(int $deliveryId): int
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) total FROM flow_connect_delivery_attempts WHERE delivery_id=?');
        $stmt->bind_param('i', $deliveryId);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }

    public function deadLettersForEvent(int $eventId): int
    {
        $stmt = $this->conn->prepare('SELECT COUNT(*) total FROM flow_connect_dead_letters WHERE event_id=?');
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }

    public function assertShadowDelivery(int $deliveryId, int $collaboratorId, bool $identityExpected): void
    {
        $delivery = $this->delivery($deliveryId);
        $this->assert((int) $delivery['collaborator_id'] === $collaboratorId, 'colaborador_id da delivery divergente.');
        $this->assert(trim((string) $delivery['rendered_text']) !== '', 'Preview da delivery vazio.');
        $this->assert((int) $delivery['attempt_count'] === 0 && $this->attemptsForDelivery($deliveryId) === 0, 'Shadow não pode criar tentativa externa.');
        if ($identityExpected) {
            $this->assert($delivery['status'] === 'PENDING', 'Delivery shadow com identidade ativa deve ficar PENDING lógico.');
            $this->assert(trim((string) $delivery['slack_user_id']) !== '', 'Identity ACTIVE não foi projetada na delivery.');
        } else {
            $this->assert($delivery['status'] === 'UNRESOLVED', 'Delivery sem identidade deve ficar UNRESOLVED.');
            $this->assert(trim((string) $delivery['slack_user_id']) === '', 'Delivery sem identidade não pode ter Slack ID.');
        }
    }

    public function cleanup(): void
    {
        $like = $this->conn->real_escape_string($this->prefix . '%');
        $eventIds = [];
        $result = $this->conn->query("SELECT id FROM flow_connect_events WHERE idempotency_key LIKE '{$like}'");
        while ($row = $result->fetch_assoc()) $eventIds[] = (int) $row['id'];
        if ($eventIds !== []) {
            $idList = implode(',', $eventIds);
            $this->conn->query("DELETE a FROM flow_connect_delivery_attempts a JOIN flow_connect_deliveries d ON d.id=a.delivery_id JOIN flow_connect_notifications n ON n.id=d.notification_id WHERE n.event_id IN ({$idList})");
            $this->conn->query("DELETE d FROM flow_connect_dead_letters d WHERE d.event_id IN ({$idList}) OR d.notification_id IN (SELECT id FROM (SELECT id FROM flow_connect_notifications WHERE event_id IN ({$idList})) owned_notifications)");
            $this->conn->query("DELETE FROM flow_connect_deliveries WHERE notification_id IN (SELECT id FROM (SELECT id FROM flow_connect_notifications WHERE event_id IN ({$idList})) owned_notifications)");
            $this->conn->query("DELETE FROM flow_connect_notifications WHERE event_id IN ({$idList})");
            $this->conn->query("DELETE FROM flow_connect_events WHERE id IN ({$idList})");
        }
        $this->conn->query("DELETE FROM flow_connect_schedules WHERE JSON_UNQUOTE(JSON_EXTRACT(context_json, '$.integration_prefix'))='" . $this->conn->real_escape_string($this->prefix) . "'");
        $this->conn->query("DELETE FROM flow_connect_slack_identities WHERE source='flow_connect_integration_test'");
    }
}

function fc_it_make_event(string $type, array $payload, string $entityType = 'funcao_imagem', string $entityId = '900001'): array
{
    return EventEnvelope::normalize([
        'event_type' => $type,
        'event_version' => 1,
        'source_module' => 'flow_review',
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'actor_id' => 1,
        'occurred_at' => gmdate('Y-m-d H:i:s'),
        'event_uuid' => null,
        'correlation_id' => null,
        'causation_event_uuid' => null,
        'idempotency_key' => 'temporary-key',
        'payload' => $payload,
        'metadata' => ['producer' => 'FlowConnect integration test'],
    ]);
}

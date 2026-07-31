<?php

declare(strict_types=1);

use FlowConnect\Application\FlowReviewEventFactory;
use FlowConnect\Contracts\EventEnvelope;

function fc_it_workers(FlowConnectIntegrationContext $ctx): void
{
    $active = $ctx->activeCollaboratorId();
    $event = FlowReviewEventFactory::mention(['comentario_id' => 996001, 'mencao_id' => 996101, 'autor_id' => 1, 'mencionado_id' => $active, 'flow_review_url' => 'https://improov/ImproovWeb/FlowReview/']);
    $event['idempotency_key'] = $ctx->prefix . ':worker-event';
    $event['metadata']['flow_connect_mode'] = 'shadow';
    $ctx->conn->begin_transaction();
    try {
        $eventId = flow_connect_publish_in_transaction($ctx->conn, $event);
        $ctx->conn->commit();
    } catch (Throwable $e) {
        $ctx->conn->rollback();
        throw $e;
    }
    // O ambiente pode conter eventos shadow legítimos do scheduler. Um lote
    // amplo preserva a ordem FIFO e impede que o fixture fique atrás deles.
    $command = 'php ' . escapeshellarg(dirname(__DIR__, 2) . '/workers/event_worker.php') . ' --once --limit=500';
    exec($command, $output, $exitCode);
    $ctx->assert($exitCode === 0, 'Event worker terminou com erro.');
    $stored = $ctx->event($eventId);
    $ctx->assert($stored['status'] === 'PROCESSED', 'Event worker não processou evento PENDING.');
    $notification = $ctx->conn->query("SELECT id FROM flow_connect_notifications WHERE event_id={$eventId}")->fetch_assoc();
    $ctx->assert($notification !== null, 'Worker não criou notification.');
    $delivery = $ctx->conn->query("SELECT id FROM flow_connect_deliveries WHERE notification_id=" . (int) $notification['id'])->fetch_assoc();
    $ctx->assert($delivery !== null, 'Worker não criou delivery lógica.');
    $before = (int) $ctx->conn->query("SELECT COUNT(*) total FROM flow_connect_deliveries WHERE notification_id=" . (int) $notification['id'])->fetch_assoc()['total'];
    exec($command, $outputRepeat, $exitRepeat);
    $after = (int) $ctx->conn->query("SELECT COUNT(*) total FROM flow_connect_deliveries WHERE notification_id=" . (int) $notification['id'])->fetch_assoc()['total'];
    $ctx->assert($exitRepeat === 0 && $before === $after && $after === 1, 'Execução repetida do event worker criou duplicidade.');
    $ctx->assert($ctx->attemptsForDelivery((int) $delivery['id']) === 0, 'Event worker não pode criar tentativa externa.');

    $expired = FlowReviewEventFactory::mention(['comentario_id' => 996002, 'mencao_id' => 996102, 'autor_id' => 1, 'mencionado_id' => $active, 'flow_review_url' => 'https://improov/ImproovWeb/FlowReview/']);
    $expired['idempotency_key'] = $ctx->prefix . ':worker-expired-claim';
    $expired['metadata']['flow_connect_mode'] = 'shadow';
    $ctx->conn->begin_transaction();
    try {
        $expiredId = flow_connect_publish_in_transaction($ctx->conn, $expired);
        $ctx->conn->commit();
    } catch (Throwable $e) {
        $ctx->conn->rollback();
        throw $e;
    }
    $ctx->conn->query("UPDATE flow_connect_events SET status='PROCESSING', claimed_by='expired-test', claimed_at=DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 10 MINUTE), claim_expires_at=DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 5 MINUTE) WHERE id=" . (int) $expiredId);
    exec($command, $expiredOutput, $expiredExit);
    $ctx->assert($expiredExit === 0 && $ctx->event($expiredId)['status'] === 'PROCESSED', 'Worker não recuperou claim expirado.');

    $invalidUuid = EventEnvelope::uuidV4();
    $invalidCorrelation = EventEnvelope::uuidV4();
    $invalidKey = $ctx->prefix . ':worker-invalid';
    $invalidPayload = '{}';
    $invalidMeta = '{"flow_connect_mode":"shadow","producer":"integration"}';
    $invalid = $ctx->conn->prepare("INSERT INTO flow_connect_events (event_uuid,event_type,event_version,source_module,entity_type,entity_id,actor_id,occurred_at,correlation_id,causation_event_uuid,idempotency_key,payload_json,metadata_json,status) VALUES (?, 'review.mencao.criada', 1, 'flow_review', 'mencao', '996003', 1, UTC_TIMESTAMP(6), ?, NULL, ?, ?, ?, 'PENDING')");
    $invalid->bind_param('sssss', $invalidUuid, $invalidCorrelation, $invalidKey, $invalidPayload, $invalidMeta);
    $invalid->execute();
    $invalidId = (int) $ctx->conn->insert_id;
    $invalid->close();
    for ($i = 0; $i < 3; $i++) {
        exec($command, $invalidOutput, $invalidExit);
        $ctx->assert($invalidExit === 0, 'Worker falhou ao tratar evento inválido.');
    }
    $invalidRow = $ctx->event($invalidId);
    $ctx->assert($invalidRow['status'] === 'DEAD' && $ctx->deadLettersForEvent($invalidId) === 1, 'Evento inválido não foi para DEAD/dead-letter seguro.');

    $deliveryCommand = 'php ' . escapeshellarg(dirname(__DIR__, 2) . '/workers/delivery_worker.php') . ' --once --limit=20';
    $attemptsBefore = (int) $ctx->conn->query('SELECT COUNT(*) total FROM flow_connect_delivery_attempts')->fetch_assoc()['total'];
    exec($deliveryCommand, $deliveryOutput, $deliveryExit);
    $attemptsAfter = (int) $ctx->conn->query('SELECT COUNT(*) total FROM flow_connect_delivery_attempts')->fetch_assoc()['total'];
    $ctx->assert($deliveryExit === 0 && $attemptsBefore === $attemptsAfter, 'Delivery worker executou tentativa externa em shadow.');
    $ctx->evidence['worker'] = ['event_id' => $eventId, 'notification_id' => (int) $notification['id'], 'delivery_id' => (int) $delivery['id']];
}

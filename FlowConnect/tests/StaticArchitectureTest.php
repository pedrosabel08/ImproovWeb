<?php

function flow_connect_test_static_architecture(): void
{
    $root = dirname(__DIR__, 2);
    $migration = file_get_contents(dirname(__DIR__) . '/migrations/001_flow_connect_core.sql');
    foreach (['flow_connect_events', 'flow_connect_notifications', 'flow_connect_deliveries', 'flow_connect_delivery_attempts', 'flow_connect_schedules', 'flow_connect_slack_identities', 'flow_connect_dead_letters'] as $table) {
        fc_assert(strpos($migration, "CREATE TABLE {$table}") !== false, "migration has {$table}");
    }
    fc_assert(strpos($migration, 'UNIQUE KEY uq_flow_connect_event_idempotency') !== false, 'event idempotency unique');
    fc_assert(strpos($migration, 'UNIQUE KEY uq_flow_connect_delivery_destination') !== false, 'delivery idempotency unique');

    foreach (['event_worker.php', 'delivery_worker.php', 'scheduler_worker.php'] as $worker) {
        $source = file_get_contents(dirname(__DIR__) . '/workers/' . $worker);
        fc_assert(strpos($source, 'flow_connect_cli_options') !== false, "{$worker} supports CLI options");
        fc_assert(strpos($source, 'while (true)') === false, "{$worker} has no default infinite loop");
    }
    $eventRepo = file_get_contents(dirname(__DIR__) . '/infrastructure/EventRepository.php');
    $deliveryRepo = file_get_contents(dirname(__DIR__) . '/infrastructure/DeliveryRepository.php');
    $notificationRepo = file_get_contents(dirname(__DIR__) . '/infrastructure/NotificationRepository.php');
    fc_assert(strpos($eventRepo, 'FOR UPDATE SKIP LOCKED') !== false, 'event concurrent claim');
    fc_assert(strpos($deliveryRepo, 'FOR UPDATE SKIP LOCKED') !== false, 'delivery concurrent claim');
    fc_assert(strpos($deliveryRepo, "n.delivery_mode <> 'SHADOW'") !== false, 'delivery worker excludes shadow notifications');
    fc_assert(strpos($notificationRepo, "VALUES(delivery_mode)='SHADOW', 'READY'") !== false, 'shadow reprocessing restores logical notification state');

    $reviewSource = file_get_contents($root . '/FlowReview/revisarTarefa.php');
    fc_assert(strpos($reviewSource, 'case "reprovado"') === false, 'no invented rejection producer');
    fc_assert(strpos($reviewSource, "flow_connect_should_bypass_legacy('sftp'") !== false, 'technical active bypass');
    fc_assert(strpos($reviewSource, "flow_connect_should_bypass_legacy('task'") !== false, 'task active bypass');
    $planner = file_get_contents(dirname(__DIR__) . '/application/EventPlanner.php');
    fc_assert(strpos($planner, 'slack_identity_unresolved') !== false, 'missing Slack identity is observable');
    fc_assert(strpos($planner, "['HISTORY_ONLY', 'SUPPRESSED']") !== false, 'shadow preserves history-only planning');
    fc_assert(strpos($planner, 'users.list') === false, 'planner does not call users.list');
}

<?php
declare(strict_types=1);

// Business events are intentionally history-only until their communication
// policy is enabled. Operational pending cycles remain the source for SLA.
$events = [];
foreach (['link_issued','client_submitted','complement_requested','client_resubmitted','approved'] as $action) {
    $events['briefing.briefing.' . $action] = [
        'severity' => in_array($action, ['client_submitted','complement_requested'], true) ? 'ACAO' : 'INFORMATIVO',
        'category' => 'BRIEFING', 'delivery_mode' => 'HISTORY_ONLY',
        'template' => 'operational_pending_created', 'recipient_strategy' => 'operational_pending_audience',
        'required_payload' => ['titulo','obra_id'],
    ];
}
return $events;

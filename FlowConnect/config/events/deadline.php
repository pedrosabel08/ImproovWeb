<?php

declare(strict_types=1);

$events = [];
foreach (['aberto', 'atualizado', 'resolvido'] as $action) {
    foreach (['' => 'ACAO', '_critico' => 'CRITICO'] as $suffix => $severity) {
        $events["deadline.incidente.{$action}{$suffix}"] = [
            'family' => 'deadline_diagnosis',
            'severity' => $severity,
            'category' => 'OPERATIONAL',
            'delivery_mode' => 'CHANNEL',
            'template' => 'deadline_incident',
            'recipient_strategy' => 'deadline_webhook',
            'required_payload' => ['worker', 'job_id', 'job_name', 'category', 'diagnosis', 'confidence',
                'severity', 'state', 'occurrences', 'window_count', 'window_minutes', 'recommendation', 'evidence_summary'],
        ];
    }
}
return $events;

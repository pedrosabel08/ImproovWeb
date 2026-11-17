<?php

declare(strict_types=1);

return static function (array $payload, array $event): array {
    $value = static fn(string $key, int $limit = 220): string =>
        flow_connect_tpl_escape(flow_connect_tpl_truncate($payload[$key] ?? '', $limit));
    $eventType = (string) $event['event_type'];
    $heading = match (true) {
        str_starts_with($eventType, 'deadline.incidente.resolvido') => 'Incidente resolvido',
        str_starts_with($eventType, 'deadline.incidente.atualizado') => 'Atualização do incidente',
        $payload['severity'] === 'CRITICO' => 'Falha crítica',
        default => 'Falha recorrente',
    };
    $state = ['open' => 'Aberto', 'monitoring' => 'Em monitoramento', 'resolved' => 'Resolvido'];
    $worker = $value('worker', 100) ?: 'Worker indisponível';
    $job = $value('job_name') ?: $value('job_id', 24);
    $otherJobs = max(0, (int) ($payload['jobs_total'] ?? 1) - 1);
    if ($otherJobs) $job .= " (+{$otherJobs})";
    $count = (int) $payload['occurrences'];
    $windowCount = (int) $payload['window_count'];
    $window = (int) $payload['window_minutes'];
    $countLine = "Ocorrências: {$count} no incidente.";
    if ($windowCount) $countLine .= " {$windowCount} em uma janela de {$window} min.";
    $ram = $value('ram_percent', 45);
    return ['text' => implode("\n", [
        "*{$heading} — {$worker}*",
        "Job: {$job}",
        'Categoria: ' . $value('category'),
        'Diagnóstico: ' . $value('diagnosis') . ' · Confiança: ' . $value('confidence'),
        'Evidências: ' . $value('evidence_summary') . ($ram ? " {$ram}" : ''),
        'Recomendação: ' . $value('recommendation'),
        $countLine,
        'Estado: ' . ($state[$payload['state']] ?? 'Indisponível') . ' · Incidente #' . (int) $event['entity_id'],
    ])];
};

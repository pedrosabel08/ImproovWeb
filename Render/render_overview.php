<?php

function render_overview_table_available(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $available = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();
    return $available;
}

function render_overview_group(string $execution): string
{
    return "CASE
        WHEN r.status = 'Aprovado' THEN 'approved'
        WHEN r.status = 'Erro' OR (r.has_error = 1 AND r.status <> 'Em aprovação') OR $execution = 'failed' THEN 'error'
        WHEN r.status IN ('Refazendo', 'Reprovado') THEN 'rework'
        WHEN $execution = 'rendering' THEN 'rendering'
        WHEN $execution = 'queued' OR (r.status = 'Não iniciado' AND $execution IS NULL) THEN 'queued'
        ELSE 'other' END";
}

function render_overview_summary(mysqli $conn, callable $bind, string $from, string $where, string $types, array $params, string $group, string $duration, string $dateFrom, string $dateTo): array
{
    $stmt = $conn->prepare("WITH cohort AS (
        SELECT r.data AS sent_at, $group AS render_group, $duration AS duration,
            ROW_NUMBER() OVER (PARTITION BY IFNULL(r.imagem_id,-r.idrender_alta)
                ORDER BY r.data DESC, r.idrender_alta DESC) AS image_rank,
            ROW_NUMBER() OVER (PARTITION BY IFNULL(r.imagem_id,-r.idrender_alta), r.status='Aprovado'
                ORDER BY r.data DESC, r.idrender_alta DESC) AS tab_rank
        $from $where
    )
    SELECT COUNT(CASE WHEN tab_rank=1 AND render_group='approved' THEN 1 END) AS approved,
           COUNT(CASE WHEN tab_rank=1 AND render_group<>'approved' THEN 1 END) AS pending,
           COUNT(CASE WHEN tab_rank=1 AND render_group='rendering' THEN 1 END) AS rendering,
           COUNT(CASE WHEN tab_rank=1 AND render_group='queued' THEN 1 END) AS queued,
           COUNT(CASE WHEN tab_rank=1 AND render_group='rework' THEN 1 END) AS rework,
           COUNT(CASE WHEN tab_rank=1 AND render_group='error' THEN 1 END) AS error,
           COUNT(CASE WHEN image_rank=1 THEN 1 END) AS images,
           COUNT(CASE WHEN image_rank=1 AND render_group='approved' THEN 1 END) AS successes,
           COUNT(CASE WHEN image_rank=1 AND render_group IN ('approved','rework','error') THEN 1 END) AS outcomes,
           AVG(CASE WHEN image_rank=1 AND duration>=0 THEN duration END) AS mean_seconds,
           DATEDIFF(COALESCE(NULLIF(?,''),MAX(sent_at)),COALESCE(NULLIF(?,''),MIN(sent_at)))+1 AS days
    FROM cohort");
    $summary = $bind($stmt, $types . 'ss', [...$params, $dateTo, $dateFrom])->fetch_assoc();
    $stmt->close();
    return [
        'approved' => (int) $summary['approved'], 'pending' => (int) $summary['pending'],
        'rendering' => (int) $summary['rendering'], 'queued' => (int) $summary['queued'],
        'rework' => (int) $summary['rework'], 'error' => (int) $summary['error'],
        'success_rate' => $summary['outcomes'] ? 100 * $summary['successes'] / $summary['outcomes'] : null,
        'per_day' => $summary['days'] > 0 ? $summary['images'] / $summary['days'] : null,
        'mean_seconds' => isset($summary['mean_seconds']) ? (int) round($summary['mean_seconds']) : null,
    ];
}

function render_processing_history(mysqli $conn, int $renderId): array
{
    if (!deadline_flow_schema_ready($conn)) {
        return [[], []];
    }
    $attempts = $conn->prepare('SELECT numero_tentativa, deadline_job_id, status, criado_em, iniciado_em,
        concluido_em, encerrado_em, motivo_encerramento FROM render_tentativas
        WHERE render_id=? ORDER BY numero_tentativa DESC');
    $attempts->bind_param('i', $renderId);
    $attempts->execute();
    $history = $attempts->get_result()->fetch_all(MYSQLI_ASSOC);
    $attempts->close();

    $events = $conn->prepare("SELECT rt.numero_tentativa, ev.tipo, ev.dados_json, ev.criado_em
        FROM render_tentativas rt JOIN render_tentativa_eventos ev ON ev.tentativa_id=rt.id
        WHERE rt.render_id=? AND ev.tipo<>'OBSERVACAO_DEADLINE' ORDER BY ev.id DESC LIMIT 100");
    $events->bind_param('i', $renderId);
    $events->execute();
    $logs = [];
    foreach ($events->get_result() as $event) {
        $data = json_decode((string) $event['dados_json'], true);
        unset($event['dados_json']);
        $event['message'] = $data['message'] ?? $data['reason'] ?? null;
        $logs[] = $event;
    }
    $events->close();
    return [$history, $logs];
}

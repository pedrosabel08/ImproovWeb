<?php

require_once __DIR__ . '/render_ef_helpers.php';

function render_diagnostic_ready(mysqli $conn): bool
{
    if (getenv('FLOW_RENDER_DEADLINE_DIAGNOSIS') !== '1') return false;
    return (bool) render_ef_rows($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deadline_diagnostic_occurrences'");
}

function render_diagnostic_original_url(array $occurrence): ?string
{
    $base = rtrim(trim((string) getenv('FLOW_RENDER_DEADLINE_REPORT_ORIGIN')), '/');
    if (!preg_match('~^https://[a-z0-9.-]+(?::[0-9]{1,5})?$~iD', $base)) return null;
    return $base . '/api/jobreports?' . http_build_query([
        'JobID' => $occurrence['job_id'], 'Data' => 'errorcontents', 'ReportID' => $occurrence['report_id'],
    ], '', '&', PHP_QUERY_RFC3986);
}

function render_diagnostic_page(mysqli $conn, array $query, bool $manager): ?array
{
    $renderId = max(0, (int) ($query['render_id'] ?? 0));
    $incidentId = max(0, (int) ($query['incident_id'] ?? 0));
    $before = max(1, (int) ($query['before'] ?? PHP_INT_MAX));
    if ($renderId) {
        $source = render_ef_rows($conn, 'SELECT r.responsavel_id,r.imagem_id,i.obra_id
            FROM render_alta r JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=r.imagem_id
            WHERE r.idrender_alta=? AND r.excluido_em IS NULL', 'i', [$renderId])[0] ?? null;
        if (!$source || !render_ef_authorized($conn, $_SESSION, $source)) return null;
    } elseif (!$manager) {
        return null;
    }

    if (!$renderId && !$incidentId) {
        $rows = render_ef_rows($conn, "SELECT i.id,i.worker,i.state,i.last_at,i.episode,
            (SELECT o.diagnosis_json FROM deadline_diagnostic_occurrences o
             WHERE o.incident_id=i.id AND o.episode=i.episode ORDER BY o.occurred_at DESC,o.id DESC LIMIT 1) AS diagnosis,
            (SELECT COUNT(*) FROM deadline_diagnostic_occurrences o WHERE o.incident_id=i.id AND o.episode=i.episode) AS occurrences
            FROM deadline_diagnostic_incidents i WHERE i.id < ? ORDER BY i.id DESC LIMIT 21", 'i', [$before]);
        $more = count($rows) > 20;
        $rows = array_slice($rows, 0, 20);
        foreach ($rows as &$row) $row['diagnosis'] = json_decode((string) $row['diagnosis'], true);
        return ['incidents' => $rows, 'next' => $more ? end($rows)['id'] : null];
    }

    $incident = null;
    $history = [];
    $correlation = null;
    $collection = null;
    if ($renderId) {
        $where = "AND (EXISTS (SELECT 1 FROM render_tentativas t WHERE t.render_id=? AND t.deadline_job_id=o.job_id)
                     OR EXISTS (SELECT 1 FROM render_alta r WHERE r.idrender_alta=? AND r.deadline_job_id=o.job_id))";
        $values = [$before, $renderId, $renderId];
        $scan = render_ef_rows($conn, "SELECT j.last_scan_at,j.last_error FROM render_alta r
            JOIN deadline_diagnostic_jobs j ON j.job_id=r.deadline_job_id WHERE r.idrender_alta=?", 'i', [$renderId]);
        $collection = $scan[0] ?? null;
    } else {
        $where = 'AND o.incident_id=?';
        $values = [$before, $incidentId];
        $incident = render_ef_rows($conn, 'SELECT * FROM deadline_diagnostic_incidents WHERE id=?', 'i', [$incidentId])[0] ?? null;
        if (!$incident) return null;
        $history = render_ef_rows($conn, "SELECT kind,episode,recorded_at,detail_json FROM deadline_diagnostic_history
            WHERE incident_id=? AND kind NOT IN ('classified','reclassified') ORDER BY id DESC LIMIT 100", 'i', [$incidentId]);
        foreach ($history as &$event) {
            $event['detail'] = json_decode((string) $event['detail_json'], true);
            unset($event['detail_json']);
        }
        $correlation = render_ef_rows($conn, "
            SELECT (SELECT COUNT(DISTINCT worker) FROM deadline_diagnostic_occurrences
                    WHERE signature=? AND occurred_at>=UTC_TIMESTAMP()-INTERVAL 30 DAY) AS workers_with_signature,
                   (SELECT COUNT(DISTINCT job_id) FROM deadline_diagnostic_occurrences
                    WHERE worker=? AND occurred_at>=UTC_TIMESTAMP()-INTERVAL 30 DAY) AS jobs_on_worker,
                   (SELECT COUNT(DISTINCT job_id) FROM deadline_diagnostic_occurrences
                    WHERE incident_id=? AND episode=?) AS jobs_in_incident,
                   (SELECT COUNT(DISTINCT related.worker) FROM deadline_diagnostic_occurrences related
                    WHERE related.occurred_at>=UTC_TIMESTAMP()-INTERVAL 30 DAY AND related.job_id IN (
                        SELECT o.job_id FROM deadline_diagnostic_occurrences o WHERE o.incident_id=? AND o.episode=?
                    )) AS workers_on_jobs,
                   (SELECT COUNT(*) FROM deadline_diagnostic_occurrences related
                    WHERE related.occurred_at>=UTC_TIMESTAMP()-INTERVAL 30 DAY AND related.renderer=(
                        SELECT o.renderer FROM deadline_diagnostic_occurrences o
                        WHERE o.incident_id=? AND o.episode=? AND o.renderer IS NOT NULL
                        ORDER BY o.occurred_at DESC,o.id DESC LIMIT 1
                    )) AS renderer_failures", 'ssiiiiii',
            [$incident['signature'], $incident['worker'], $incidentId, $incident['episode'],
                $incidentId, $incident['episode'], $incidentId, $incident['episode']])[0];
    }
    $rows = render_ef_rows($conn, "SELECT o.id,o.incident_id,o.episode,o.job_id,o.job_name,o.worker,o.task_id,o.frame,
        o.report_id,o.body_state,o.occurred_at,o.collected_at,o.signature,o.report_json,o.evidence_json,o.diagnosis_json,
        i.state AS incident_state,i.episode AS current_episode
        FROM deadline_diagnostic_occurrences o LEFT JOIN deadline_diagnostic_incidents i ON i.id=o.incident_id
        WHERE o.id < ? {$where} ORDER BY o.id DESC LIMIT 21", str_repeat('i', count($values)), $values);
    $more = count($rows) > 20;
    $rows = array_slice($rows, 0, 20);
    foreach ($rows as &$row) {
        $report = json_decode((string) $row['report_json'], true) ?: [];
        $row['report'] = array_intersect_key($report, array_flip(['plugin', 'job_pool', 'job_group', 'peak_ram', 'capacity']));
        $row['evidence'] = json_decode((string) $row['evidence_json'], true) ?: [];
        unset($row['evidence']['hits']);
        $row['diagnosis'] = json_decode((string) $row['diagnosis_json'], true);
        $row['original_url'] = $manager ? render_diagnostic_original_url($row) : null;
        $row['classifications'] = render_ef_rows($conn, "SELECT kind,recorded_at,
            JSON_UNQUOTE(JSON_EXTRACT(detail_json,'$.rule')) AS rule,
            JSON_UNQUOTE(JSON_EXTRACT(detail_json,'$.confidence')) AS confidence,
            JSON_UNQUOTE(JSON_EXTRACT(detail_json,'$.catalog_version')) AS catalog_version
            FROM deadline_diagnostic_history WHERE occurrence_id=? AND kind IN ('classified','reclassified')
            ORDER BY id DESC LIMIT 20", 'i', [(int) $row['id']]);
        unset($row['report_json'], $row['evidence_json'], $row['diagnosis_json']);
    }
    return ['incident' => $incident, 'occurrences' => $rows, 'history' => $history,
        'next' => $more ? end($rows)['id'] : null, 'correlation' => $correlation, 'collection' => $collection];
}

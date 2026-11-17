<?php

require_once __DIR__ . '/render_ef_helpers.php';

function render_diagnostic_ready(mysqli $conn): bool
{
    if (getenv('FLOW_RENDER_DEADLINE_DIAGNOSIS') !== '1') {
        return false;
    }
    return (bool) render_ef_rows($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deadline_diagnostic_occurrences'");
}

function render_diagnostic_original_url(array $occurrence): ?string
{
    $base = rtrim(trim((string) getenv('FLOW_RENDER_DEADLINE_REPORT_ORIGIN')), '/');
    if (!preg_match('~^https://[a-z0-9.-]+(?::[0-9]{1,5})?$~iD', $base)) {
        return null;
    }
    return $base . '/api/jobreports?' . http_build_query([
        'JobID' => $occurrence['job_id'], 'Data' => 'errorcontents', 'ReportID' => $occurrence['report_id'],
    ], '', '&', PHP_QUERY_RFC3986);
}

function render_diagnostic_filter(array $query, bool $facets = false): array
{
    $zone = new DateTimeZone('America/Sao_Paulo');
    $utc = new DateTimeZone('UTC');
    $end = new DateTimeImmutable('now', $utc);
    $period = $query['period'] ?? '30';
    $start = $end->modify('-' . (in_array($period, ['1', '7', '30'], true) ? $period : '30') . ' days');
    if ($period === 'custom') {
        $parse = static function ($value) use ($zone): ?DateTimeImmutable {
            if (!is_string($value)) {
                return null;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
            return $date && $date->format('Y-m-d') === $value && $value >= '1000-01-01' && $value < '9999-12-31' ? $date : null;
        };
        $start = $parse($query['start'] ?? null);
        $last = $parse($query['end'] ?? null);
        if (!$start || !$last || $start > $last) {
            throw new InvalidArgumentException('Informe um intervalo de datas válido.');
        }
        $end = $last->modify('+1 day');
    }
    $where = 'o.occurred_at >= ? AND o.occurred_at < ?';
    $values = [$start->setTimezone($utc)->format('Y-m-d H:i:s.u'), $end->setTimezone($utc)->format('Y-m-d H:i:s.u')];
    if (!$facets) {
        foreach (['worker' => 'o.worker', 'renderer' => 'o.renderer', 'category' => render_diagnostic_category(), 'state' => 'i.state'] as $key => $column) {
            $value = $query[$key] ?? '';
            if (is_string($value) && $value !== '') {
                $where .= " AND $column = ?";
                $values[] = $value;
            }
        }
    }
    return [$where, $values];
}

function render_diagnostic_category(): string
{
    return "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.diagnosis_json,'$.category')), 'pending')";
}

function render_diagnostic_dashboard(mysqli $conn, array $query, string $view): array
{
    [$where, $values] = render_diagnostic_filter($query);
    $from = "FROM deadline_diagnostic_occurrences o LEFT JOIN deadline_diagnostic_incidents i ON i.id=o.incident_id WHERE $where";
    $rows = static fn (string $sql) => render_ef_rows($conn, $sql, str_repeat('s', count($values)), $values);
    $category = render_diagnostic_category();
    $label = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.diagnosis_json,'$.category_label')), 'Diagnóstico pendente')";
    if ($view === 'list') {
        $total = (int) $rows("SELECT COUNT(DISTINCT i.id) AS total $from")[0]['total'];
        $pages = max(1, (int) ceil($total / 6));
        $page = max(1, min($pages, (int) ($query['page'] ?? 1)));
        $offset = ($page - 1) * 6;
        $order = match ($query['sort'] ?? '') {
            'oldest' => 'matched.last_at ASC, matched.id ASC',
            'occurrences' => 'matched.occurrences DESC, matched.last_at DESC, matched.id DESC',
            default => 'matched.last_at DESC, matched.id DESC',
        };
        $items = render_ef_rows($conn, "SELECT matched.*, i.worker, i.state, o.diagnosis_json
            FROM (SELECT i.id, MAX(o.occurred_at) AS last_at, COUNT(*) AS occurrences
                $from AND i.id IS NOT NULL GROUP BY i.id) matched
            JOIN deadline_diagnostic_incidents i ON i.id=matched.id
            JOIN deadline_diagnostic_occurrences o ON o.id=(
                SELECT o.id $from AND o.incident_id=matched.id
                ORDER BY o.occurred_at DESC,o.id DESC LIMIT 1
            )
            ORDER BY $order LIMIT 6 OFFSET $offset", str_repeat('s', 2 * count($values)), array_merge($values, $values));
        foreach ($items as &$item) {
            $diagnosis = json_decode((string) $item['diagnosis_json'], true);
            $item['diagnosis'] = array_intersect_key($diagnosis ?: [], array_flip(['category_label', 'severity']));
            unset($item['diagnosis_json']);
        }
        return ['incidents' => $items, 'page' => $page, 'pages' => $pages, 'total' => $total];
    }
    $categories = $rows("SELECT $category AS category, MAX($label) AS label, COUNT(*) AS total
        $from GROUP BY $category ORDER BY total DESC, category");
    $workerLimit = $view === 'analysis' ? 10 : 1;
    $workers = $rows("SELECT o.worker, COUNT(*) AS total, COUNT(DISTINCT o.job_id) AS jobs
        $from AND o.worker IS NOT NULL AND o.worker<>'' GROUP BY o.worker ORDER BY total DESC, o.worker LIMIT $workerLimit");
    if ($view === 'analysis') {
        // The farm's reporting calendar is in São Paulo; the collected timestamps are UTC.
        $day = "DATE(CONVERT_TZ(o.occurred_at, '+00:00', '-03:00'))";
        $days = $rows("SELECT $day AS day, COUNT(*) AS occurrences, COUNT(DISTINCT i.id) AS incidents
            $from GROUP BY $day ORDER BY day");
        return compact('days', 'workers', 'categories');
    }
    $summary = $rows("SELECT COUNT(*) AS occurrences, COUNT(DISTINCT i.id) AS incidents, MAX(o.occurred_at) AS last_at $from")[0];
    [$facetWhere, $facetValues] = render_diagnostic_filter($query, true);
    $facets = [];
    foreach (['worker' => 'o.worker', 'renderer' => 'o.renderer', 'category' => $category] as $key => $column) {
        $facetLabel = $key === 'category' ? "MAX($label)" : $column;
        $facets[$key] = render_ef_rows($conn, "SELECT $column AS value, $facetLabel AS label
            FROM deadline_diagnostic_occurrences o WHERE $facetWhere AND $column <> ''
            GROUP BY $column ORDER BY label", 'ss', $facetValues);
    }
    return ['summary' => $summary, 'worker' => $workers[0] ?? null, 'category' => $categories[0] ?? null, 'facets' => $facets];
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
        if (!$source || !render_ef_authorized($conn, $_SESSION, $source)) {
            return null;
        }
    } elseif (!$manager) {
        return null;
    }

    $view = $query['view'] ?? '';
    if (!$renderId && !$incidentId && in_array($view, ['summary', 'list', 'analysis'], true)) {
        return render_diagnostic_dashboard($conn, $query, $view);
    }
    $feed = !$renderId && !$incidentId && in_array($view, ['occurrences', 'reports'], true);
    if (!$renderId && !$incidentId && !$feed) {
        $rows = render_ef_rows($conn, "SELECT i.id,i.worker,i.state,i.last_at,i.episode,
            (SELECT o.diagnosis_json FROM deadline_diagnostic_occurrences o
             WHERE o.incident_id=i.id AND o.episode=i.episode ORDER BY o.occurred_at DESC,o.id DESC LIMIT 1) AS diagnosis,
            (SELECT COUNT(*) FROM deadline_diagnostic_occurrences o WHERE o.incident_id=i.id AND o.episode=i.episode) AS occurrences
            FROM deadline_diagnostic_incidents i WHERE i.id < ? ORDER BY i.id DESC LIMIT 21", 'i', [$before]);
        $more = count($rows) > 20;
        $rows = array_slice($rows, 0, 20);
        foreach ($rows as &$row) {
            $row['diagnosis'] = json_decode((string) $row['diagnosis'], true);
        }
        return ['incidents' => $rows, 'next' => $more ? end($rows)['id'] : null];
    }

    $incident = null;
    $history = [];
    $correlation = null;
    $collection = null;
    $types = 'i';
    if ($feed) {
        [$conditions, $filters] = render_diagnostic_filter($query);
        $where = "AND $conditions";
        $values = array_merge([$before], $filters);
        $types .= str_repeat('s', count($filters));
    } elseif ($renderId) {
        $where = "AND (EXISTS (SELECT 1 FROM render_tentativas t WHERE t.render_id=? AND t.deadline_job_id=o.job_id)
                     OR EXISTS (SELECT 1 FROM render_alta r WHERE r.idrender_alta=? AND r.deadline_job_id=o.job_id))";
        $values = [$before, $renderId, $renderId];
        $types .= 'ii';
        $scan = render_ef_rows($conn, "SELECT j.last_scan_at,j.last_error FROM render_alta r
            JOIN deadline_diagnostic_jobs j ON j.job_id=r.deadline_job_id WHERE r.idrender_alta=?", 'i', [$renderId]);
        $collection = $scan[0] ?? null;
    } else {
        $where = 'AND o.incident_id=?';
        $values = [$before, $incidentId];
        $types .= 'i';
        $incident = render_ef_rows($conn, 'SELECT * FROM deadline_diagnostic_incidents WHERE id=?', 'i', [$incidentId])[0] ?? null;
        if (!$incident) {
            return null;
        }
        if ($view === '' || $view === 'overview') {
            $history = render_ef_rows($conn, "SELECT kind,episode,recorded_at,detail_json FROM deadline_diagnostic_history
                WHERE incident_id=? AND kind NOT IN ('classified','reclassified') ORDER BY recorded_at DESC,id DESC LIMIT 100", 'i', [$incidentId]);
            if ($view === 'overview') {
                $events = render_ef_rows($conn, "SELECT 'occurrence' AS kind, episode, occurred_at AS recorded_at,
                    JSON_OBJECT('job', COALESCE(NULLIF(job_name,''), job_id)) AS detail_json
                    FROM deadline_diagnostic_occurrences WHERE incident_id=? AND occurred_at IS NOT NULL
                    ORDER BY occurred_at DESC,id DESC LIMIT 100", 'i', [$incidentId]);
                $history = array_merge($history, $events);
                usort($history, static fn ($a, $b) => strcmp($b['recorded_at'], $a['recorded_at']));
            }
            foreach ($history as &$event) {
                $event['detail'] = json_decode((string) $event['detail_json'], true);
                unset($event['detail_json']);
            }
            $correlation = render_ef_rows(
                $conn,
                "
                SELECT (SELECT COUNT(*) FROM deadline_diagnostic_occurrences
                        WHERE incident_id=?) AS occurrences_all_episodes,
                       (SELECT COUNT(*) FROM deadline_diagnostic_occurrences
                        WHERE incident_id=? AND episode=?) AS occurrences_in_incident,
                       (SELECT COUNT(DISTINCT worker) FROM deadline_diagnostic_occurrences
                        WHERE signature=? AND occurred_at>=UTC_TIMESTAMP()-INTERVAL 30 DAY) AS workers_with_signature,
                       (SELECT COUNT(DISTINCT worker) FROM deadline_diagnostic_occurrences
                        WHERE signature=? AND worker<>? AND occurred_at>=UTC_TIMESTAMP()-INTERVAL 30 DAY) AS other_workers_with_signature,
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
                        )) AS renderer_failures",
                'iiissssiiiiii',
                [$incidentId, $incidentId, $incident['episode'], $incident['signature'], $incident['signature'], $incident['worker'],
                    $incident['worker'], $incidentId, $incident['episode'],
                    $incidentId, $incident['episode'], $incidentId, $incident['episode']]
            )[0];
        }
    }
    $limit = 21;
    $order = 'o.id DESC';
    if (!$renderId && $incidentId && $view === 'overview') {
        $where .= ' AND o.episode=?';
        $values[] = (int) $incident['episode'];
        $types .= 'i';
        $limit = 1;
        $order = 'o.occurred_at DESC,o.id DESC';
    }
    if ($view === 'reports') {
        $where .= " AND (JSON_LENGTH(JSON_EXTRACT(o.evidence_json,'$.excerpts')) > 0";
        if ($manager && render_diagnostic_original_url(['job_id' => '', 'report_id' => ''])) {
            $where .= " OR o.body_state='available'";
        }
        $where .= ')';
        $rows = render_ef_rows($conn, "SELECT o.id,o.occurred_at,o.worker,o.job_id,o.job_name,o.report_id,
            JSON_EXTRACT(o.evidence_json,'$.excerpts') AS excerpts
            FROM deadline_diagnostic_occurrences o LEFT JOIN deadline_diagnostic_incidents i ON i.id=o.incident_id
            WHERE o.id < ? $where ORDER BY o.id DESC LIMIT 11", $types, $values);
        $more = count($rows) > 10;
        $rows = array_slice($rows, 0, 10);
        foreach ($rows as &$row) {
            $row['excerpts'] = json_decode((string) $row['excerpts'], true) ?: [];
            $row['original_url'] = $manager ? render_diagnostic_original_url($row) : null;
        }
        return ['reports' => $rows, 'next' => $more ? end($rows)['id'] : null];
    }
    $rows = render_ef_rows($conn, "SELECT o.id,o.incident_id,o.episode,o.job_id,o.job_name,o.worker,o.task_id,o.frame,
        o.report_id,o.body_state,o.occurred_at,o.collected_at,o.signature,o.report_json,o.evidence_json,o.diagnosis_json,
        i.state AS incident_state,i.episode AS current_episode
        FROM deadline_diagnostic_occurrences o LEFT JOIN deadline_diagnostic_incidents i ON i.id=o.incident_id
        WHERE o.id < ? {$where} ORDER BY $order LIMIT $limit", $types, $values);
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

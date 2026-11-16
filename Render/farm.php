<?php

require_once __DIR__ . '/../config/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SESSION['logado'] ?? false) !== true) {
    http_response_code(401);
    echo json_encode(['message' => 'Sessão expirada.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}
session_write_close();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/render_overview.php';

$payload = ['health' => 'unavailable', 'observed_at' => null, 'workers' => [], 'incidents' => []];
try {
    $conn = improov_db_connect();
    if (render_overview_table_available($conn, 'deadline_farm_cache')
        && render_overview_table_available($conn, 'deadline_farm_incidents')) {
        $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
        $cache = $conn->query('SELECT workers_json, checked_at, successful_at, health FROM deadline_farm_cache WHERE id=1')->fetch_assoc();
        if ($cache && $cache['checked_at']) {
            $payload['health'] = time() - (int) $cache['checked_at'] > 35 ? 'stale' : $cache['health'];
            $payload['observed_at'] = $cache['successful_at'] ? (int) $cache['successful_at'] : null;
            $payload['workers'] = json_decode($cache['workers_json'], true, 512, JSON_THROW_ON_ERROR);
            $payload['incidents'] = $conn->query(
                'SELECT id, worker, kind, opened_at, resolved_at, resolved_reason FROM deadline_farm_incidents
                 WHERE opened_at IS NOT NULL ORDER BY (resolved_at IS NULL) DESC, COALESCE(resolved_at,opened_at) DESC LIMIT 60'
            )->fetch_all(MYSQLI_ASSOC);
        } else {
            $payload['health'] = 'waiting';
        }
        $conn->commit();

        $jobs = array_values(array_unique(array_filter(array_column($payload['workers'], 'job_id'))));
        if ($jobs) {
            $markers = implode(',', array_fill(0, count($jobs), '?'));
            $stmt = $conn->prepare("SELECT deadline_job_id, previa_jpg FROM render_alta
                WHERE deadline_job_id IN ($markers) AND previa_jpg IS NOT NULL AND previa_jpg <> '' AND status <> 'Arquivado'
                ORDER BY data DESC, idrender_alta DESC");
            $stmt->bind_param(str_repeat('s', count($jobs)), ...$jobs);
            $stmt->execute();
            $previews = [];
            foreach ($stmt->get_result() as $row) {
                $previews[$row['deadline_job_id']] ??= basename($row['previa_jpg']);
            }
            foreach ($payload['workers'] as &$worker) {
                $worker['preview'] = $previews[$worker['job_id'] ?? ''] ?? null;
            }
            unset($worker);
        }
    } else {
        $payload['health'] = 'waiting';
    }
} catch (Throwable $error) {
    error_log('[Render Farm] ' . $error->getMessage());
    $payload['health'] = $payload['observed_at'] ? 'stale' : 'unavailable';
}
echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);

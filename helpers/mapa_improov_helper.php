<?php

require_once __DIR__ . '/mapa_espacial_helper.php';

function mi_coordinates(array $row): ?array
{
    try {
        $lat = mpe_coordinate($row['latitude'] ?? null, -90, 90, 'Latitude');
        $lng = mpe_coordinate($row['longitude'] ?? null, -180, 180, 'Longitude');
        return $lat === null || $lng === null ? null : [$lng, $lat];
    } catch (InvalidArgumentException $error) {
        return null;
    }
}

function mi_project(array $row, ?array $model, bool $canOpenViewer = true): array
{
    $id = (int) $row['idobra'];
    $status = (int) $row['status_obra'];
    $coordinates = mi_coordinates($row);
    return [
        'obra_id' => $id,
        'codigo' => (string) $row['nomenclatura'],
        'nome' => trim((string) ($row['nome_real'] ?? '')) ?: (string) $row['nome_obra'],
        'status_id' => $status,
        'status' => [0 => 'Ativo', 1 => 'Não ativo', 2 => 'Onboarding'][$status] ?? 'Status não informado',
        'localizacao' => trim((string) ($row['local'] ?? '')),
        'coordinates' => $coordinates,
        'has_glb' => $model !== null,
        'can_open_3d' => $model !== null && $canOpenViewer,
        'thumbnail' => null, // No dedicated, authorized cover exists in the current registry.
        'viewer_url' => $model && $canOpenViewer ? '../MapaPavimento/predio-3d/visualizador-glb-navegacao.php?' . http_build_query([
            'obra_id' => $id, 'modelo_id' => (int) $model['id'], 'origem' => 'mapa-improov',
        ]) : null,
    ];
}

/** Registry permissions and viewer permissions remain independently enforced by Flow. */
function mi_projects(mysqli $conn, array $allowed, array $viewerIds): array
{
    $ids = array_values(array_unique(array_map('intval', array_column($allowed, 'idobra'))));
    if (!$ids) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $rows = mp_rows($conn, "SELECT o.idobra,o.nomenclatura,o.nome_obra,o.nome_real,o.status_obra,o.local,
        e.latitude,e.longitude FROM obra o LEFT JOIN mapa_obra_configuracoes_espaciais e ON e.obra_id=o.idobra
        WHERE o.idobra IN ($marks) ORDER BY o.nomenclatura,o.idobra", $types, $ids);
    $models = mp_rows($conn, "SELECT obra_id,id FROM mapa_modelos_3d WHERE obra_id IN ($marks) ORDER BY obra_id,nome,id", $types, $ids);
    $first = [];
    foreach ($models as $model) {
        $first[(int) $model['obra_id']] ??= $model;
    }
    return array_map(static fn ($row) => mi_project($row, $first[(int) $row['idobra']] ?? null, in_array((int) $row['idobra'], $viewerIds, true)), $rows);
}

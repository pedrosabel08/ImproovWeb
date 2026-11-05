<?php

require_once __DIR__ . '/mapa_espacial_helper.php';

/** Inspect only a registered Google Earth URL; never resolve arbitrary URLs or project payloads. */
function flow_earth_url_parts(mixed $value): ?array
{
    if (!is_string($value)) {
        return null;
    }
    $url = trim($value);
    if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $parts = parse_url($url);
    if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== 'earth.google.com'
        || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
        return null;
    }
    $path = rawurldecode($parts['path'] ?? '');
    if (!str_starts_with($path, '/web/') || preg_match('/[\x00-\x1f\x7f]/', $path)) {
        return null;
    }
    return ['path' => $path];
}

/** Coordinates identify the view encoded in the link, not a cadastral/surveyed point. */
function flow_earth_url_coordinates(mixed $value): ?array
{
    $parts = flow_earth_url_parts($value);
    if (!$parts) {
        return null;
    }
    $number = '[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)';
    $count = preg_match_all('~(?:^|/)@(' . $number . '),(' . $number . ')(?=,|/|$)~', $parts['path'], $matches, PREG_SET_ORDER);
    if ($count !== 1) {
        return null;
    }
    try {
        return [
            'latitude' => mpe_coordinate($matches[0][1], -90, 90, 'Latitude'),
            'longitude' => mpe_coordinate($matches[0][2], -180, 180, 'Longitude'),
        ];
    } catch (InvalidArgumentException $error) {
        return null;
    }
}

/** Never replace a registered coordinate, even a partial/invalid pair needing human review. */
function flow_earth_import_decision(array $row, ?array $confirmedPoint = null): array
{
    $present = static fn ($v) => $v !== null && (!is_string($v) || trim($v) !== '');
    if ($present($row['latitude'] ?? null) || $present($row['longitude'] ?? null)) {
        return ['status' => 'coordenadas_preservadas'];
    }
    if (!flow_earth_url_parts($row['google_earth'] ?? null)) {
        return ['status' => 'link_invalido'];
    }
    $coordinates = flow_earth_url_coordinates($row['google_earth']);
    $source = 'coordenadas_da_url';
    if (!$coordinates && $confirmedPoint) {
        $coordinates = [
            'latitude' => mpe_coordinate($confirmedPoint['latitude'] ?? null, -90, 90, 'Latitude'),
            'longitude' => mpe_coordinate($confirmedPoint['longitude'] ?? null, -180, 180, 'Longitude'),
        ];
        if ($coordinates['latitude'] === null || $coordinates['longitude'] === null) {
            throw new InvalidArgumentException('Ponto confirmado incompleto.');
        }
        $source = 'ponto_confirmado_no_google_earth';
    }
    return $coordinates ? ['status' => 'pronto', 'origem' => $source, 'coordinates' => $coordinates] : ['status' => 'link_sem_coordenadas'];
}

<?php

/**
 * Version manager for SemVer-based releases.
 *
 * Storage (authoritative): cache/deploy_version.json
 * Fallback: cache/deploy_version.txt
 */

function improov_is_semver(string $value): bool
{
    return preg_match('/^\d+\.\d+\.\d+$/', $value) === 1;
}

function improov_parse_semver(string $value): array
{
    $parts = explode('.', $value);
    return [
        (int)($parts[0] ?? 0),
        (int)($parts[1] ?? 0),
        (int)($parts[2] ?? 0),
    ];
}

function improov_bump_semver(string $current, string $type): string
{
    if (!improov_is_semver($current)) {
        $current = '1.0.0';
    }

    [$major, $minor, $patch] = improov_parse_semver($current);

    if ($type === 'major') {
        $major += 1;
        $minor = 0;
        $patch = 0;
    } elseif ($type === 'minor') {
        $minor += 1;
        $patch = 0;
    } else {
        // patch (default)
        $patch += 1;
    }

    return $major . '.' . $minor . '.' . $patch;
}

function improov_version_paths(string $rootDir): array
{
    $cacheDir = rtrim($rootDir, '/\\') . DIRECTORY_SEPARATOR . 'cache';
    return [
        'cache_dir' => $cacheDir,
        'json' => $cacheDir . DIRECTORY_SEPARATOR . 'deploy_version.json',
        'txt' => $cacheDir . DIRECTORY_SEPARATOR . 'deploy_version.txt',
    ];
}

function improov_read_versions(string $rootDir): array
{
    $paths = improov_version_paths($rootDir);

    $app = 'dev';
    $asset = 'dev';

    if (is_file($paths['json'])) {
        $raw = @file_get_contents($paths['json']);
        if ($raw !== false) {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                $av = isset($data['app_version']) ? trim((string)$data['app_version']) : '';
                $sv = isset($data['asset_version']) ? trim((string)$data['asset_version']) : '';
                if ($av !== '') $app = $av;
                if ($sv !== '') $asset = $sv;
            }
        }
    }

    if ($app === 'dev' && is_file($paths['txt'])) {
        $raw = @file_get_contents($paths['txt']);
        if ($raw !== false) {
            $trim = trim($raw);
            if ($trim !== '') {
                $app = $trim;
                $asset = $trim;
            }
        }
    }

    if ($asset === 'dev') {
        $asset = $app;
    }

    return [
        'app_version' => $app,
        'asset_version' => $asset,
    ];
}

function improov_write_versions(string $rootDir, string $appVersion, string $assetVersion): bool
{
    $paths = improov_version_paths($rootDir);

    if (!is_dir($paths['cache_dir'])) {
        if (!mkdir($paths['cache_dir'], 0777, true) && !is_dir($paths['cache_dir'])) {
            return false;
        }
    }

    $payload = [
        'app_version' => $appVersion,
        'asset_version' => $assetVersion,
        'updated_at' => date('c'),
    ];

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    if (file_put_contents($paths['json'], $json . "\n") === false) {
        return false;
    }

    // Keep txt for backward compatibility (some scripts/tools might read it).
    @file_put_contents($paths['txt'], $assetVersion . "\n");

    return true;
}

/**
 * Bump version or set explicit SemVer.
 *
 * @param string $type major|minor|patch
 * @param string|null $explicitSemver If provided, takes precedence.
 */
function improov_bump_versions(string $rootDir, string $type = 'patch', ?string $explicitSemver = null): array
{
    $current = improov_read_versions($rootDir);

    if ($explicitSemver !== null) {
        $explicitSemver = trim($explicitSemver);
        if ($explicitSemver === '' || !improov_is_semver($explicitSemver)) {
            return [
                'ok' => false,
                'message' => 'Versao invalida. Use o formato 1.2.3.',
                'app_version' => $current['app_version'],
                'asset_version' => $current['asset_version'],
            ];
        }

        $next = $explicitSemver;
    } else {
        $type = strtolower(trim($type));
        if (!in_array($type, ['major', 'minor', 'patch'], true)) {
            $type = 'patch';
        }
        $next = improov_bump_semver((string)$current['app_version'], $type);
    }

    // For simplicity and to guarantee cache-busting, we set asset_version == app_version.
    $ok = improov_write_versions($rootDir, $next, $next);

    return [
        'ok' => $ok,
        'message' => $ok ? 'Versao atualizada: ' . $next : 'Falha ao salvar arquivo de versao.',
        'app_version' => $next,
        'asset_version' => $next,
    ];
}

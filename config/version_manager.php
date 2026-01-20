<?php

/**
 * Version manager for SemVer-based releases.
 *
 * Storage (authoritative): database table versionamentos
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

function improov_read_versions(string $rootDir): array
{
    $app = 'dev';
    $asset = 'dev';

    if (is_file(__DIR__ . '/db_version.php')) {
        require_once __DIR__ . '/db_version.php';
        $conn = improov_db_version_connect();
        if ($conn instanceof mysqli) {
            $res = $conn->query("SELECT versao FROM versionamentos ORDER BY criado_em DESC, id DESC LIMIT 1");
            if ($res && ($row = $res->fetch_assoc())) {
                $dbVersion = trim((string)($row['versao'] ?? ''));
                if ($dbVersion !== '') {
                    $app = $dbVersion;
                    $asset = $dbVersion;
                }
            }
            $conn->close();
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
    // No file storage. Database is the single source of truth.
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

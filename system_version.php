<?php
// Prefer the database (versionamentos) as authoritative for system_version.
// Falls back to version_manager / config/version.php when necessary.
header('Content-Type: application/json; charset=utf-8');

// Garantir que essa resposta nunca fique em cache (proxy/CDN/navegador)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: Cookie');

$assetVersion = null;
$appVersion = null;

// Try database first
if (is_file(__DIR__ . '/conexao.php')) {
    require_once __DIR__ . '/conexao.php';
    if (isset($conn) && $conn instanceof mysqli) {
        $sql = "SELECT versao FROM versionamentos ORDER BY criado_em DESC, id DESC LIMIT 1";
        $res = $conn->query($sql);
        if ($res && ($row = $res->fetch_assoc())) {
            $dbVersion = trim((string)($row['versao'] ?? ''));
            if ($dbVersion !== '') {
                $appVersion = $dbVersion;
                $assetVersion = $dbVersion;
            }
        }
    }
}

// Try version_manager first (reads deploy_version.json / deploy_version.txt)
if (($assetVersion === null || $appVersion === null) && is_file(__DIR__ . '/config/version_manager.php')) {
    require_once __DIR__ . '/config/version_manager.php';
    $v = improov_read_versions(__DIR__);
    if (is_array($v)) {
        $assetVersion = $v['asset_version'] ?? null;
        $appVersion = $v['app_version'] ?? null;
    }
}

// Fallback to legacy single-file config if present
if (($assetVersion === null || $appVersion === null) && is_file(__DIR__ . '/config/version.php')) {
    require_once __DIR__ . '/config/version.php';
    if (defined('ASSET_VERSION') && defined('APP_VERSION')) {
        $assetVersion = $assetVersion ?? ASSET_VERSION;
        $appVersion = $appVersion ?? APP_VERSION;
    }
}

// Final fallback values
if ($assetVersion === null) $assetVersion = 'dev';
if ($appVersion === null) $appVersion = 'dev';

// Sync cache files to avoid ?v=dev when assets use version.php
if ($appVersion !== 'dev') {
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }
    $jsonPath = $cacheDir . '/deploy_version.json';
    $txtPath = $cacheDir . '/deploy_version.txt';

    $payload = [
        'app_version' => (string)$appVersion,
        'asset_version' => (string)$assetVersion,
        'updated_at' => date('c'),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json !== false) {
        @file_put_contents($jsonPath, $json . "\n");
    }
    @file_put_contents($txtPath, (string)$assetVersion . "\n");
}

echo json_encode([
    'ok' => true,
    // Used by the sidebar auto-reload. This should change whenever assets must be refreshed.
    'version' => (string)$assetVersion,
    // Human-friendly SemVer (ex.: 1.2.3)
    'app_version' => (string)$appVersion,
    'ts' => time(),
]);
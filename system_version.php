<?php
// Prefer the database (versionamentos) as authoritative for system_version.
header('Content-Type: application/json; charset=utf-8');

// Garantir que essa resposta nunca fique em cache (proxy/CDN/navegador)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: Cookie');

require_once __DIR__ . '/config/version.php';

$assetVersion = defined('ASSET_VERSION') ? ASSET_VERSION : 'dev';
$appVersion = defined('APP_VERSION') ? APP_VERSION : 'dev';

echo json_encode([
    'ok' => true,
    // Used by the sidebar auto-reload. This should change whenever assets must be refreshed.
    'version' => (string)$assetVersion,
    // Human-friendly SemVer (ex.: 1.2.3)
    'app_version' => (string)$appVersion,
    'ts' => time(),
]);
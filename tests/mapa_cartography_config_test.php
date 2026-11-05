<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/mapa_improov.php';
improov_load_env_once();
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) throw new RuntimeException($label);
};
putenv('FLOW_MAP_DEM_URL=https://tiles.mapterhorn.com/tilejson.json');
putenv('FLOW_MAP_DEM_ENCODING=mapbox');
putenv('FLOW_MAP_DEM_TILE_SIZE=256');
$config = mi_map_config();
$check($config['demEncoding'] === 'terrarium' && $config['demTileSize'] === 512, 'Old example settings must not corrupt the default DEM');
putenv('FLOW_MAP_DEM_URL=https://example.org/terrain.json');
putenv('FLOW_MAP_DEM_ATTRIBUTION=Custom provider');
$config = mi_map_config();
$check($config['demEncoding'] === 'mapbox' && $config['demTileSize'] === 256, 'Preserve explicitly configured legacy providers');
$check($config['demAttribution'] === 'Custom provider', 'Keep the configured provider credit');
foreach (['http://example.org/tiles', 'https://user:secret@example.org/tiles', 'javascript:alert(1)'] as $invalid) {
    putenv('FLOW_MAP_IMAGERY_URL=' . $invalid);
    $rejected = false;
    try { mi_map_config(); } catch (RuntimeException $error) { $rejected = true; }
    $check($rejected, 'Only public credential-free HTTPS imagery is allowed');
}
putenv('FLOW_MAP_IMAGERY_URL=https://example.org/{z}/{y}/{x}.jpeg');
$check(str_contains(mi_map_config()['imagery'], '{y}/{x}'), 'Retain WMTS row/column templates');
echo "Cartographic config: $checks checks passed.\n";

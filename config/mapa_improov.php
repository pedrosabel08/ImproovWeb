<?php

require_once __DIR__ . '/secure_env.php';

/** Only public browser configuration. Never pass server credentials to this module. */
function mi_map_config(): array
{
    $url = static function (string $key, string $fallback = ''): string {
        $value = trim(improov_env($key, $fallback));
        if ($value !== '' && (!filter_var($value, FILTER_VALIDATE_URL) || parse_url($value, PHP_URL_SCHEME) !== 'https'
            || parse_url($value, PHP_URL_USER) !== null || parse_url($value, PHP_URL_PASS) !== null)) {
            throw new RuntimeException('Configuração cartográfica inválida.');
        }
        return $value;
    };
    $dem = $url('FLOW_MAP_DEM_URL', 'https://tiles.mapterhorn.com/tilejson.json');
    $mapterhorn = $dem === 'https://tiles.mapterhorn.com/tilejson.json';
    return [
        'style' => $url('FLOW_MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/dark'),
        'imagery' => $url('FLOW_MAP_IMAGERY_URL', 'https://gibs.earthdata.nasa.gov/wmts/epsg3857/best/BlueMarble_NextGeneration/default/GoogleMapsCompatible_Level8/{z}/{y}/{x}.jpeg'),
        'dem' => $dem,
        // Mapterhorn's wire format is fixed, including on installations that
        // still have the previous example's unused ENCODING=mapbox setting.
        'demEncoding' => $mapterhorn ? 'terrarium' : (improov_env('FLOW_MAP_DEM_ENCODING', 'mapbox') === 'terrarium' ? 'terrarium' : 'mapbox'),
        'demTileSize' => $mapterhorn ? 512 : ((int) improov_env('FLOW_MAP_DEM_TILE_SIZE', '256') === 512 ? 512 : 256),
        'demAttribution' => strip_tags(improov_env('FLOW_MAP_DEM_ATTRIBUTION', $mapterhorn ? '© Mapterhorn · Copernicus DEM GLO-30' : '')),
    ];
}

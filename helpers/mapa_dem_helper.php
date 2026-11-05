<?php

/** Public Mapterhorn Terrarium tiles, restricted to the authorised work's 2 km context. */
const FLOW_DEM_ZOOM = 12;
const FLOW_DEM_CACHE_GUARD = "<?php http_response_code(404); exit; ?>\n";

function flow_dem_pixel(float $lat, float $lon): array
{
    $size = 512 * (2 ** FLOW_DEM_ZOOM);
    return [($lon + 180) / 360 * $size, (1 - asinh(tan(deg2rad($lat))) / M_PI) / 2 * $size];
}

function flow_dem_tiles(array $config): array
{
    $lat = $config['latitude'] ?? null;
    $lon = $config['longitude'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lon) || !is_finite((float)$lat) || !is_finite((float)$lon) || abs((float)$lat) >= 84 || abs((float)$lon) > 180) {
        throw new InvalidArgumentException('Localização DEM indisponível.');
    }
    // Include one pixel on each side for bilinear sampling across tile boundaries.
    $dlat = 1005 / 111195.0802;
    $dlon = $dlat / cos(deg2rad((float)$lat));
    [$left, $top] = flow_dem_pixel($lat + $dlat, $lon - $dlon);
    [$right, $bottom] = flow_dem_pixel($lat - $dlat, $lon + $dlon);
    $tiles = [];
    $n = 2 ** FLOW_DEM_ZOOM;
    for ($x = (int)floor(($left - 1) / 512); $x <= (int)floor(($right + 1) / 512); $x++) {
        for ($y = (int)floor(($top - 1) / 512); $y <= (int)floor(($bottom + 1) / 512); $y++) {
            if ($y >= 0 && $y < $n) {
                $tiles[] = [(($x % $n) + $n) % $n, $y];
            }
        }
    }
    if (count($tiles) > 16) {
        throw new RuntimeException('Área DEM excede o limite.', 503);
    }
    return $tiles;
}

function flow_dem_tile(array $config, int $x, int $y, ?string $cacheDirectory = null): string
{
    if (!in_array([$x, $y], flow_dem_tiles($config), true)) {
        throw new RuntimeException('Tile fora da região da obra.', 403);
    }
    $dir = $cacheDirectory ?? __DIR__ . '/../cache/dem';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Cache DEM indisponível.', 503);
    }
    $file = $dir . '/' . FLOW_DEM_ZOOM . "-$x-$y.webp.php";
    $read = static function () use ($file): ?string {
        $raw = @file_get_contents($file);
        if ($raw === false || !str_starts_with($raw, FLOW_DEM_CACHE_GUARD)) {
            return null;
        }
        $body = substr($raw, strlen(FLOW_DEM_CACHE_GUARD));
        return strlen($body) > 12 && substr($body, 0, 4) === 'RIFF' && substr($body, 8, 4) === 'WEBP' ? $body : null;
    };
    $cached = is_file($file) ? $read() : null;
    if ($cached !== null && filemtime($file) > time() - 30 * 86400) {
        return $cached;
    }
    $lock = @fopen($file . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        if ($lock) {
            fclose($lock);
        }
        if ($cached !== null) {
            return $cached;
        }
        throw new RuntimeException('Aquisição DEM em andamento.', 503);
    }
    try {
        // Prevent repeated failures from hammering the free endpoint.
        if (is_file($file . '.retry') && filemtime($file . '.retry') > time() - 300) {
            if ($cached !== null) {
                return $cached;
            }
            throw new RuntimeException('DEM temporariamente indisponível.', 503);
        }
        $body = '';
        $handle = curl_init('https://tiles.mapterhorn.com/' . FLOW_DEM_ZOOM . "/$x/$y.webp");
        curl_setopt_array($handle, [CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Flow-Terrain/1.0', CURLOPT_HTTPHEADER => ['Accept: image/webp'],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 2000000) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            }]);
        $ok = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        $image = @getimagesizefromstring($body);
        if ($ok === false || $status !== 200 || ($image[0] ?? 0) !== 512 || ($image[1] ?? 0) !== 512 || ($image['mime'] ?? '') !== 'image/webp') {
            @touch($file . '.retry');
            if ($cached !== null) {
                return $cached;
            }
            throw new RuntimeException('Fonte DEM indisponível; mantendo terreno plano.', 503);
        }
        $temp = tempnam($dir, 'dem-');
        if ($temp === false) {
            throw new RuntimeException('Cache DEM indisponível.', 503);
        }
        try {
            if (file_put_contents($temp, FLOW_DEM_CACHE_GUARD . $body) === false || !rename($temp, $file)) {
                throw new RuntimeException('Falha ao salvar cache DEM.', 503);
            }
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
        return $body;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

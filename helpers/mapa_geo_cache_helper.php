<?php

require_once __DIR__ . '/../config/secure_env.php';
require_once __DIR__ . '/mapa_dem_helper.php';
require_once __DIR__ . '/mapa_osm_helper.php';

const FLOW_GEO_CACHE_LIMIT = 68157440;
const FLOW_GEO_CACHE_LEASE = 120;

function flow_geo_cache_enabled(): bool
{
    return improov_env('FLOW_GEO_CACHE_ENABLED', '0') === '1';
}

function flow_geo_cache_inputs(array $config, string $model, ?string $sourceRoot = null): ?array
{
    if (!isset($config['latitude'], $config['longitude'], $config['norte_graus'])) return null;
    $base = dirname(__DIR__);
    $sourceRoot ??= "$base/cache";
    $osmKey = hash('sha256', sprintf('v2:%.7F:%.7F:1000', $config['latitude'], $config['longitude']));
    $osmPath = "$sourceRoot/flow-osm/$osmKey.json.php";
    $osmBytes = @file_get_contents($osmPath);
    if (!is_string($osmBytes) || !str_starts_with($osmBytes, FLOW_OSM_CACHE_GUARD)) return null;
    $osm = json_decode(substr($osmBytes, strlen(FLOW_OSM_CACHE_GUARD)), true);
    if (empty($osm['payload']['elements']) || ($osm['saved_at'] ?? 0) <= time() - 7 * 86400) return null;
    $tiles = [];
    foreach (flow_dem_tiles($config) as [$x, $y]) {
        $path = "$sourceRoot/dem/12-$x-$y.webp.php";
        $mtime = @filemtime($path);
        if (!$mtime || $mtime <= time() - 30 * 86400 || !($hash = @hash_file('sha256', $path))) return null;
        $tiles["$x-$y"] = ['path' => $path, 'sha256' => $hash];
    }
    if (@filesize($model) > 256 * 1024 * 1024) return null;
    $modelHash = @hash_file('sha256', $model);
    if (!$modelHash) return null;
    // Fingerprint the geometry code, including transitive dependencies and the worker.
    $code = hash_init('sha256');
    $files = array_merge(
        glob("$base/MapaPavimento/predio-3d/geo/*.js"),
        glob("$base/MapaPavimento/predio-3d/geo/vendor/*.js"),
        array_map(static fn($name) => "$base/MapaPavimento/predio-3d/$name", ['floor-visibility.js', 'model-scene.js', 'north-direction.js']),
        ["$base/tools/geo-cache/worker.mjs", "$base/tools/geo-cache/package-lock.json"]
    );
    foreach ($files as $file) if (!hash_update_file($code, $file)) return null;
    $key = hash('sha256', json_encode([
        1, hash_final($code), $modelHash, $config['latitude'], $config['longitude'], $config['norte_graus'],
        hash('sha256', json_encode($osm['payload'], JSON_THROW_ON_ERROR)),
        array_map(static fn($tile) => $tile['sha256'], $tiles),
    ], JSON_THROW_ON_ERROR));
    return [
        'key' => $key, 'config' => $config, 'model' => ['path' => $model, 'sha256' => $modelHash],
        'osm' => ['path' => $osmPath, 'sha256' => hash('sha256', $osmBytes)], 'tiles' => $tiles,
    ];
}

function flow_geo_cache_atomic_json(string $path, array $data): bool
{
    $temp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
    try {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        return file_put_contents($temp, $json) === strlen($json) && rename($temp, $path);
    } finally {
        if (is_file($temp)) @unlink($temp);
    }
}

function flow_geo_cache_attempt(string $dir, string $key): ?array
{
    $attempt = json_decode((string) @file_get_contents("$dir/$key.json"), true);
    if (!is_array($attempt) || !preg_match('/^[a-f0-9]{24}$/D', $attempt['id'] ?? '')) return null;
    return ['path' => "$dir/$key-{$attempt['id']}", 'time' => (int) ($attempt['time'] ?? 0)];
}

function flow_geo_cache_read(?array $attempt, string $key, ?string $known = null): ?array
{
    if (!$attempt) return null;
    $path = $attempt['path'];
    $manifest = json_decode((string) @file_get_contents("$path/manifest.json"), true);
    if (!is_array($manifest) || ($manifest['format'] ?? null) !== 1 || ($manifest['key'] ?? '') !== $key) return null;
    $size = $manifest['bytes'] ?? 0;
    if (!is_int($size) || $size < 5 || $size > FLOW_GEO_CACHE_LIMIT ||
        !preg_match('/^[a-f0-9]{64}$/D', $manifest['sha256'] ?? '') ||
        @filesize("$path/geometry.bin") !== $size) return null;
    $metadata = ['format' => 1, 'size' => $size, 'sha256' => $manifest['sha256'],
        'serialization_ms' => (float) ($manifest['serializationMs'] ?? 0)];
    if ($known === $metadata['sha256']) return $metadata;
    $bytes = @file_get_contents("$path/geometry.bin", false, null, 0, $size + 1);
    if (!is_string($bytes) || strlen($bytes) !== $size || hash('sha256', $bytes) !== ($manifest['sha256'] ?? '')) return null;
    return $metadata + ['bytes' => $bytes];
}

function flow_geo_cache_node(?string $platform = null): string
{
    $platform ??= PHP_OS_FAMILY;
    $variable = $platform === 'Windows' ? 'FLOW_GEO_CACHE_NODE_WINDOWS' : 'FLOW_GEO_CACHE_NODE_LINUX';
    $specific = improov_env($variable, '');
    if ($specific !== '') return $specific;
    $shared = improov_env('FLOW_GEO_CACHE_NODE', '');
    if ($shared !== '') return $shared;
    $default = $platform === 'Windows' ? 'C:/Program Files/nodejs/node.exe' : '/opt/node24/bin/node';
    return is_file($default) ? $default : 'node';
}

function flow_geo_cache_launch(string $job): bool
{
    $node = flow_geo_cache_node();
    $command = [$node, dirname(__DIR__) . '/tools/geo-cache/launch.mjs', 'launch', $job];
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $process = @proc_open($command, [['file', $null, 'r'], ['file', $null, 'a'], ['file', $null, 'a']], $pipes, null, null,
        ['bypass_shell' => true, 'create_no_window' => true]);
    if (!is_resource($process)) return false;
    $deadline = microtime(true) + 0.8;
    do {
        $status = proc_get_status($process);
        if (!$status['running']) {
            proc_close($process);
            return $status['exitcode'] === 0;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    proc_terminate($process);
    proc_close($process);
    return false;
}

function flow_geo_cache_clean(string $dir, int $now): void
{
    foreach (glob("$dir/*") as $path) {
        $name = basename($path);
        if ((int) @filemtime($path) > $now - 14 * 86400) continue;
        if (preg_match('/^[a-f0-9]{64}\.json$/D', $name)) @unlink($path);
        if (!preg_match('/^[a-f0-9]{64}-[a-f0-9]{24}$/D', $name) || !is_dir($path) || is_link($path)) continue;
        foreach (['job.json', 'geometry.bin', 'geometry.tmp', 'manifest.json', 'manifest.tmp', 'failed'] as $file) {
            if (is_file("$path/$file")) @unlink("$path/$file");
        }
        @rmdir($path);
    }
}

function flow_geo_cache_resolve(array $job, ?string $directory = null, ?callable $launch = null, ?string $known = null): ?array
{
    $dir = $directory ?? dirname(__DIR__) . '/cache/flow-geometry';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return null;
    $key = $job['key'];
    $attempt = flow_geo_cache_attempt($dir, $key);
    if ($ready = flow_geo_cache_read($attempt, $key, $known)) return $ready;

    // Hold this lock only while checking/claiming the job, never during Node generation.
    $lock = @fopen("$dir/queue.lock", 'c');
    if (!$lock) return null;
    try {
        if (!flock($lock, LOCK_EX | LOCK_NB)) return null;
        $attempt = flow_geo_cache_attempt($dir, $key);
        if ($ready = flow_geo_cache_read($attempt, $key, $known)) return $ready;
        $now = time();
        if ($attempt && $attempt['time'] > $now - FLOW_GEO_CACHE_LEASE && !is_file($attempt['path'] . '/manifest.json')) return null;
        $active = 0;
        foreach (glob("$dir/*.json") as $pointer) {
            $other = flow_geo_cache_attempt($dir, basename($pointer, '.json'));
            if ($other && $other['time'] > $now - FLOW_GEO_CACHE_LEASE &&
                !is_file($other['path'] . '/manifest.json') && !is_file($other['path'] . '/failed') && ++$active >= 2) return null;
        }
        if (random_int(1, 20) === 1) flow_geo_cache_clean($dir, $now);
        $id = bin2hex(random_bytes(12));
        $path = "$dir/$key-$id";
        if (!@mkdir($path, 0700) || !flow_geo_cache_atomic_json("$path/job.json", $job) ||
            !flow_geo_cache_atomic_json("$dir/$key.json", ['id' => $id, 'time' => $now])) return null;
    } finally {
        fclose($lock);
    }
    ($launch ?? 'flow_geo_cache_launch')("$path/job.json");
    return null;
}

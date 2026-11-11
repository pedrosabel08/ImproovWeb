<?php
require __DIR__ . '/../helpers/mapa_geo_cache_helper.php';

function check(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
}
$dir = sys_get_temp_dir() . '/flow-geometry-test-' . bin2hex(random_bytes(8));
$key = str_repeat('a', 64);
$job = ['key' => $key];
$calls = 0;
$launch = static function ($path) use (&$calls, $job): bool {
    $calls++;
    check(json_decode(file_get_contents($path), true) === $job, 'Complete job before launch');
    return false;
};
try {
    check(!flow_geo_cache_enabled(), 'Disabled by default');
    putenv('FLOW_GEO_CACHE_NODE_WINDOWS=C:/Program Files/nodejs/node.exe');
    putenv('FLOW_GEO_CACHE_NODE_LINUX=/opt/node24/bin/node');
    putenv('FLOW_GEO_CACHE_NODE=legacy-node');
    check(flow_geo_cache_node('Windows') === 'C:/Program Files/nodejs/node.exe', 'Windows uses its configured executable');
    check(flow_geo_cache_node('Linux') === '/opt/node24/bin/node', 'Linux uses its configured executable');
    putenv('FLOW_GEO_CACHE_NODE_LINUX');
    check(flow_geo_cache_node('Linux') === 'legacy-node', 'Legacy executable remains supported');
    putenv('FLOW_GEO_CACHE_NODE_WINDOWS');
    putenv('FLOW_GEO_CACHE_NODE');
    check(flow_geo_cache_resolve($job, $dir, $launch) === null && $calls === 1, 'Cold miss enqueues');
    check(flow_geo_cache_resolve($job, $dir, $launch) === null && $calls === 1, 'Pending lease deduplicates');
    $attempt = flow_geo_cache_attempt($dir, $key);
    $binary = 'test binary data';
    file_put_contents($attempt['path'] . '/geometry.bin', $binary);
    check(flow_geo_cache_resolve($job, $dir, $launch) === null, 'Incomplete publish invisible');
    $manifest = ['format' => 1, 'key' => $key, 'bytes' => strlen($binary), 'sha256' => hash('sha256', $binary)];
    flow_geo_cache_atomic_json($attempt['path'] . '/manifest.json', $manifest);
    check(flow_geo_cache_resolve($job, $dir, $launch)['bytes'] === $binary && $calls === 1, 'Published cache hit');
    $known = flow_geo_cache_resolve($job, $dir, $launch, $manifest['sha256']);
    check(!isset($known['bytes']) && $known['size'] === strlen($binary), 'Matching authorized manifest omits the binary');
    file_put_contents($attempt['path'] . '/geometry.bin', strrev($binary));
    check(flow_geo_cache_resolve($job, $dir, $launch) === null && $calls === 2, 'Same-length corruption triggers new attempt');
    $pointer = json_decode(file_get_contents("$dir/$key.json"), true);
    $pointer['time'] = time() - FLOW_GEO_CACHE_LEASE - 1;
    flow_geo_cache_atomic_json("$dir/$key.json", $pointer);
    check(flow_geo_cache_resolve($job, $dir, $launch) === null && $calls === 3, 'Failed job is retried after lease');
    $lock = fopen("$dir/queue.lock", 'c');
    flock($lock, LOCK_EX);
    check(flow_geo_cache_resolve(['key' => str_repeat('b', 64)], $dir, $launch) === null && $calls === 3, 'Concurrent claim fails open');
    fclose($lock);
    foreach (glob("$dir/*") as $path) touch($path, time() - 15 * 86400);
    flow_geo_cache_clean($dir, time());
    check(count(glob("$dir/*")) === 1 && is_file("$dir/queue.lock"), 'Expired attempts removed safely');
    $sources = "$dir/sources";
    mkdir($sources);
    mkdir("$sources/flow-osm");
    mkdir("$sources/dem");
    $config = ['obra_id' => 1, 'latitude' => 0.0, 'longitude' => 0.0, 'norte_graus' => 0.0];
    $osm = "$sources/flow-osm/" . hash('sha256', 'v2:0.0000000:0.0000000:1000') . '.json.php';
    $model = "$sources/model.glb";
    $tiles = [];
    try {
        file_put_contents($model, 'fixture GLB version 1');
        file_put_contents($osm, FLOW_OSM_CACHE_GUARD . json_encode(['saved_at' => time(), 'payload' => ['elements' => [1]]]));
        foreach (flow_dem_tiles($config) as [$x, $y]) {
            $tile = "$sources/dem/12-$x-$y.webp.php";
            $tiles[] = $tile;
            file_put_contents($tile, FLOW_DEM_CACHE_GUARD . 'fixture elevation tile');
        }
        $key1 = flow_geo_cache_inputs($config, $model, $sources)['key'];
        file_put_contents($osm, FLOW_OSM_CACHE_GUARD . json_encode(['saved_at' => time(), 'payload' => ['elements' => [2]]]));
        $key2 = flow_geo_cache_inputs($config, $model, $sources)['key'];
        file_put_contents($tiles[0], FLOW_DEM_CACHE_GUARD . 'new elevation tile');
        $key3 = flow_geo_cache_inputs($config, $model, $sources)['key'];
        file_put_contents($model, 'fixture GLB version 2');
        $key4 = flow_geo_cache_inputs($config, $model, $sources)['key'];
        check(count(array_unique([$key1, $key2, $key3, $key4])) === 4, 'OSM, DEM and GLB content changes invalidate the key');
    } finally {
        foreach ($tiles as $tile) unlink($tile);
        unlink($osm);
        unlink($model);
        rmdir("$sources/flow-osm");
        rmdir("$sources/dem");
        rmdir($sources);
    }
    echo "Geo cache queue, leases, integrity and cleanup passed.\n";
} finally {
    if (is_dir($dir)) {
        foreach (glob("$dir/*") as $path) {
            if (is_dir($path)) {
                foreach (glob("$path/*") as $file) unlink($file);
                rmdir($path);
            } else unlink($path);
        }
        rmdir($dir);
    }
}

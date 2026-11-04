<?php
/** OSM acquisition only. Coordinates come from the authorised work's saved settings. */
const FLOW_OSM_ENDPOINT = 'https://maps.mail.ru/osm/tools/overpass/api/interpreter';
const FLOW_OSM_CACHE_GUARD = "<?php http_response_code(404); exit; ?>\n";
const FLOW_OSM_MAX_BYTES = 12000000;
class FlowOsmUpstreamException extends RuntimeException { public int $retryAfter = 0; }

function flow_osm_query(float $latitude, float $longitude, int $radius = 1000): string
{
    if (!is_finite($latitude) || abs($latitude) >= 89 || !is_finite($longitude) || abs($longitude) > 180 || $radius < 100 || $radius > 1500) {
        throw new InvalidArgumentException('Localização OSM inválida.');
    }
    $latDelta = ($radius + 5) / 111195.0802;
    $lonDelta = $latDelta / cos(deg2rad($latitude));
    $bbox = '(' . implode(',', array_map(static fn($n) => json_encode($n, JSON_THROW_ON_ERROR), [
        $latitude - $latDelta, $longitude - $lonDelta, $latitude + $latDelta, $longitude + $lonDelta,
    ])) . ')';
    $filters = ['[building][building!="no"]', '[highway]', '[natural="water"]', '[water]', '[natural="coastline"]', '[waterway]', '[landuse="reservoir"]', '[leisure="park"]', '[landuse~"^(forest|grass)$"]', '[natural~"^(wood|grassland)$"]'];
    $query = '[out:json][timeout:25];(';
    foreach ($filters as $filter) $query .= "way$filter$bbox;relation[type=\"multipolygon\"]$filter$bbox;";
    return $query . ');out geom;';
}

/** Remove names/POI fields before storing or returning the public OSM geometry. */
function flow_osm_payload(array $payload): array
{
    if (!isset($payload['elements']) || !is_array($payload['elements']) || !empty($payload['remark'])) {
        throw new RuntimeException('Resposta Overpass incompleta.', 502);
    }
    $tags = array_flip(['type', 'building', 'height', 'building:levels', 'highway', 'area', 'natural', 'water', 'waterway', 'landuse', 'leisure']);
    $elements = [];
    foreach ($payload['elements'] as $element) {
        if (!is_array($element) || !in_array($element['type'] ?? '', ['way', 'relation'], true) || !isset($element['id']) || !is_numeric($element['id'])) {
            throw new RuntimeException('Resposta Overpass inválida.', 502);
        }
        if (($element['type'] === 'way' && !is_array($element['geometry'] ?? null)) ||
            ($element['type'] === 'relation' && !is_array($element['members'] ?? null))) {
            throw new RuntimeException('Geometria Overpass incompleta.', 502);
        }
        $clean = array_intersect_key($element, array_flip(['id', 'type', 'geometry']));
        $clean['tags'] = array_intersect_key(is_array($element['tags'] ?? null) ? $element['tags'] : [], $tags);
        if (isset($element['members']) && is_array($element['members'])) {
            $clean['members'] = array_map(static fn($member) => array_intersect_key($member, array_flip(['type', 'ref', 'role', 'geometry'])), array_filter($element['members'], 'is_array'));
            $clean['members'] = array_values($clean['members']);
        }
        $elements[] = $clean;
    }
    return ['elements' => $elements];
}

function flow_osm_download(string $query): array
{
    if (!extension_loaded('curl')) throw new RuntimeException('Aquisição OSM indisponível.', 503);
    $body = ''; $retryAfter = 0; $started = microtime(true);
    $handle = curl_init(FLOW_OSM_ENDPOINT);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['data' => $query]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_USERAGENT => 'Flow-OSM-PoC/1.0',
        CURLOPT_CONNECTTIMEOUT => 7,
        CURLOPT_TIMEOUT => 28,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$retryAfter): int {
            if (preg_match('/^Retry-After:\s*(.*?)\s*$/i', $line, $match)) {
                $value = trim($match[1]);
                $retryAfter = ctype_digit($value) ? (int)$value : max(0, (strtotime($value) ?: time()) - time());
            }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > FLOW_OSM_MAX_BYTES) return 0;
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($handle);
    curl_close($handle);
    flow_osm_log('upstream', ['http'=>$status, 'curl'=>$curlError, 'duration_ms'=>(int)((microtime(true)-$started)*1000)]);
    if ($ok === false || $status !== 200) {
        error_log("Flow OSM: aquisição indisponível (HTTP $status, cURL $curlError).");
        $error = new FlowOsmUpstreamException('Overpass temporariamente indisponível.', 503);
        $error->retryAfter = $retryAfter;
        throw $error;
    }
    try { $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR); }
    catch (Throwable $error) { throw new RuntimeException('Resposta Overpass inválida.', 502); }
    if (!is_array($payload)) throw new RuntimeException('Resposta Overpass inválida.', 502);
    return flow_osm_payload($payload);
}

function flow_osm_cache_read(string $path): ?array
{
    if (!is_file($path) || filesize($path) > FLOW_OSM_MAX_BYTES) return null;
    $body = @file_get_contents($path);
    if (!is_string($body) || !str_starts_with($body, FLOW_OSM_CACHE_GUARD)) return null;
    $entry = json_decode(substr($body, strlen(FLOW_OSM_CACHE_GUARD)), true);
    return is_array($entry) ? $entry : null;
}

function flow_osm_cache_write(string $path, array $entry): void
{
    // The PHP guard makes a direct web request exit with 404, without exposing the cache.
    $encoded = FLOW_OSM_CACHE_GUARD . json_encode($entry, JSON_THROW_ON_ERROR);
    if (strlen($encoded) > FLOW_OSM_MAX_BYTES) throw new RuntimeException('Resposta OSM excede o limite de cache.', 502);
    $temp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp.php';
    try {
        if (file_put_contents($temp, $encoded, LOCK_EX) !== strlen($encoded) || !@rename($temp, $path)) throw new RuntimeException('Cache OSM indisponível.', 503);
    } finally { if (is_file($temp)) @unlink($temp); }
}

function flow_osm_log(string $event, array $details = []): void { error_log(json_encode(['event'=>'osm_'.$event] + $details)); }
function flow_osm_setting(string $name, int $default, int $min, int $max): int {
    $value = getenv($name);
    return $value !== false && ctype_digit($value) ? max($min, min($max, (int)$value)) : $default;
}
function flow_osm_lock(string $path) {
    $handle = @fopen($path, 'c+');
    if (!$handle) return null;
    if (!flock($handle, LOCK_EX | LOCK_NB)) { fclose($handle); return null; }
    if (fstat($handle)['size'] === 0) { fwrite($handle, FLOW_OSM_CACHE_GUARD); fflush($handle); }
    return $handle;
}
function flow_osm_unlock($handle): void { flock($handle, LOCK_UN); fclose($handle); }

/** Remove only expired entries; the quota is soft while entries remain eligible for fallback. */
function flow_osm_cleanup(string $directory, int $now): void {
    $maintenance = flow_osm_lock($directory . '/maintenance.lock.php');
    if (!$maintenance) return;
    try {
        $limit = flow_osm_setting('FLOW_OSM_CACHE_MAX_BYTES', 268435456, 12000000, 2147483647);
        $total = 0;
        foreach (glob($directory . '/*.json.php') ?: [] as $file) {
            clearstatcache(true, $file); $size = @filesize($file) ?: 0; $total += $size;
            $key = basename($file, '.json.php');
            if (!preg_match('/^[a-f0-9]{64}$/D', $key)) continue;
            $lock = flow_osm_lock($directory . '/' . $key . '.lock.php');
            if (!$lock) continue;
            try {
                $entry = flow_osm_cache_read($file); $saved = (int)($entry['saved_at'] ?? 0);
                if (($saved <= 0 || $now - $saved >= 30 * 86400) && @unlink($file)) {
                    $total -= $size; @unlink($directory . '/' . $key . '.error.php');
                }
            } finally { flow_osm_unlock($lock); }
        }
        if ($total > $limit) flow_osm_log('quota_exceeded', ['bytes'=>$total, 'limit'=>$limit]);
    } finally { flow_osm_unlock($maintenance); }
}
function flow_osm_store_payload(string $directory, string $path, array $entry): void {
    $maintenance = flow_osm_lock($directory . '/maintenance.lock.php');
    if (!$maintenance) throw new RuntimeException('Cache OSM ocupado.', 503);
    try {
        $total = 0;
        foreach (glob($directory . '/*.json.php') ?: [] as $file) { clearstatcache(true, $file); $total += @filesize($file) ?: 0; }
        clearstatcache(true, $path);
        $old = is_file($path) ? (@filesize($path) ?: 0) : 0;
        $size = strlen(FLOW_OSM_CACHE_GUARD . json_encode($entry, JSON_THROW_ON_ERROR));
        $limit = flow_osm_setting('FLOW_OSM_CACHE_MAX_BYTES', 268435456, 12000000, 2147483647);
        if ($total - $old + $size > $limit) { flow_osm_log('quota_exceeded', ['bytes'=>$total, 'limit'=>$limit]); throw new RuntimeException('Limite do cache OSM atingido.', 503); }
        flow_osm_cache_write($path, $entry);
    } finally { flow_osm_unlock($maintenance); }
}

/** One live request per key; fresh for 7 days, stale-on-error up to 30 days.
 * $fetcher/$directory/$now isolate network and filesystem in CLI tests. */
function flow_osm_context(array $config, ?callable $fetcher = null, ?string $directory = null, ?int $now = null): array
{
    if (!isset($config['latitude'], $config['longitude']) || !is_numeric($config['latitude']) || !is_numeric($config['longitude'])) {
        throw new InvalidArgumentException('Defina a localização da obra.');
    }
    $latitude = (float)$config['latitude']; $longitude = (float)$config['longitude'];
    $query = flow_osm_query($latitude, $longitude);
    $key = hash('sha256', sprintf('v2:%.7F:%.7F:1000', $latitude, $longitude));
    $directory ??= __DIR__ . '/../cache/flow-osm';
    $now ??= time();
    $fetcher ??= 'flow_osm_download';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Cache OSM indisponível.', 503);
    // Lock files are guarded PHP as well; the file name is never user-controlled.
    $lockPath = $directory . '/' . $key . '.lock.php';
    $lock = flow_osm_lock($lockPath);
    if (!$lock) {
        flow_osm_log('key_busy');
        $entry = flow_osm_cache_read($directory . '/' . $key . '.json.php');
        $saved = (int)($entry['saved_at'] ?? 0); $age = $now - $saved;
        if ($saved > 0 && $age >= 0 && $age < 30 * 86400 && is_array($entry['payload']['elements'] ?? null)) return ['payload'=>$entry['payload'], 'origin'=>['latitude'=>$latitude, 'longitude'=>$longitude], 'acquired_at'=>$saved, 'expires_at'=>$saved+7*86400, 'cache_hit'=>true, 'stale'=>$age>=7*86400];
        throw new RuntimeException('Consulta OSM em andamento. Tente novamente em instantes.', 503);
    }
    $slot = null;
    try {
        flow_osm_cleanup($directory, $now);
        if (fstat($lock)['size'] === 0) { fwrite($lock, FLOW_OSM_CACHE_GUARD); fflush($lock); }
        $path = $directory . '/' . $key . '.json.php';
        $entry = flow_osm_cache_read($path);
        $saved = (int)($entry['saved_at'] ?? 0);
        $age = $now - $saved;
        $valid = $saved > 0 && $age >= 0 && is_array($entry['payload']['elements'] ?? null);
        $origin = ['latitude' => $latitude, 'longitude' => $longitude];
        $result = static fn(bool $stale) => ['payload'=>$entry['payload'], 'origin'=>$origin, 'acquired_at'=>$saved, 'expires_at'=>$saved+7*86400, 'cache_hit'=>true, 'stale'=>$stale];
        if ($valid && $age < 7 * 86400) { flow_osm_log('cache_hit'); return $result(false); }
        flow_osm_log('cache_miss');
        $statePath = $directory . '/global.state.php';
        $stateLock = flow_osm_lock($directory . '/global.lock.php');
        if (!$stateLock) { if ($valid && $age < 30*86400) return $result(true); throw new RuntimeException('Serviço OSM ocupado.', 503); }
        try {
            $state = flow_osm_cache_read($statePath) ?? [];
            if ($now < (int)($state['until'] ?? 0)) { flow_osm_log('cooldown'); if ($valid && $age < 30*86400) return $result(true); throw new RuntimeException('Overpass temporariamente indisponível.', 503); }
            $slots = flow_osm_setting('FLOW_OSM_MAX_CONCURRENT', 2, 1, 16);
            for ($i = 0; $i < $slots; $i++) { $slot = flow_osm_lock($directory . '/slot-' . $i . '.lock.php'); if ($slot) break; }
            if (!$slot) { flow_osm_log('capacity'); if ($valid && $age < 30*86400) return $result(true); throw new RuntimeException('Serviço OSM ocupado.', 503); }
        } finally { flow_osm_unlock($stateLock); }
        try {
            $payload = flow_osm_payload($fetcher($query));
            flow_osm_store_payload($directory, $path, ['saved_at' => $now, 'payload' => $payload]);
            $stateLock = flow_osm_lock($directory . '/global.lock.php');
            if ($stateLock) { try { try { $current = flow_osm_cache_read($statePath) ?? []; if ((int)($current['until'] ?? 0) <= $now) flow_osm_cache_write($statePath, ['failures'=>0, 'until'=>0]); } catch (Throwable $ignored) {} } finally { flow_osm_unlock($stateLock); } }
            flow_osm_log('fetch_success');
            return ['payload' => $payload, 'origin' => $origin, 'acquired_at'=>$now, 'expires_at'=>$now+7*86400, 'cache_hit' => false, 'stale' => false];
        } catch (Throwable $error) {
            flow_osm_log('failure', ['type'=>get_class($error)]);
            $stateLock = flow_osm_lock($directory . '/global.lock.php');
            if ($stateLock) {
                try {
                    $state = flow_osm_cache_read($statePath) ?? []; $failures = min(8, (int)($state['failures'] ?? 0) + 1);
                    $delay = max(min(1800, 60 * (2 ** ($failures - 1))), min(86400, $error instanceof FlowOsmUpstreamException ? $error->retryAfter : 0));
                    try { flow_osm_cache_write($statePath, ['failures'=>$failures, 'until'=>max((int)($state['until'] ?? 0), $now+$delay)]); } catch (Throwable $ignored) { flow_osm_log('cooldown_write_failure'); }
                } finally { flow_osm_unlock($stateLock); }
            }
            if ($valid && $age < 30 * 86400) return $result(true);
            throw $error;
        }
    } finally { if ($slot) flow_osm_unlock($slot); flow_osm_unlock($lock); flow_osm_cleanup($directory, $now); }
}

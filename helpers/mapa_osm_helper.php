<?php
/** OSM acquisition only. Coordinates come from the authorised work's saved settings. */
const FLOW_OSM_ENDPOINT = 'https://maps.mail.ru/osm/tools/overpass/api/interpreter';
const FLOW_OSM_CACHE_GUARD = "<?php http_response_code(404); exit; ?>\n";
const FLOW_OSM_MAX_BYTES = 12000000;
class FlowOsmUpstreamException extends RuntimeException { public int $retryAfter = 0; public string $reason = 'external'; public bool $shouldCooldown = true; }
class FlowOsmResponseException extends RuntimeException {}
class FlowOsmLocalException extends RuntimeException {
    public function __construct(public string $reason, string $message = 'Cache OSM indisponível.') { parent::__construct($message, 503); }
}

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
        throw new FlowOsmResponseException('Resposta Overpass incompleta.', 502);
    }
    $tags = array_flip(['type', 'building', 'height', 'building:levels', 'highway', 'area', 'natural', 'water', 'waterway', 'landuse', 'leisure']);
    $elements = [];
    foreach ($payload['elements'] as $element) {
        if (!is_array($element) || !in_array($element['type'] ?? '', ['way', 'relation'], true) || !isset($element['id']) || !is_numeric($element['id'])) {
            throw new FlowOsmResponseException('Resposta Overpass inválida.', 502);
        }
        if (($element['type'] === 'way' && !is_array($element['geometry'] ?? null)) ||
            ($element['type'] === 'relation' && !is_array($element['members'] ?? null))) {
            throw new FlowOsmResponseException('Geometria Overpass incompleta.', 502);
        }
        $geometries = $element['type'] === 'way' ? [$element['geometry']] : array_map(static fn($member) => is_array($member) ? ($member['geometry'] ?? []) : null, $element['members']);
        foreach ($geometries as $geometry) {
            if (!is_array($geometry)) throw new FlowOsmResponseException('Geometria Overpass inválida.', 502);
            foreach ($geometry as $point) {
                if (!is_array($point) || !is_numeric($point['lat'] ?? null) || !is_numeric($point['lon'] ?? null) || !is_finite((float)$point['lat']) || !is_finite((float)$point['lon']) || abs((float)$point['lat']) > 90 || abs((float)$point['lon']) > 180) throw new FlowOsmResponseException('Geometria Overpass inválida.', 502);
            }
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
    if (!extension_loaded('curl')) throw new FlowOsmLocalException('curl_unavailable', 'Aquisição OSM indisponível.');
    $body = ''; $retryAfter = 0; $started = microtime(true); $tooLarge = false;
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
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge): int {
            if (strlen($body) + strlen($chunk) > FLOW_OSM_MAX_BYTES) { $tooLarge = true; return 0; }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($handle);
    curl_close($handle);
    flow_osm_log('upstream', ['stage'=>'download', 'result'=>($ok !== false && $status === 200 ? 'success' : 'failure'), 'http'=>$status, 'curl'=>$curlError, 'duration_ms'=>(int)((microtime(true)-$started)*1000)]);
    if ($tooLarge) throw new FlowOsmResponseException('Resposta Overpass excede o limite permitido.', 502);
    if ($ok === false || $status !== 200) {
        error_log("Flow OSM: aquisição indisponível (HTTP $status, cURL $curlError).");
        $error = new FlowOsmUpstreamException('Overpass temporariamente indisponível.', 503);
        $error->reason = $curlError === 28 ? 'timeout' : ($curlError ? 'curl_error' : 'http_error');
        $error->shouldCooldown = $curlError !== 0 || $status === 408 || $status === 429 || $status >= 500;
        $error->retryAfter = $retryAfter;
        throw $error;
    }
    try { $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR); }
    catch (Throwable $error) { throw new FlowOsmResponseException('Resposta Overpass inválida.', 502); }
    if (!is_array($payload)) throw new FlowOsmResponseException('Resposta Overpass inválida.', 502);
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
    if (strlen($encoded) > FLOW_OSM_MAX_BYTES) throw new FlowOsmLocalException('entry_too_large');
    $temp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp.php';
    try {
        if (@file_put_contents($temp, $encoded, LOCK_EX) !== strlen($encoded)) throw new FlowOsmLocalException(is_writable(dirname($path)) ? 'write_failed' : 'permission_denied');
        if (!@rename($temp, $path)) throw new FlowOsmLocalException(is_writable(dirname($path)) ? 'rename_failed' : 'permission_denied');
    } finally { if (is_file($temp)) @unlink($temp); }
}

function flow_osm_begin(?string $requestId = null): string {
    $GLOBALS['flow_osm_log_context'] = ['request_id'=>$requestId ?? bin2hex(random_bytes(16)), 'started'=>microtime(true), 'cache_state'=>'unknown', 'cache_age_s'=>null, 'cooldown_state'=>'unknown'];
    return $GLOBALS['flow_osm_log_context']['request_id'];
}
function flow_osm_log(string $event, array $details = []): void {
    if (!isset($GLOBALS['flow_osm_log_context']['request_id'])) flow_osm_begin();
    $c = $GLOBALS['flow_osm_log_context'];
    error_log(json_encode(array_merge(['event'=>'osm_'.$event, 'request_id'=>$c['request_id'], 'stage'=>$event, 'result'=>'info', 'duration_ms'=>(int)((microtime(true)-$c['started'])*1000), 'cache_state'=>$c['cache_state'], 'cache_age_s'=>$c['cache_age_s'], 'cooldown_state'=>$c['cooldown_state']], $details)));
}
function flow_osm_valid_cache(?array $entry, int $now): ?array {
    $saved = $entry['saved_at'] ?? null;
    if (!is_int($saved) || $saved <= 0 || $saved > $now || !is_array($entry['payload'] ?? null)) return null;
    try { $entry['payload'] = flow_osm_payload($entry['payload']); } catch (Throwable $ignored) { return null; }
    return $entry;
}
function flow_osm_cleanup_safely(string $directory, int $now): void {
    try { flow_osm_cleanup($directory, $now); }
    catch (Throwable $error) { flow_osm_log('cleanup', ['result'=>'failure', 'reason'=>$error instanceof FlowOsmLocalException ? $error->reason : 'unexpected']); }
}
function flow_osm_setting(string $name, int $default, int $min, int $max): int {
    $value = getenv($name);
    return $value !== false && ctype_digit($value) ? max($min, min($max, (int)$value)) : $default;
}
function flow_osm_lock(string $path) {
    $handle = @fopen($path, 'c+');
    if (!$handle) { $reason = is_writable(dirname($path)) ? 'lock_open_failed' : 'permission_denied'; flow_osm_log('lock', ['result'=>'failure', 'reason'=>$reason]); throw new FlowOsmLocalException($reason); }
    $wouldBlock = 0;
    if (!@flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) { fclose($handle); if ($wouldBlock) { flow_osm_log('lock', ['result'=>'busy']); return null; } flow_osm_log('lock', ['result'=>'failure', 'reason'=>'lock_acquire_failed']); throw new FlowOsmLocalException('lock_acquire_failed'); }
    $stat = fstat($handle);
    if ($stat === false || ($stat['size'] === 0 && (fwrite($handle, FLOW_OSM_CACHE_GUARD) !== strlen(FLOW_OSM_CACHE_GUARD) || !fflush($handle)))) {
        flock($handle, LOCK_UN); fclose($handle); flow_osm_log('lock', ['result'=>'failure', 'reason'=>'lock_write_failed']); throw new FlowOsmLocalException('lock_write_failed');
    }
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
    if (!$maintenance) throw new FlowOsmLocalException('maintenance_busy', 'Cache OSM ocupado.');
    try {
        $total = 0;
        foreach (glob($directory . '/*.json.php') ?: [] as $file) { clearstatcache(true, $file); $total += @filesize($file) ?: 0; }
        clearstatcache(true, $path);
        $old = is_file($path) ? (@filesize($path) ?: 0) : 0;
        $size = strlen(FLOW_OSM_CACHE_GUARD . json_encode($entry, JSON_THROW_ON_ERROR));
        $limit = flow_osm_setting('FLOW_OSM_CACHE_MAX_BYTES', 268435456, 12000000, 2147483647);
        if ($total - $old + $size > $limit) { flow_osm_log('quota_exceeded', ['bytes'=>$total, 'limit'=>$limit]); throw new FlowOsmLocalException('quota_exceeded', 'Limite do cache OSM atingido.'); }
        flow_osm_cache_write($path, $entry);
    } finally { flow_osm_unlock($maintenance); }
}

/** One live request per key; fresh for 7 days, stale-on-error up to 30 days.
 * $fetcher/$directory/$now isolate network and filesystem in CLI tests. */
function flow_osm_context(array $config, ?callable $fetcher = null, ?string $directory = null, ?int $now = null, ?callable $store = null): array
{
    if (!isset($GLOBALS['flow_osm_log_context']['request_id'])) flow_osm_begin();
    if (!isset($config['latitude'], $config['longitude']) || !is_numeric($config['latitude']) || !is_numeric($config['longitude'])) {
        throw new InvalidArgumentException('Defina a localização da obra.');
    }
    $latitude = (float)$config['latitude']; $longitude = (float)$config['longitude'];
    $query = flow_osm_query($latitude, $longitude);
    $key = hash('sha256', sprintf('v2:%.7F:%.7F:1000', $latitude, $longitude));
    $directory ??= __DIR__ . '/../cache/flow-osm';
    $now ??= time();
    $fetcher ??= 'flow_osm_download';
    $store ??= 'flow_osm_store_payload';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Cache OSM indisponível.', 503);
    // Lock files are guarded PHP as well; the file name is never user-controlled.
    $lockPath = $directory . '/' . $key . '.lock.php';
    $lockError = null;
    try { $lock = flow_osm_lock($lockPath); }
    catch (FlowOsmLocalException $error) { $lock = null; $lockError = $error; flow_osm_log('lock', ['result'=>'failure', 'reason'=>$error->reason, 'lock_kind'=>'key']); }
    if (!$lock) {
        if (!$lockError) flow_osm_log('key_busy', ['result'=>'busy']);
        $entry = flow_osm_valid_cache(flow_osm_cache_read($directory . '/' . $key . '.json.php'), $now);
        $saved = (int)($entry['saved_at'] ?? 0); $age = $now - $saved;
        if ($entry && $age < 30 * 86400) { $GLOBALS['flow_osm_log_context']['cache_state'] = $age < 7*86400 ? 'fresh' : 'stale'; $GLOBALS['flow_osm_log_context']['cache_age_s'] = $age; flow_osm_log('cache_hit', ['result'=>'success']); return ['payload'=>$entry['payload'], 'origin'=>['latitude'=>$latitude, 'longitude'=>$longitude], 'acquired_at'=>$saved, 'expires_at'=>$saved+7*86400, 'cache_hit'=>true, 'stale'=>$age>=7*86400]; }
        if ($lockError) throw $lockError;
        throw new RuntimeException('Consulta OSM em andamento. Tente novamente em instantes.', 503);
    }
    $slot = null;
    try {
        flow_osm_cleanup_safely($directory, $now);
        if (fstat($lock)['size'] === 0) { fwrite($lock, FLOW_OSM_CACHE_GUARD); fflush($lock); }
        $path = $directory . '/' . $key . '.json.php';
        $entry = flow_osm_valid_cache(flow_osm_cache_read($path), $now);
        $saved = (int)($entry['saved_at'] ?? 0);
        $age = $now - $saved;
        $valid = $entry !== null;
        $GLOBALS['flow_osm_log_context']['cache_state'] = !$valid ? 'miss' : ($age < 7*86400 ? 'fresh' : ($age < 30*86400 ? 'stale' : 'expired'));
        $GLOBALS['flow_osm_log_context']['cache_age_s'] = $valid ? $age : null;
        $origin = ['latitude' => $latitude, 'longitude' => $longitude];
        $result = static fn(bool $stale) => ['payload'=>$entry['payload'], 'origin'=>$origin, 'acquired_at'=>$saved, 'expires_at'=>$saved+7*86400, 'cache_hit'=>true, 'stale'=>$stale];
        if ($valid && $age < 7 * 86400) { flow_osm_log('cache_hit'); return $result(false); }
        flow_osm_log('cache_miss');
        $statePath = $directory . '/global.state.php';
        try { $stateLock = flow_osm_lock($directory . '/global.lock.php'); }
        catch (FlowOsmLocalException $error) { if ($valid && $age < 30*86400) return $result(true); throw $error; }
        if (!$stateLock) { if ($valid && $age < 30*86400) return $result(true); throw new RuntimeException('Serviço OSM ocupado.', 503); }
        try {
            $state = flow_osm_cache_read($statePath) ?? [];
            $GLOBALS['flow_osm_log_context']['cooldown_state'] = $now < (int)($state['until'] ?? 0) ? 'active' : 'inactive';
            if ($now < (int)($state['until'] ?? 0)) { flow_osm_log('cooldown', ['result'=>'active', 'remaining_s'=>(int)$state['until']-$now]); if ($valid && $age < 30*86400) return $result(true); throw new RuntimeException('Overpass temporariamente indisponível.', 503); }
            $slots = flow_osm_setting('FLOW_OSM_MAX_CONCURRENT', 2, 1, 16);
            try { for ($i = 0; $i < $slots; $i++) { $slot = flow_osm_lock($directory . '/slot-' . $i . '.lock.php'); if ($slot) break; } }
            catch (FlowOsmLocalException $error) { if ($valid && $age < 30*86400) return $result(true); throw $error; }
            if (!$slot) { flow_osm_log('capacity'); if ($valid && $age < 30*86400) return $result(true); throw new RuntimeException('Serviço OSM ocupado.', 503); }
        } finally { flow_osm_unlock($stateLock); }
        try {
            $payload = flow_osm_payload($fetcher($query));
            try {
                $store($directory, $path, ['saved_at' => $now, 'payload' => $payload]);
            } catch (Throwable $storageError) {
                flow_osm_log('persistence', ['result'=>'failure', 'reason'=>$storageError instanceof FlowOsmLocalException ? $storageError->reason : 'unexpected']);
            }
            try {
                $stateLock = flow_osm_lock($directory . '/global.lock.php');
                if ($stateLock) { try { $current = flow_osm_cache_read($statePath) ?? []; if ((int)($current['until'] ?? 0) <= $now) flow_osm_cache_write($statePath, ['failures'=>0, 'until'=>0]); } finally { flow_osm_unlock($stateLock); } }
                else flow_osm_log('cooldown_reset', ['result'=>'busy']);
            } catch (Throwable $ignored) { flow_osm_log('cooldown_reset', ['result'=>'failure', 'reason'=>$ignored instanceof FlowOsmLocalException ? $ignored->reason : 'unexpected']); }
            flow_osm_log('fetch_success');
            return ['payload' => $payload, 'origin' => $origin, 'acquired_at'=>$now, 'expires_at'=>$now+7*86400, 'cache_hit' => false, 'stale' => false];
        } catch (Throwable $error) {
            flow_osm_log('failure', ['result'=>'failure', 'reason'=>$error instanceof FlowOsmUpstreamException ? $error->reason : ($error instanceof FlowOsmResponseException ? 'invalid_response' : ($error instanceof FlowOsmLocalException ? $error->reason : 'internal'))]);
            $stateLock = null;
            try { if ($error instanceof FlowOsmUpstreamException && $error->shouldCooldown) $stateLock = flow_osm_lock($directory . '/global.lock.php'); }
            catch (Throwable $ignored) { flow_osm_log('cooldown_write_failure', ['result'=>'failure', 'reason'=>'lock_error']); }
            if ($stateLock) {
                try {
                    $state = flow_osm_cache_read($statePath) ?? []; $failures = min(8, (int)($state['failures'] ?? 0) + 1);
                    $delay = max(min(1800, 60 * (2 ** ($failures - 1))), min(86400, $error instanceof FlowOsmUpstreamException ? $error->retryAfter : 0));
                    try { flow_osm_cache_write($statePath, ['failures'=>$failures, 'until'=>max((int)($state['until'] ?? 0), $now+$delay)]); $GLOBALS['flow_osm_log_context']['cooldown_state']='active'; flow_osm_log('cooldown_set', ['result'=>'success', 'delay_s'=>$delay]); } catch (Throwable $ignored) { flow_osm_log('cooldown_write_failure', ['result'=>'failure', 'reason'=>$ignored instanceof FlowOsmLocalException ? $ignored->reason : 'unexpected']); }
                } finally { flow_osm_unlock($stateLock); }
            }
            if ($valid && $age < 30 * 86400) return $result(true);
            throw $error;
        }
    } finally { if ($slot) flow_osm_unlock($slot); flow_osm_unlock($lock); flow_osm_cleanup_safely($directory, $now); }
}

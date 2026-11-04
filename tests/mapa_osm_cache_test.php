<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../helpers/mapa_osm_helper.php';
$checks = 0;
function check($condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function fails(callable $run, string $class = Throwable::class): void {
    try { $run(); } catch (Throwable $error) { check($error instanceof $class, 'Unexpected exception'); return; }
    throw new RuntimeException('Expected rejection');
}
$directory = __DIR__.'/../output/osm-qa/cache-test-'.bin2hex(random_bytes(5));
$config = ['latitude'=>-27.0992256,'longitude'=>-48.6179817];
$fixture = ['elements'=>[['type'=>'way','id'=>12,'tags'=>['natural'=>'wood','name'=>'do not store','operator'=>'private'], 'geometry'=>[['lat'=>-27,'lon'=>-48]], 'user'=>'do not store']]];
$now = 1800000000; $calls = 0;
$fetcher = static function($query) use (&$calls,$fixture) { $calls++; check(str_contains($query,'out geom;'), 'Geometry query'); return $fixture; };
try {
    foreach ([[NAN,0],[90,0],[0,181],[0,0,99],[0,0,1501]] as $invalid) fails(fn()=>flow_osm_query(...$invalid),InvalidArgumentException::class);
    $query = flow_osm_query($config['latitude'],$config['longitude']);
    check(str_contains($query,'[natural="coastline"]') && str_contains($query,'relation[type="multipolygon"]'), 'Coast and relations included');
    $first = flow_osm_context($config,$fetcher,$directory,$now);
    check(!$first['cache_hit'] && !$first['stale'] && $first['origin']===$config,'First fetch and origin');
    $clean = $first['payload']['elements'][0];
    check($clean['tags']===['natural'=>'wood'] && !isset($clean['user']), 'Names and nonvisual metadata removed');
    $second = flow_osm_context($config,$fetcher,$directory,$now+1);
    check($second['cache_hit'] && $calls===1,'Fresh cache avoids network');
    foreach (glob($directory.'/*.php') as $path) check(str_starts_with(file_get_contents($path),FLOW_OSM_CACHE_GUARD),'Cache protected from direct web request');
    flow_osm_context(array_replace($config,['longitude'=>-48.6]),$fetcher,$directory,$now+1);
    check($calls===2,'Location change has separate cache');
    $offlineCalls = 0;
    $offline = static function() use (&$offlineCalls) { $offlineCalls++; throw new RuntimeException('offline',503); };
    $stale = flow_osm_context($config,$offline,$directory,$now+8*86400);
    check($stale['stale'] && $stale['cache_hit'] && $offlineCalls===1,'Stale cache survives upstream failure');
    $again = flow_osm_context($config,$offline,$directory,$now+8*86400+1);
    check($again['stale'] && $offlineCalls===1,'Failure cooldown prevents repeated requests');
    fails(fn()=>flow_osm_context($config,$offline,$directory,$now+31*86400));
    fails(fn()=>flow_osm_context($config,$offline,$directory,$now+31*86400+1));
    check($offlineCalls===2,'Expired stale data rejected and cooldown retained');
    $other = array_replace($config,['longitude'=>-48.5]);
    fails(fn()=>flow_osm_context($other,fn()=>['elements'=>[], 'remark'=>'partial'],$directory,$now));
    fails(fn()=>flow_osm_context(['latitude'=>null,'longitude'=>0],$fetcher,$directory,$now),InvalidArgumentException::class);
    fails(fn()=>flow_osm_payload(['invalid'=>[]]));
    check($first['acquired_at'] === $now && $second['expires_at'] === $now + 7*86400, 'Original acquisition and expiration preserved');
    flow_osm_context($config,$fetcher,$directory,$now+32*86400);
    $key = hash('sha256', sprintf('v2:%.7F:%.7F:1000', $config['latitude'], $config['longitude']));
    $held = flow_osm_lock($directory.'/'.$key.'.lock.php');
    $busyStale = flow_osm_context($config,$offline,$directory,$now+40*86400);
    check($busyStale['stale'] && $offlineCalls===2, 'Busy key returns stale without another fetch');
    flow_osm_unlock($held);
    $activePath = $directory.'/'.$key.'.json.php';
    $held = flow_osm_lock($directory.'/'.$key.'.lock.php');
    flow_osm_cleanup($directory,$now+63*86400);
    check(is_file($activePath), 'Cleanup skips active entry');
    flow_osm_unlock($held);
    flow_osm_cleanup($directory,$now+63*86400);
    check(!is_file($activePath), 'Cleanup removes expired entry');
    $newConfig = ['latitude'=>-26.4,'longitude'=>-48.4];
    $slot1 = flow_osm_lock($directory.'/slot-0.lock.php');
    $slot2 = flow_osm_lock($directory.'/slot-1.lock.php');
    fails(fn()=>flow_osm_context($newConfig,$fetcher,$directory,$now+64*86400));
    check($calls===3, 'Global slots reject immediately without fetching');
    flow_osm_unlock($slot1); flow_osm_unlock($slot2);
    $globalDir = $directory.'/write-failure'; mkdir($globalDir);
    flow_osm_context($config,$fetcher,$globalDir,$now);
    unlink($globalDir.'/global.state.php'); mkdir($globalDir.'/global.state.php');
    $result = flow_osm_context($config,$offline,$globalDir,$now+8*86400);
    check($result['stale'] && $result['acquired_at']===$now, 'Failure to write cooldown preserves stale response');
    rmdir($globalDir.'/global.state.php');
    $broken = static fn()=>['elements'=>[['type'=>'way','id'=>1]]];
    $result = flow_osm_context($config,$broken,$globalDir,$now+9*86400);
    check($result['stale'] && $result['payload']===$first['payload'], 'Incomplete geometry cannot replace valid cache');
    foreach (glob($globalDir.'/*.php') as $path) unlink($path);
    rmdir($globalDir);
    $globalDir = $directory.'/global-test'; mkdir($globalDir);
    $rateLimited = static function () { $error = new FlowOsmUpstreamException('limited', 503); $error->retryAfter=300; throw $error; };
    fails(fn()=>flow_osm_context($config,$rateLimited,$globalDir,$now));
    $state = flow_osm_cache_read($globalDir.'/global.state.php');
    check($state['until']===$now+300 && $state['failures']===1, 'Retry-After drives global cooldown');
    $before = $calls;
    fails(fn()=>flow_osm_context($newConfig,$fetcher,$globalDir,$now+60));
    check($calls===$before, 'Cooldown applies across locations');
    fails(fn()=>flow_osm_context($newConfig,$offline,$globalDir,$now+301));
    $state = flow_osm_cache_read($globalDir.'/global.state.php');
    check($state['until']===$now+421 && $state['failures']===2, 'Backoff doubles after next failure');
    foreach (glob($globalDir.'/*.php') as $path) unlink($path);
    rmdir($globalDir);
    echo "OSM server cache: $checks checks passed\n";
} finally {
    // Only files created by this test, inside the fixed QA directory.
    foreach (glob($directory.'/*.php') as $path) unlink($path);
    if (is_dir($directory)) rmdir($directory);
}

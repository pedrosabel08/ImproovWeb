<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../helpers/mapa_dem_helper.php';
$checks=0;
function dem_ok(bool $condition,string $message): void {global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
$config=['latitude'=>0.0,'longitude'=>0.0];
dem_ok(count(flow_dem_tiles($config))===4,'Tile boundary coverage');
foreach([['latitude'=>null,'longitude'=>0],['latitude'=>89,'longitude'=>0],['latitude'=>0,'longitude'=>181]] as $invalid) {
    try {flow_dem_tiles($invalid);throw new LogicException('Invalid location accepted');}catch(InvalidArgumentException $e){$checks++;}
}
try {flow_dem_tile($config,1,1);throw new LogicException('Tile outside work accepted');}catch(RuntimeException $e){dem_ok($e->getCode()===403,'Work restriction before any acquisition');}
$directory=__DIR__.'/../tmp/dem-unit-'.bin2hex(random_bytes(4));mkdir($directory);
try {
    [$x,$y]=flow_dem_tiles($config)[0];$file=$directory.'/12-'.$x.'-'.$y.'.webp.php';
    $body='RIFF'.pack('V',20).'WEBP'.str_repeat('a',20);
    file_put_contents($file,FLOW_DEM_CACHE_GUARD.$body);
    dem_ok(flow_dem_tile($config,$x,$y,$directory)===$body,'Cache reused without upstream, PHP guard stripped');
    touch($file,time()-40*86400);touch($file.'.retry');
    dem_ok(flow_dem_tile($config,$x,$y,$directory)===$body,'Stale cache survives upstream cooldown');
    unlink($file);clearstatcache();
    try {flow_dem_tile($config,$x,$y,$directory);throw new LogicException('Cooldown ignored');}catch(RuntimeException $e){dem_ok($e->getCode()===503,'No-cache cooldown fails safely for flat fallback');}
} finally {foreach(glob($directory.'/*') as $file)unlink($file);rmdir($directory);}
echo "DEM backend: $checks checks passed\n";

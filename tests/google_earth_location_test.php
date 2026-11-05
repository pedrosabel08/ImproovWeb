<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../helpers/google_earth_location_helper.php';
$checks=0; $failures=[];
$check=static function($ok,$label) use (&$checks,&$failures) { $checks++; if(!$ok)$failures[]=$label; };
$root='https://earth.google.com/web/';
foreach ([
    ['@-27.12345678,-48.5,0a,200d,35y,0h,0t,0r/data=payload?authuser=1',['latitude'=>-27.1234568,'longitude'=>-48.5]],
    ['search/rua+exemplo/@-26.9,-48.6,0a,100d/data=payload',['latitude'=>-26.9,'longitude'=>-48.6]],
    ['search/rua%20exemplo/@-26.9,-48.6,0a,100d/data=payload',['latitude'=>-26.9,'longitude'=>-48.6]],
    ['%40-26.9%2C-48.6%2C0a',['latitude'=>-26.9,'longitude'=>-48.6]],
    ['@0,0,0a',['latitude'=>0.,'longitude'=>0.]],
    ['@90,-180,0a',['latitude'=>90.,'longitude'=>-180.]],
    ['@-91,0,0a',null], ['@0,181,0a',null], ['@abc,-48,0a',null], ['@1e2,0,0a',null],
    ['data=shared-project-only',null], ['search/name?next=/@-27,-48,0a',null],
    ['@-27,-48,0a/@-26,-49,0a',null],
] as $i=>[$path,$expected]) $check(flow_earth_url_coordinates($root.$path)===$expected,'URL case '.$i);
foreach ([null,[],false,'http://earth.google.com/web/@-27,-48,0a','https://earth.google.com.evil.test/web/@-27,-48,0a',
    'https://earth.google.com@evil.test/web/@-27,-48,0a','https://user:pass@earth.google.com/web/@-27,-48,0a',
    'https://earth.google.com:444/web/@-27,-48,0a','https://other.test/web/@-27,-48,0a',
    'https://earth.google.com/other/@-27,-48,0a',"https://earth.google.com/web/@-27,-48,0a\n?x=1",
    $root.'@-27,-48,0a/'.str_repeat('a',2048)] as $i=>$url) $check(flow_earth_url_coordinates($url)===null,'Unsafe URL '.$i);
$row=['google_earth'=>$root.'@-27,-48,0a','latitude'=>null,'longitude'=>null];
$check(flow_earth_import_decision($row)['status']==='pronto','Import missing pair');
foreach ([[0,0],[0,null],[null,0],[91,181]] as $i=>[$lat,$lng]) {
    $check(flow_earth_import_decision(['latitude'=>$lat,'longitude'=>$lng]+$row)['status']==='coordenadas_preservadas','Preserve existing/partial '.$i);
}
$project=['google_earth'=>$root.'data=project','latitude'=>null,'longitude'=>null];
$check(flow_earth_import_decision($project)['status']==='link_sem_coordenadas','Project payload never guessed');
$manual=flow_earth_import_decision($project,['latitude'=>-27.1,'longitude'=>-48.6]);
$check($manual['status']==='pronto'&&$manual['origem']==='ponto_confirmado_no_google_earth','Explicit human-reviewed point');
$check(flow_earth_import_decision($row,['latitude'=>1,'longitude'=>2])['coordinates']===['latitude'=>-27.,'longitude'=>-48.],'URL precedes manual point');
$check(flow_earth_import_decision(['google_earth'=>'https://other.test/']+['latitude'=>null,'longitude'=>null],['latitude'=>1,'longitude'=>2])['status']==='link_invalido','Confirmed point requires registered Earth source');
try { flow_earth_import_decision($project,['latitude'=>91,'longitude'=>0]); $check(false,'Invalid confirmed point'); }
catch(InvalidArgumentException $error){$check(true,'Invalid confirmed point');}
echo json_encode(compact('checks','failures'),JSON_PRETTY_PRINT).PHP_EOL;
exit($failures?1:0);

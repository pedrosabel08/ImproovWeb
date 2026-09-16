<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../PortalCliente/auth.php';
$db=briefing_conn(); $fixture=json_decode(file_get_contents(sys_get_temp_dir().'/improov-portal-browser-fixture.json'),true,512,JSON_THROW_ON_ERROR);
$credentials=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$base='http://localhost:8066/ImproovWeb/';$cookies=[];$assertions=0;
function client(): CurlHandle {global $cookies;$path=tempnam(sys_get_temp_dir(),'portal-http-');$cookies[]=$path;$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$path,CURLOPT_COOKIEJAR=>$path,CURLOPT_TIMEOUT=>25]);return $c;}
function request(CurlHandle $c,string $path,?array $body=null,string $csrf='',bool $form=false): array {
    global $base;
    curl_setopt_array($c,[CURLOPT_URL=>$base.$path,CURLOPT_POST=>$body!==null,CURLOPT_HTTPHEADER=>$body!==null?['Content-Type: '.($form?'application/x-www-form-urlencoded':'application/json'),'X-Portal-CSRF: '.$csrf]:[]]);
    if($body!==null)curl_setopt($c,CURLOPT_POSTFIELDS,$form?http_build_query($body):json_encode($body));
    $raw=curl_exec($c);if($raw===false)throw new RuntimeException(curl_error($c));return ['status'=>curl_getinfo($c,CURLINFO_RESPONSE_CODE),'raw'=>$raw,'json'=>json_decode($raw,true)];
}
function expectStatus(array $r,int $status,string $label): void {global $assertions;if($r['status']!==$status)throw new RuntimeException($label.' expected '.$status.' got '.$r['status'].' '.substr($r['raw'],0,160));$assertions++;echo "OK $label HTTP $status\n";}
function csrfFrom(array $r): string {if(!preg_match('/name="portal-csrf" content="([a-f0-9]+)"/',$r['raw'],$m))throw new RuntimeException('CSRF meta not found');return $m[1];}
try {
    $anon=client();expectStatus(request($anon,'PortalCliente/internal_api.php',['action'=>'bootstrap']),401,'anonymous internal API');
    expectStatus(request($anon,'PortalCliente/api.php'),405,'external GET mutation refused');
    expectStatus(request($anon,'PortalCliente/api.php',['action'=>'access.inspect','token'=>str_repeat('0',64)]),404,'unknown invite');
    $internal=client();$login=request($internal,'login.php',$credentials,'',true);expectStatus($login,200,'existing Flow login');
    $page=request($internal,'PortalCliente/internal.php');$icsrf=csrfFrom($page);
    expectStatus(request($internal,'PortalCliente/internal_api.php',['action'=>'bootstrap']),403,'internal CSRF required');
    expectStatus(request($internal,'PortalCliente/internal_api.php',['action'=>'bootstrap'],$icsrf),200,'internal bootstrap');
    $user=portal_one($db,'SELECT idusuario FROM usuario WHERE login=? AND ativo=1','s',[$credentials['login']]);
    $b=$fixture['obras'][1];$email='api-'.$fixture['client'].'@example.invalid';
    $config=request($internal,'PortalCliente/internal_api.php',['action'=>'project.configure','obra_id'=>$b,'curador_usuario_id'=>$user['idusuario'],'nome'=>'API Teste','email'=>$email,'telefone'=>'47900000003'],$icsrf);expectStatus($config,200,'configure fixture B');$token=$config['json']['token'];
    $otherToken=bin2hex(random_bytes(32));
    // Issue a different invite for synthetic B only; A's real browser invitation is untouched.
    $ext=client();$entry=request($ext,'PortalCliente/index.php?t='.$token);$entryCsrf=csrfFrom($entry);
    expectStatus(request($ext,'PortalCliente/api.php',['action'=>'project.get','token'=>$token]),401,'link alone is not authenticated');
    expectStatus(request($ext,'PortalCliente/api.php',['action'=>'access.verify','token'=>$token,'email'=>$email,'code'=>'123456']),403,'preauth CSRF required');
    $db->begin_transaction();$p=portal_project($db,$b,true);$code='';portal_issue_otp($db,$p,['email'=>$email,'nome'=>'API Teste','telefone'=>'47900000003'],function($e,$v)use(&$code){$code=$v;return true;});$db->commit();
    $verify=request($ext,'PortalCliente/api.php',['action'=>'access.verify','token'=>$token,'email'=>$email,'code'=>$code],$entryCsrf);expectStatus($verify,200,'verify actual one-time challenge');$ecsrf=$verify['json']['csrf'];
    expectStatus(request($ext,'PortalCliente/api.php',['action'=>'access.verify','token'=>$token,'email'=>$email,'code'=>$code],$entryCsrf),422,'HTTP OTP replay refused');
    expectStatus(request($ext,'PortalCliente/api.php',['action'=>'project.get','token'=>$token]),200,'authenticated project read');
    expectStatus(request($ext,'PortalCliente/api.php',['action'=>'profile.save','token'=>$token,'nome'=>'API Teste','telefone'=>'47900000003','disciplinas'=>[]]),403,'authenticated CSRF required');
    expectStatus(request($ext,'PortalCliente/api.php',['action'=>'profile.save','token'=>$token,'nome'=>'API Teste','telefone'=>'47900000003','disciplinas'=>[]],$ecsrf),200,'no-discipline profile through HTTP');
    expectStatus(request($ext,'PortalCliente/api.php',['action'=>'participant.remove','token'=>$token,'contato_id'=>$p['administrador_contato_id']],$ecsrf),404,'scenario 6 external removal endpoint absent');
    expectStatus(request($ext,'PortalCliente/api.php',['action'=>'invite.share','token'=>$token],$ecsrf),200,'scenario 5 participant may share invite');
    expectStatus(request($ext,'PortalCliente/api.php',['action'=>'project.get','token'=>$token,'obra_id'=>$fixture['obras'][0]]),403,'scenario 12 forged project parameter');
    $pA=portal_project($db,$fixture['obras'][0]);
    // Token A can be provided explicitly to test membership isolation without rotating it.
    $tokenA=$argv[1] ?? '';
    if($tokenA){expectStatus(request($ext,'PortalCliente/api.php',['action'=>'project.get','token'=>$tokenA]),403,'scenario 12 another valid invitation does not grant membership');}
    $cid=(int)$p['administrador_contato_id'];
    $db->begin_transaction();portal_exec($db,'UPDATE portal_participante SET removido_em=UTC_TIMESTAMP() WHERE obra_id=? AND contato_id=?','ii',[$b,$cid]);$db->commit();
    expectStatus(request($ext,'PortalCliente/api.php',['action'=>'project.get','token'=>$token]),403,'session loses access immediately after removal');
    $db->begin_transaction();portal_exec($db,'UPDATE portal_participante SET removido_em=NULL WHERE obra_id=? AND contato_id=?','ii',[$b,$cid]);$db->commit();
    expectStatus(request($ext,'PortalCliente/api.php',['action'=>'access.logout','token'=>$token],$ecsrf),200,'logout');
    expectStatus(request($ext,'PortalCliente/api.php',['action'=>'project.get','token'=>$token]),401,'revoked session rejected');
    echo "$assertions HTTP assertions passed. Fixture cleanup remains separate.\n";
} finally {foreach($cookies as $file)if(is_file($file))unlink($file);}

<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
// Somente a rota explicitamente isolada, em loopback, com sessão sintética.
$base='http://localhost:8066/ImproovWeb/tests/competencia-browser.php';
$h=curl_init();
curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>30]);
function req(string $url,?array $data=null,?string $csrf=null): array {
    global $h;
    curl_setopt_array($h,[CURLOPT_URL=>$url,CURLOPT_POST=>$data!==null,CURLOPT_HTTPHEADER=>$csrf===null?[]:['Content-Type: application/json','X-CSRF-Token: '.$csrf]]);
    if($data!==null) curl_setopt($h,CURLOPT_POSTFIELDS,$csrf===null?http_build_query($data):json_encode($data));
    $raw=curl_exec($h); if($raw===false) throw new RuntimeException(curl_error($h));
    return [curl_getinfo($h,CURLINFO_RESPONSE_CODE),$raw];
}
function api(string $action,array $data,?string $csrf=null): array {
    global $base;
    [$status,$raw]=req($base.'?fixture_action='.$action.($csrf===null?'&'.http_build_query($data):''),$csrf!==null?$data:null,$csrf);
    $r=json_decode($raw,true);
    if($status!==200 || empty($r['success'])) throw new RuntimeException('API '.$action.' falhou: '.$status.' '.$raw);
    return $r['data'];
}
[$status,$html]=req($base,['login'=>'pedro_imp','senha'=>'Pedro0808']);
if(!preg_match('/name="pagamento-csrf" content="([a-f0-9]+)"/',$html,$m)) throw new RuntimeException('Sessão/CSRF sintético não encontrado. Prepare --browser-ready.');
$token=$m[1]; $ctx=['colaborador_id'=>2,'competencia'=>'2026-09'];
$r=api('obter',$ctx);
$item=array_values(array_filter($r['revisao']['financeiro_servicos']['itens_analisados'],fn($s)=>$s['situacao']==='DEVIDO'))[0];
$input=['estado'=>'RETIRAR','alvo'=>'ITEM','identidade'=>$item['identidade'],'motivo'=>'Homologação HTTP: somente render'];
$body=$ctx+['expected_version'=>$r['latest_version'],'idempotency_key'=>'http-withdraw-'.bin2hex(random_bytes(8)),'tipo'=>'SERVICOS','input'=>$input];
$withdraw=api('decidir',$body,$token);
if(!array_filter($withdraw['revisao']['financeiro_servicos']['itens_analisados'],fn($s)=>$s['situacao']==='RETIRADO')) throw new RuntimeException('HTTP não retirou tarefa');
$body['expected_version']=$withdraw['latest_version']; $body['idempotency_key']='http-restore-'.bin2hex(random_bytes(8));
$body['input']['estado']='RESTAURAR'; $body['input']['motivo']='Homologação HTTP: restauração autorizada';
$restore=api('decidir',$body,$token);
if($restore['revisao']['total_final_centavos']!==$r['revisao']['total_final_centavos']) throw new RuntimeException('HTTP não restaurou o total');
[$status,$failure]=req($base.'?fixture_action=decidir',$body,'invalid-token');
if($status!==403) throw new RuntimeException('CSRF: HTTP '.$status.' '.$failure);
echo "HTTP sintético: retirada, restauração e CSRF passaram.\n";

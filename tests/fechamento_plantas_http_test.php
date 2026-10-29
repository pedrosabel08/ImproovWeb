<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$url='http://localhost:8066/ImproovWeb/tests/mensal-browser.php';$h=curl_init();
curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>30]);
function phRequest(string $action,array $body=[],?string $csrf=null):array {
    global $h,$url;
    curl_setopt_array($h,[CURLOPT_URL=>$url.'?fixture_action='.$action.($csrf===null?'&'.http_build_query($body):''),CURLOPT_POST=>$csrf!==null,CURLOPT_HTTPHEADER=>$csrf!==null?['Content-Type: application/json','X-CSRF-Token: '.$csrf]:[]]);
    if($csrf!==null)curl_setopt($h,CURLOPT_POSTFIELDS,json_encode($body));
    $raw=curl_exec($h);if($raw===false)throw new RuntimeException(curl_error($h));
    return [curl_getinfo($h,CURLINFO_RESPONSE_CODE),json_decode($raw,true)];
}
curl_setopt_array($h,[CURLOPT_URL=>$url,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['login'=>'pedro_imp','senha'=>'Pedro0808'])]);
$html=curl_exec($h);if(!preg_match('/name="pagamento-csrf" content="([a-f0-9]+)"/',$html,$m))throw new RuntimeException('Prepare a fixture PH.');
$csrf=$m[1];$ctx=['colaborador_id'=>6,'competencia'=>'2026-10'];
[$code,$read]=phRequest('obter',$ctx);if($code!==200)throw new RuntimeException('Leitura PH falhou');$read=$read['data'];
$item=array_values(array_filter($read['revisao']['financeiro_servicos']['itens_analisados'],fn($i)=>(int)$i['identidade']['origem_id']===5001))[0];
$body=$ctx+['expected_version'=>$read['latest_version'],'idempotency_key'=>'ph-http-'.bin2hex(random_bytes(5)),'tipo'=>'SERVICOS','input'=>['estado'=>'APLICAR_TARIFA','alvo'=>'ITEM','identidade'=>$item['identidade'],'categoria_planta'=>'APTO_INDIVIDUAL']];
[$code,$changed]=phRequest('decidir',$body,$csrf);if($code!==200||$changed['data']['revisao']['total_final_centavos']!=='77000')throw new RuntimeException('Tarifa HTTP não aplicada');
[$code,$retry]=phRequest('decidir',$body,$csrf);if($code!==200||$retry['data']['revisao']['id']!==$changed['data']['revisao']['id'])throw new RuntimeException('Retry PH não idempotente');
$bad=$body;$bad['input']['valor']='1';[$code]=phRequest('decidir',$bad,$csrf);if($code!==422)throw new RuntimeException('Valor de tarifa arbitrário aceito');
$bad=$body;$bad['input']['estado']='ALTERAR_VALOR';$bad['input']['valor']='250';[$code]=phRequest('decidir',$bad,$csrf);if($code!==422)throw new RuntimeException('Exceção sem motivo aceita');
[$code]=phRequest('decidir',$body,'invalid');if($code!==403)throw new RuntimeException('CSRF inválido aceito');
$restore=$body;$restore['expected_version']=$changed['data']['latest_version'];$restore['idempotency_key'].='-restore';$restore['input']=['estado'=>'RESTAURAR_VALOR','alvo'=>'ITEM','identidade'=>$item['identidade'],'motivo'=>'Restauração de teste HTTP sintético'];
[$code,$restored]=phRequest('decidir',$restore,$csrf);if($code!==200||$restored['data']['revisao']['total_final_determinado'])throw new RuntimeException('Restauração não retomou divergência');
echo "HTTP PH: padrão sem motivo livre, idempotência, rejeição de tarifa arbitrária, motivo obrigatório na exceção, CSRF e restauração passaram.\n";

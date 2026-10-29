<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$url='http://localhost:8066/ImproovWeb/tests/mensal-browser.php';
$h=curl_init();curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>30]);
function taskRequest(string $action,array $body=[],?string $csrf=null):array {
    global $h,$url;
    curl_setopt_array($h,[CURLOPT_URL=>$url.($action?'?fixture_action='.$action.($csrf===null?'&'.http_build_query($body):''):''),CURLOPT_POST=>$csrf!==null,CURLOPT_HTTPHEADER=>$csrf!==null?['Content-Type: application/json','X-CSRF-Token: '.$csrf]:[]]);
    if($csrf!==null)curl_setopt($h,CURLOPT_POSTFIELDS,json_encode($body));
    $raw=curl_exec($h);if($raw===false)throw new RuntimeException(curl_error($h));
    return [curl_getinfo($h,CURLINFO_RESPONSE_CODE),json_decode($raw,true),$raw];
}
curl_setopt_array($h,[CURLOPT_URL=>$url,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['login'=>'pedro_imp','senha'=>'Pedro0808'])]);
$html=curl_exec($h);
if(!preg_match('/name="pagamento-csrf" content="([a-f0-9]+)"/',$html,$m))throw new RuntimeException('Prepare a fixture de valores por tarefa.');
$csrf=$m[1];$ctx=['colaborador_id'=>8,'competencia'=>'2026-09'];
[$code,$r]=taskRequest('obter',$ctx);
if($code!==200)throw new RuntimeException('Leitura HTTP falhou');
$r=$r['data'];
$item=array_values(array_filter($r['revisao']['financeiro_servicos']['itens_analisados'],fn($i)=>$i['situacao']==='DEVIDO'&&(int)$i['descricao']['funcao_id']===3&&!isset($i['ajuste_valor'])))[0];
$body=$ctx+['expected_version'=>$r['latest_version'],'idempotency_key'=>'http-value-'.bin2hex(random_bytes(5)),'tipo'=>'SERVICOS','input'=>['estado'=>'ALTERAR_VALOR','alvo'=>'ITEM','identidade'=>$item['identidade'],'valor'=>'151,25','motivo'=>'Teste HTTP sintético']];
[$code,$changed]=taskRequest('decidir',$body,$csrf);
if($code!==200 || (int)$changed['data']['revisao']['total_final_centavos']!==(int)$r['revisao']['total_final_centavos']+5125)throw new RuntimeException('Valor não atualizado pela API');
[$code,$retry]=taskRequest('decidir',$body,$csrf);
if($code!==200 || $retry['data']['revisao']['id']!==$changed['data']['revisao']['id'])throw new RuntimeException('Retry HTTP não idempotente');
[$code]=taskRequest('decidir',$body,'invalid');if($code!==403)throw new RuntimeException('CSRF não rejeitado');
$restore=$body;$restore['expected_version']=$changed['data']['latest_version'];$restore['idempotency_key'].='-restore';$restore['input']['estado']='RESTAURAR_VALOR';unset($restore['input']['valor']);
[$code,$restored]=taskRequest('decidir',$restore,$csrf);
if($code!==200 || $restored['data']['revisao']['total_final_centavos']!==$r['revisao']['total_final_centavos'])throw new RuntimeException('Restauração HTTP não recompôs total');
echo "HTTP isolado: edição, restauração, idempotência e CSRF passaram.\n";

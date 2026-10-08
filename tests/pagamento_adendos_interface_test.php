<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/fixtures/pagamento_adendos_interface.php';
require_once __DIR__.'/../Pagamento/services/FechamentoDocumentoFiles.php';
$fixture=json_decode(file_get_contents($argv[1]??''),true,512,JSON_THROW_ON_ERROR);
$db=$fixture['db']; $conn=documental_test_connection($db);
$checks=0; $cookie=''; $csrf='';
function eq($value,$expected,$label): void {global $checks;$checks++;if($value!==$expected)throw new RuntimeException($label.': '.json_encode($value).' != '.json_encode($expected));}
function http(string $route,string $method='GET',$data=null,?string $token=null,bool $session=true): array {
    global $cookie,$csrf;
    $ch=curl_init('http://localhost:8066/ImproovWeb/'.$route); $headers=['Accept: application/json'];
    curl_setopt_array($ch,[CURLOPT_RESOLVE=>['localhost:8066:127.0.0.1'],CURLOPT_PROXY=>'']);
    if($session && $cookie) $headers[]='Cookie: '.$cookie;
    if($token!==null) $headers[]='X-CSRF-Token: '.$token;
    if($data!==null){$headers[]='Content-Type: application/json';curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));}
    curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>30]);
    $raw=curl_exec($ch);if($raw===false)throw new RuntimeException(curl_error($ch));$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$size=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
    $body=substr($raw,$size); return [$status,json_decode($body,true),$body,substr($raw,0,$size)];
}
function login(string $user):void {
    global $cookie,$csrf;
    $ch=curl_init('http://localhost:8066/ImproovWeb/');
    curl_setopt_array($ch,[CURLOPT_RESOLVE=>['localhost:8066:127.0.0.1'],CURLOPT_PROXY=>'']);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['usuario'=>$user,'senha'=>getenv('PAGAMENTO_FIXTURE_PASSWORD')?:'']),CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true]);
    $raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);eq($status,302,'Login sintético');
    preg_match('/Set-Cookie:\s*([^;\r\n]+)/i',$raw,$m);$cookie=$m[1]??'';
    $r=http('fixture-menu');preg_match('/name="pagamento-csrf" content="([^"]+)"/',$r[2],$m);$csrf=$m[1]??'';
}
function api(string $action,array $data,bool $post=true,?int $expected=200): array {
    global $csrf;
    $route='Pagamento/api/'.(in_array($action,['preparar','obter','decidir','revisoes'],true)?'fechamento/':'documento/').$action.'.php';
    $r=http($route.($post?'':'?'.http_build_query($data)),$post?'POST':'GET',$post?$data:null,$post?$csrf:null);
    if($expected!==null)eq($r[0],$expected,$action.' HTTP');return $r;
}
if(($argv[2]??'')==='--page') {
    login('pedro_imp'); $page=http('Pagamento/fechamento.php');eq($page[0],200,'Página real isolada');
    foreach(['id="fc-colaborador"','id="fc-preview"','thinking-orbs.js','fechamento.js','pdf.min.js','id="fc-pdf-zoom-in"'] as $needle) eq(str_contains($page[2],$needle),true,'Página contém '.$needle);
    eq(http('Pagamento/fechamento.js')[0],200,'Módulo JS servido');
    eq(http('assets/js/thinking-orbs.js')[0],200,'Orb global servida');
    foreach(['Pagamento/fechamento-pdf.js','assets/pdfjs/pdf.min.js','assets/pdfjs/pdf.worker.min.js','gif/assinatura_preto.gif'] as $asset) eq(http($asset)[0],200,'Asset existente servido: '.$asset);
    echo "$checks verificações de render/asset HTTP OK; não substituem navegador.\n";exit;
}
$context=['colaborador_id'=>13,'competencia'=>'2026-09'];
eq(http('Pagamento/api/fechamento/obter.php?'.http_build_query($context),'GET',null,null,false)[0],401,'Sessão ausente');
login('restrito_fixture');eq(http('Pagamento/api/fechamento/obter.php?'.http_build_query($context))[0],403,'Nível não autorizado');
login('inativo_fixture');eq(http('Pagamento/api/fechamento/obter.php?'.http_build_query($context))[0],403,'Ator inativo no banco');
login('gestor_fixture');eq(api('obter',$context,false)[1]['success'],true,'Nível 5 ativo');login('pedro_imp');
$prepare=$context+['expected_version'=>0,'idempotency_key'=>'HTTP-prepare'];
eq(http('Pagamento/api/fechamento/preparar.php','POST',$prepare,'invalid')[0],419,'CSRF inválido');
api('preparar',$prepare+['usuario_id'=>8],true,422);
api('preparar',array_replace($prepare,['expected_version'=>null]),true,422);
api('preparar',array_replace($prepare,['competencia'=>'2026-13']),true,422);
api('preparar',array_replace($prepare,['colaborador_id'=>99999]),true,404);
$r=api('preparar',$prepare)[1]['data']['revisao'];eq($r['autor_id'],1,'Ator da sessão');eq($r['resumo']['BONUS_EXTRAS'],null,'Null preservado');eq($r['total_final_centavos'],null,'Total indeterminado');
eq(api('preparar',$prepare)[1]['data']['revisao']['id'],$r['id'],'Retry financeiro mesma revisão');
api('preparar',array_replace($prepare,['expected_version'=>1]),true,409);
$decision=$context+['expected_version'=>1,'idempotency_key'=>'HTTP-bonus','tipo'=>'BONUS','input'=>['estado'=>'SEM_BONUS','motivo'=>'Teste endpoint sintético','itens'=>[]]];
$ready=api('decidir',$decision)[1]['data']['revisao'];eq($ready['estado'],'PRONTO','Sem bônus pronto');
api('decidir',array_replace($decision,['idempotency_key'=>'HTTP-stale']),true,409);
eq(api('decidir',$decision)[1]['data']['revisao']['id'],$ready['id'],'Retry decisão');
eq(api('obter',$context+['revision_id'=>$r['id']],false)[1]['data']['revisao']['id'],$r['id'],'Histórico exato');
eq(count(api('revisoes',$context,false)[1]['data']),2,'Histórico financeiro');
$extra=api('decidir',$context+['expected_version'=>2,'idempotency_key'=>'HTTP-extra','tipo'=>'BONUS','input'=>['estado'=>'DEFINIDO','motivo'=>'Extra sintético','itens'=>[['categoria'=>'Qualidade','referencia'=>'HTTP-ref','valor'=>'125,50']]]])[1]['data']['revisao'];
eq($extra['total_final_centavos'],'12550','Total backend extra');
$fixed=api('decidir',$context+['expected_version'=>3,'idempotency_key'=>'HTTP-fixo','tipo'=>'FIXO','input'=>['estado'=>'OVERRIDE','motivo'=>'Fixo sintético','valor'=>'100,00']])[1]['data']['revisao'];eq($fixed['total_final_centavos'],null,'Fixo sem evidência pendente');
$reconciled=api('decidir',$context+['expected_version'=>4,'idempotency_key'=>'HTTP-liquidacao','tipo'=>'LIQUIDACAO','input'=>['estado'=>'EVIDENCIADA','motivo'=>'Apuração sintética','evidencias'=>[['tipo'=>'APURACAO_FIXO_SEM_PAGAMENTO','referencia'=>'HTTP-apuracao','valor'=>'0,00','origem_verificavel'=>'Fixture descartável']]]])[1]['data']['revisao'];eq($reconciled['total_final_centavos'],'22550','Fixo reconciliado');
$generate=$context+['fechamento_id'=>$ready['fechamento_id'],'revision_id'=>$reconciled['id'],'idempotency_key'=>'HTTP-pdf'];
$doc=api('gerar',$generate)[1]['data'];eq(isset($doc['arquivo_preview']),false,'Path não exposto');
eq(api('gerar',$generate)[1]['data']['document_id'],$doc['document_id'],'Retry documental');
api('gerar',$generate+['path'=>'../conexao.php'],true,422);
$confirm=$context+['document_id'=>$doc['document_id'],'revision_id'=>$doc['revision_id'],'pdf_hash'=>$doc['pdf_hash'],'idempotency_key'=>'HTTP-confirm'];
api('confirmar',$confirm,true,409);
api('visualizar',array_replace($context,['colaborador_id'=>3])+['document_id'=>$doc['document_id'],'idempotency_key'=>'HTTP-foreign'],true,404);
$view=api('visualizar',$context+['document_id'=>$doc['document_id'],'idempotency_key'=>'HTTP-view']);eq(substr($view[2],0,5),'%PDF-','PDF efetivo');eq(hash('sha256',$view[2]),$doc['pdf_hash'],'PDF visualizado hash');
api('confirmar',array_replace($confirm,['pdf_hash'=>str_repeat('a',64),'idempotency_key'=>'HTTP-wrong-hash']),true,409);
$new=api('preparar',$context+['expected_version'=>5,'idempotency_key'=>'HTTP-newer'])[1]['data']['revisao'];eq($new['numero'],6,'Revisão posterior ao preview');
$confirmed=api('confirmar',$confirm)[1]['data'];eq($confirmed['estado'],'CONFIRMADO','Confirmação');eq($confirmed['revision_id'],$doc['revision_id'],'Confirma preview antigo exato');
eq(api('confirmar',$confirm)[1]['data']['document_id'],$doc['document_id'],'Retry confirmação');
$again=api('visualizar',$context+['document_id'=>$doc['document_id'],'idempotency_key'=>'HTTP-definitivo']);eq($again[2],$view[2],'Definitivo mesmos bytes');
$row=$conn->query('SELECT arquivo_definitivo FROM pagamento_fechamento_documento WHERE id='.$doc['document_id'])->fetch_assoc();
$files=new FechamentoDocumentoFiles($fixture['root']); $path=$files->path($row['arquivo_definitivo']);
try { file_put_contents($path,$again[2].'CORRUPCAO FIXTURE'); api('visualizar',$context+['document_id'=>$doc['document_id'],'idempotency_key'=>'HTTP-corrupt'],true,422); }
finally { file_put_contents($path,$again[2]); }
// Falha real de publicação SQL depois da reserva e do arquivo: recovery via endpoint.
$conn->query("CREATE TRIGGER fixture_1d_fail_publish BEFORE UPDATE ON pagamento_fechamento_documento FOR EACH ROW BEGIN IF NEW.estado='PREVIEW' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture publication failure'; END IF; END");
try { api('gerar',$context+['fechamento_id'=>$new['fechamento_id'],'revision_id'=>$new['id'],'idempotency_key'=>'HTTP-reserve'],true,500); }
finally { $conn->query('DROP TRIGGER fixture_1d_fail_publish'); }
$list=api('listar',$context,false)[1]['data'];eq(count($list['operacoes_pendentes']),1,'Reserva visível');$op=$list['operacoes_pendentes'][0];
api('recuperar',$context+['operation_id'=>$op['id'],'idempotency_key'=>'wrong-key'],true,409);
$recovered=api('recuperar',$context+['operation_id'=>$op['id'],'idempotency_key'=>$op['chave']])[1]['data'];eq($recovered['estado'],'PREVIEW','Recovery HTTP');eq(count(api('listar',$context,false)[1]['data']['operacoes_pendentes']),0,'Reserva concluída');
api('visualizar',$context+['document_id'=>$recovered['document_id'],'idempotency_key'=>'HTTP-view-recovered']);
$conn->query("CREATE TRIGGER fixture_1d_fail_confirm BEFORE UPDATE ON pagamento_fechamento_documento FOR EACH ROW BEGIN IF NEW.estado='CONFIRMADO' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture confirmation failure'; END IF; END");
try { api('confirmar',$context+['document_id'=>$recovered['document_id'],'revision_id'=>$recovered['revision_id'],'pdf_hash'=>$recovered['pdf_hash'],'idempotency_key'=>'HTTP-confirm-reserve'],true,500); }
finally { $conn->query('DROP TRIGGER fixture_1d_fail_confirm'); }
$list=api('listar',$context,false)[1]['data'];$op=$list['operacoes_pendentes'][0];eq($op['tipo'],'CONFIRMAR','Reserva de confirmação visível');
$final=api('recuperar',$context+['operation_id'=>$op['id'],'idempotency_key'=>$op['chave']])[1]['data'];eq($final['estado'],'CONFIRMADO','Recovery confirmação HTTP');
eq(api('obter',['colaborador_id'=>1,'competencia'=>'2026-09'],false)[1]['data']['revisao']['resumo']['ACOMPANHAMENTO_ESPECIAL'],'400000','Especial Nicolle distinto do fixo');
eq(api('obter',['colaborador_id'=>4,'competencia'=>'2026-09'],false)[1]['data']['revisao']['resumo']['VALOR_FIXO'],null,'Fixo pendente não vira zero');
$div=api('obter',['colaborador_id'=>5,'competencia'=>'2026-09'],false)[1]['data']['revisao'];eq($div['situacao'],'DIVERGENCIA_FINANCEIRA','Divergência visível');eq($div['total_final_centavos'],null,'Divergência impede total');
$output=__DIR__.'/../output/pdf/fase1d';if(!is_dir($output))mkdir($output,0775,true);file_put_contents($output.'/endpoint-confirmado.pdf',$again[2]);
echo "$checks verificações HTTP FASE 1D OK; endpoints reais, sessão Flow e MySQL 8 sintético.\n";

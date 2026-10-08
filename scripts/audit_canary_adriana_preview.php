<?php
/** Canary evidence only: SELECT in a read-only transaction. Never performs financial/document writes. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$stage=$argv[1]??'';
if (!in_array($stage,['before','initial','bonus','preview'],true)) throw new InvalidArgumentException('Invalid evidence stage');
require __DIR__.'/../config/pagamento_fechamento.php';
require __DIR__.'/../Pagamento/services/FechamentoSnapshot.php';
$private='C:/ProgramData/ImproovWeb/private/deployment-backups/canary_adriana_202609/preview_'.$stage.'.json';
$public=__DIR__.'/../docs/evidence/pagamento-canary-adriana-preview-'.$stage.'.json';
if (file_exists($private)||file_exists($public)) throw new RuntimeException('Evidence already exists; do not overwrite');
$c=pagamento_fechamento_connection();
$c->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
try {
    $select=fn($sql)=>$c->query($sql)->fetch_all(MYSQLI_ASSOC);
    $ledger=$select('SELECT pi.*,p.mes_ref,p.status pagamento_status FROM pagamento_itens pi JOIN pagamentos p ON p.idpagamento=pi.pagamento_id WHERE p.colaborador_id=14 ORDER BY pi.idpagamento_item');
    $payments=$select('SELECT * FROM pagamentos WHERE colaborador_id=14 ORDER BY idpagamento');
    $h=$select('SELECT id,competencia,status,payload_enviado,arquivo_path FROM adendos WHERE id=150 AND colaborador_id=14')[0]??throw new RuntimeException('Historical document missing');
    $preserved=['ledger_rows'=>count($ledger),'ledger_sha256'=>hash('sha256',json_encode($ledger,JSON_THROW_ON_ERROR)), 'payments_rows'=>count($payments),'payments_sha256'=>hash('sha256',json_encode($payments,JSON_THROW_ON_ERROR)), 'historical_id'=>150,'historical_state'=>$h['status'],'historical_payload_sha256'=>hash('sha256',$h['payload_enviado']),'historical_pdf_sha256'=>hash_file('sha256',$h['arquivo_path'])];
    $f=$select("SELECT id,colaborador_id,competencia,estado,lock_version,numero_revisao,criado_por FROM pagamento_fechamento WHERE colaborador_id=14 AND competencia='2026-09'");
    $rows=$select("SELECT r.* FROM pagamento_fechamento_revisao r JOIN pagamento_fechamento f ON f.id=r.fechamento_id WHERE f.colaborador_id=14 AND f.competencia='2026-09' ORDER BY r.numero");
    $revisions=[];
    foreach ($rows as $r) {
        $s=json_decode($r['snapshot_json'],true,512,JSON_THROW_ON_ERROR); $x=$s['composicao'];
        if (!hash_equals($r['snapshot_hash'],FechamentoSnapshot::hash($s))) throw new RuntimeException('Revision hash mismatch');
        $due=$x['financeiro_servicos']['servicos_devidos']; $units=[];
        foreach($due as $item)$units[(string)$item['saldo_centavos']]=($units[(string)$item['saldo_centavos']]??0)+1;
        $revisions[]=['revision_id'=>(int)$r['id'],'version'=>(int)$r['numero'],'estado'=>$r['estado'],'situacao'=>$x['situacao'],'actor_id'=>(int)$r['criado_por'],'snapshot_hash'=>$r['snapshot_hash'],'snapshot_hash_valid'=>true,'servicos_devidos'=>count($due),'unit_values_centavos'=>$units,'componentes'=>$x['componentes'],'componentes_conhecidos_centavos'=>(int)$r['componentes_conhecidos_centavos'],'total_final_centavos'=>$r['total_final_centavos']===null?null:(int)$r['total_final_centavos'],'bonus_estado'=>$x['extras']['estado'],'extras_count'=>count($x['extras']['itens']),'especial_aplicavel'=>$x['acompanhamento_especial']['aplicavel'],'fixo_liquidacao'=>$x['fixo']['estado_liquidacao'],'pendencias'=>array_column($x['pendencias'],'codigo')];
    }
    $ops=$select("SELECT o.id,o.tipo,o.autor_id,o.chave,o.revisao_id,o.registrado_em,o.antes_json FROM pagamento_fechamento_operacao o JOIN pagamento_fechamento f ON f.id=o.fechamento_id WHERE f.colaborador_id=14 AND f.competencia='2026-09' ORDER BY o.id");
    foreach($ops as &$op){$op['antes']=json_decode($op['antes_json'],true,512,JSON_THROW_ON_ERROR);unset($op['antes_json']);}unset($op);
    $decisions=$select("SELECT d.id,d.tipo,d.autor_id,d.motivo,d.depois_json FROM pagamento_fechamento_decisao d JOIN pagamento_fechamento f ON f.id=d.fechamento_id WHERE f.colaborador_id=14 AND f.competencia='2026-09' ORDER BY d.id");
    foreach($decisions as &$d){$d['depois']=json_decode($d['depois_json'],true,512,JSON_THROW_ON_ERROR);unset($d['depois_json']);}unset($d);
    $docs=$select("SELECT d.* FROM pagamento_fechamento_documento d JOIN pagamento_fechamento f ON f.id=d.fechamento_id WHERE f.colaborador_id=14 AND f.competencia='2026-09' ORDER BY d.id");
    $documents=[]; $privateFiles=[];
    foreach($docs as $d){
        $model=json_decode($d['modelo_json'],true,512,JSON_THROW_ON_ERROR);
        $path='C:/ProgramData/ImproovWeb/private/pagamento-fechamento/'.$d['arquivo_preview'];
        $final='C:/ProgramData/ImproovWeb/private/pagamento-fechamento/'.$d['arquivo_definitivo'];
        $actual=is_file($path)?hash_file('sha256',$path):null;
        $documents[]=['document_id'=>(int)$d['id'],'revision_id'=>(int)$d['revisao_id'],'estado'=>$d['estado'],'actor_id'=>(int)$d['criado_por'],'pdf_hash'=>$d['pdf_hash'],'actual_pdf_hash'=>$actual,'pdf_hash_valid'=>$actual!==null&&hash_equals($d['pdf_hash']??'',$actual),'size_bytes'=>is_file($path)?filesize($path):null,'recorded_size_bytes'=>$d['tamanho_bytes'],'financial_snapshot_hash'=>$d['financial_snapshot_hash'],'model_revision_id'=>$model['revisao_id'],'model_competencia'=>$model['competencia'],'model_colaborador_id'=>$model['colaborador_id'],'model_services'=>count($model['servicos']),'model_service_values'=>array_count_values(array_column($model['servicos'],'valor_centavos')),'model_rubricas'=>$model['rubricas'],'model_total_centavos'=>$model['total_centavos'],'model_total_extenso'=>$model['placeholders']['valor_total_extenso'],'confirmado_por'=>$d['confirmado_por'],'confirmado_em'=>$d['confirmado_em'],'definitive_file_exists'=>is_file($final)];
        $privateFiles[]=['document_id'=>(int)$d['id'],'preview_path'=>$path,'definitive_path'=>$final];
    }
    $docOps=$select("SELECT o.id,o.tipo,o.estado,o.autor_id,o.chave,o.documento_id,o.request_json,o.criado_em,o.concluido_em FROM pagamento_fechamento_documento_operacao o JOIN pagamento_fechamento_documento d ON d.id=o.documento_id JOIN pagamento_fechamento f ON f.id=d.fechamento_id WHERE f.colaborador_id=14 AND f.competencia='2026-09' ORDER BY o.id");
    foreach($docOps as &$op){$op['request']=json_decode($op['request_json'],true,512,JSON_THROW_ON_ERROR);unset($op['request_json']);}unset($op);
    $expectedRows=['before'=>0,'initial'=>1,'bonus'=>2,'preview'=>2];
    if(count($revisions)!==$expectedRows[$stage])throw new RuntimeException('Unexpected revision count: '.count($revisions));
    if($stage!=='before'){
        $last=$revisions[count($revisions)-1];
        if($last['servicos_devidos']!==64||$last['unit_values_centavos']!==[6000=>64]||$last['componentes']['SERVICOS']!==384000||$last['componentes']['VALOR_FIXO']!==0||$last['componentes']['ACOMPANHAMENTO_ESPECIAL']!==0||$last['especial_aplicavel']!==false)throw new RuntimeException('Financial benchmark mismatch');
        if($stage==='initial'){
            if($last['estado']!=='PENDENTE'||$last['total_final_centavos']!==null||$last['pendencias']!==['BONUS_PENDENTE'])throw new RuntimeException('Initial pending state mismatch');
        }else{
            if($last['estado']!=='PRONTO'||$last['total_final_centavos']!==384000||$last['bonus_estado']!=='SEM_BONUS'||$last['extras_count']!==0||$last['componentes']['BONUS_EXTRAS']!==0||$last['pendencias']!==[])throw new RuntimeException('Post-bonus financial benchmark mismatch');
        }
        $baseline=json_decode(file_get_contents(__DIR__.'/../docs/evidence/pagamento-canary-adriana-preview-before.json'),true,512,JSON_THROW_ON_ERROR);
        if($baseline['preserved']!==$preserved)throw new RuntimeException('Legacy payment/historical data changed');
    }
    foreach($docOps as $op)if($op['tipo']==='CONFIRMAR')throw new RuntimeException('Forbidden confirmation operation exists');
    if($stage==='preview'){
        if(count($documents)!==1)throw new RuntimeException('Unexpected preview count');
        $doc=$documents[0]; $last=$revisions[1];
        if($doc['estado']!=='PREVIEW'||!$doc['pdf_hash_valid']||$doc['revision_id']!==$last['revision_id']||$doc['model_revision_id']!==$last['revision_id']||$doc['financial_snapshot_hash']!==$last['snapshot_hash']||$doc['model_total_centavos']!==384000||$doc['model_services']!==64||$doc['confirmado_em']!==null||$doc['confirmado_por']!==null||$doc['definitive_file_exists'])throw new RuntimeException('Preview evidence mismatch');
    }elseif($documents)throw new RuntimeException('Unexpected document before preview stage');
    $out=['result'=>'OK','at'=>(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format(DATE_ATOM),'stage'=>$stage,'colaborador_id'=>14,'competencia'=>'2026-09','fechamento'=>$f,'revisions'=>$revisions,'decisions'=>$decisions,'financial_operations'=>$ops,'documents'=>$documents,'document_operations'=>$docOps,'preserved'=>$preserved];
    file_put_contents($private,json_encode($out+['files'=>$privateFiles],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    file_put_contents($public,json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}finally{$c->rollback();$c->close();}

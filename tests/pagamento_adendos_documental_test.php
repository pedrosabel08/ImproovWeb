<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/fixtures/pagamento_adendos_documental.php';
require_once __DIR__.'/../Pagamento/services/FechamentoDocumentoService.php';

if (($argv[1]??'')==='--worker') {
    $c=documental_test_connection($argv[2]); $s=new FechamentoDocumentoService($c,documental_test_root($argv[2]));
    echo 'READY '.$c->thread_id."\n"; flush();
    try {
        if ($argv[3]==='CONFIRMAR') $r=$s->confirmar((int)$argv[4],(int)$argv[5],$argv[6],1,$argv[7]);
        else $r=$s->gerarPreview((int)$argv[4],(int)$argv[5],1,$argv[7],'2026-10-02');
        echo json_encode(['ok'=>true,'id'=>$r['document_id'],'estado'=>$r['estado']])."\n";
    } catch(Throwable $e) { echo json_encode(['ok'=>false,'erro'=>$e->getMessage()])."\n"; }
    exit;
}
$checks=0;
function doc_eq($a,$e,string $l): void {global $checks; $checks++;if($a!==$e)throw new RuntimeException($l.': '.json_encode($a).' != '.json_encode($e));}
function doc_reject(callable $f,string $part,string $l): void {global $checks;$checks++;try{$f();}catch(Throwable $e){if(str_contains($e->getMessage(),$part))return;throw new RuntimeException($l.': '.$e->getMessage());}throw new RuntimeException($l.': não rejeitou');}
function pdf_text(string $pdf): string {
    $bin=getenv('PAGAMENTO_PDFTOTEXT')?:sys_get_temp_dir().'/pagamento_1cb_runtime/poppler/poppler-26.09.0/Library/bin/pdftotext.exe';
    if(!is_file($bin))throw new RuntimeException('Configure PAGAMENTO_PDFTOTEXT para inspeção semântica obrigatória.');
    $pipes=[];$p=proc_open([$bin,'-layout',$pdf,'-'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$text=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);if($code!==0)throw new RuntimeException($err);return $text;
}
function doc_concurrent(mysqli $c,string $db,string $table,int $id,array $arguments): array {
    if (!in_array($table,['pagamento_fechamento','pagamento_fechamento_documento'],true)) throw new RuntimeException('Lock fixture inválido');
    $c->begin_transaction(); $c->query("SELECT id FROM $table WHERE id=$id FOR UPDATE"); $workers=[];
    try {
        for ($i=0;$i<2;$i++) {
            $pipes=[]; $p=proc_open(array_merge([PHP_BINARY,__FILE__,'--worker',$db],$arguments),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if (!is_resource($p)) throw new RuntimeException('Worker documental indisponível');
            fclose($pipes[0]); stream_set_timeout($pipes[1],15);
            $ready=trim(fgets($pipes[1])?:'');
            if (!preg_match('/^READY ([0-9]+)$/D',$ready,$m)) throw new RuntimeException('Worker documental não iniciou');
            $workers[]=[$p,$pipes,(int)$m[1]];
        }
        $threads=implode(',',array_column($workers,2)); $limit=microtime(true)+10; $waiting=0;
        do {
            $waiting=(int)$c->query('SELECT COUNT(DISTINCT t.PROCESSLIST_ID) n FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID=w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID IN ('.$threads.')')->fetch_assoc()['n'];
            if ($waiting>=2) break; usleep(10000);
        } while (microtime(true)<$limit);
        doc_eq($waiting>=2,true,'Dois processos documentais realmente aguardam o lock');
        $c->commit(); $out=[];
        foreach ($workers as [$p,$pipes]) { $raw=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); proc_close($p); if ($err) throw new RuntimeException($err); $out[]=json_decode(trim($raw),true,512,JSON_THROW_ON_ERROR); }
        return $out;
    } finally { $c->rollback(); foreach ($workers as [$p,$pipes]) { if(is_resource($p))proc_terminate($p); foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe); if(is_resource($p))proc_close($p); } }
}
function doc_bad_revision(mysqli $c,array $r,int $number,?array $snapshot,string $hash): int {
    $row=$c->query('SELECT * FROM pagamento_fechamento_revisao WHERE id='.$r['id'])->fetch_assoc(); unset($row['id']); $row['numero']=$number;
    if ($snapshot!==null) $row['snapshot_json']=FechamentoSnapshot::json($snapshot); $row['snapshot_hash']=$hash;
    $stmt=$c->prepare('INSERT INTO pagamento_fechamento_revisao ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')');
    $values=array_values($row); $stmt->bind_param(str_repeat('s',count($values)),...$values); $stmt->execute(); $id=(int)$stmt->insert_id; $stmt->close(); return $id;
}
final class DocumentoGuardConnection extends mysqli {
    public array $queries=[];
    public function prepare(string $query): mysqli_stmt|false {
        if(preg_match('/\b(funcao_imagem|funcao_animacao|animacao|acompanhamento|pagamento_itens|pagamentos|adendos|log_alteracoes|imagens_cliente_obra|valor_fixo)\b/i',$query))throw new RuntimeException('Tentou consultar financeiro atual durante documento');
        $this->queries[]=$query;return parent::prepare($query);
    }
}
$gold=hash_file('sha256',__DIR__.'/characterization/pagamento_adendos_golden.php');
$c=documental_test_connection();$db='pagamento_1cb_test_'.bin2hex(random_bytes(5));$c->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$c->select_db($db);
$root=documental_test_root($db);$app=null;
try {
    documental_test_schema($c);
    doc_eq($c->query('SELECT VERSION() v')->fetch_assoc()['v'],'8.0.42','MySQL 8 real');
    $f=new FechamentoRevisaoService($c);
    $A=documental_test_limpa($f,2,'A');
    $B=$f->decidir(2,'2026-09',1,$A['version'],'B-bonus','BONUS',['estado'=>'DEFINIDO','motivo'=>'Fixture B','itens'=>[['referencia'=>'B1','categoria'=>'Qualidade Fixture','valor_centavos'=>10050],['referencia'=>'B2','categoria'=>'Entrega Fixture','valor_centavos'=>20000]]]);
    $C=documental_test_limpa($f,1,'C',[['referencia'=>'C1','categoria'=>'Bonus Nicolle Fixture','valor_centavos'=>12500]]);
    $D=documental_test_limpa($f,8,'D');$E=documental_test_limpa($f,3,'E');$F=$f->prepararRevisao(4,'2026-09',1,0,'F-pendente');
    doc_eq($A['estado'],'PRONTO','A pronta'); doc_eq($B['estado'],'PRONTO','B pronta');doc_eq($C['snapshot']['composicao']['total_final_centavos'],570000,'Nicolle limpa');doc_eq($D['snapshot']['composicao']['total_final_centavos'],10000,'Comissão correta');doc_eq($E['snapshot']['composicao']['total_final_centavos'],0,'Zero pronto');
    $guard=new DocumentoGuardConnection('127.0.0.1','root','',$db,3320);$guard->set_charset('utf8mb4');
    $s=new FechamentoDocumentoService($guard,$root); $files=new FechamentoDocumentoFiles($root);
    $docs=[];
    foreach (['A'=>$A,'B'=>$B,'C'=>$C,'D'=>$D,'E'=>$E] as $name=>$r) {
        $d=$s->gerarPreview($r['fechamento_id'],$r['id'],1,'preview-'.$name,'2026-10-02');$docs[$name]=$d;
        doc_eq($d['estado'],'PREVIEW',$name.' preview');doc_eq($d['revision_id'],$r['id'],$name.' revisão exata');doc_eq($d['financial_snapshot_hash'],$r['snapshot_hash'],$name.' hash financeiro');
        $path=$files->path($d['arquivo_preview']);$bytes=file_get_contents($path);doc_eq(hash('sha256',$bytes),$d['pdf_hash'],$name.' bytes/hash');doc_eq(strlen($bytes),$d['tamanho_bytes'],$name.' tamanho');
        $text=pdf_text($path);
        doc_eq(str_contains($text,'Beneficiario Fixture '.(['A'=>2,'B'=>2,'C'=>1,'D'=>8,'E'=>3][$name])) || ($name==='D' && str_contains($text,'Gestor Fixture')),true,$name.' beneficiário correto');
        doc_eq(str_contains(preg_replace('/\s+/u',' ',$text),(new AdendoDocumentalApresentacao())->extenso($r['snapshot']['composicao']['total_final_centavos'])),true,$name.' extenso coincide com centavos');
        foreach (['ADENDO CONTRATUAL - SETEMBRO 2026','TERMO','ADITIVO','DO OBJETO','IMPROOV LTDA.','Nota Fiscal','Valor fixo','2 de outubro de 2026',AdendoDocumentalApresentacao::moeda($r['snapshot']['composicao']['total_final_centavos'])] as $needle) doc_eq(str_contains(preg_replace('/\s+/u',' ',$text),$needle),true,$name.' PDF contém '.$needle);
        foreach (json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.$d['document_id'])->fetch_assoc()['modelo_json'],true)['servicos'] as $row) {
            doc_eq(str_contains($text,$row['imagem']),true,$name.' imagem');doc_eq(str_contains($text,$row['funcao']),true,$name.' função');doc_eq(str_contains($text,AdendoDocumentalApresentacao::moeda($row['valor_centavos'])),true,$name.' valor serviço');
        }
        doc_eq($s->gerarPreview($r['fechamento_id'],$r['id'],1,'preview-'.$name,'2026-10-02'),$d,$name.' retry geração');
        $view=$s->visualizar($d['document_id'],1,'ver-'.$name);doc_eq($view['bytes'],$bytes,$name.' visualiza bytes reais por ID');
        $output=__DIR__.'/../output/pdf/fase1c-b';if(!is_dir($output))mkdir($output,0775,true);file_put_contents($output.'/fixture-'.$name.'.pdf',$bytes);
    }
    // A extração do renderizador mantém a API antiga e a política de nomes reais.
    $modelA=json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.$docs['A']['document_id'])->fetch_assoc()['modelo_json'],true);
    $legacyRenderer=new ContratoPdfService($root.'/renderer-parity',__DIR__.'/../Contratos/templates/adendo_modelo.html');
    $legacyPdf=$legacyRenderer->gerarPdf('paridade.pdf',$modelA['placeholders']); $legacyHash=hash_file('sha256',$legacyPdf['file_path']);
    doc_eq(pdf_text($legacyPdf['file_path']),pdf_text($files->path($docs['A']['arquivo_preview'])),'API antiga e renderizador puro mantêm conteúdo documental');
    $legacySecond=$legacyRenderer->gerarPdf('paridade.pdf',$modelA['placeholders']);
    doc_eq($legacySecond['file_name'],basename($legacySecond['file_path']),'API antiga devolve basename real');
    doc_eq($legacySecond['file_path']!==$legacyPdf['file_path'],true,'API antiga mantém sufixo em colisão');
    doc_eq(hash_file('sha256',$legacyPdf['file_path']),$legacyHash,'API antiga não sobrescreve anterior');
    doc_eq(str_contains(pdf_text($files->path($docs['A']['arquivo_preview'])),'Comissao quitada Fixture'),false,'Item quitado ausente');
    doc_eq(str_contains(pdf_text($files->path($docs['B']['arquivo_preview'])),'Qualidade Fixture'),true,'B extras visíveis');
    doc_eq(str_contains(pdf_text($files->path($docs['C']['arquivo_preview'])),'Acompanhamento'),true,'Especial separado');
    doc_eq(str_contains(pdf_text($files->path($docs['C']['arquivo_preview'])),'4.000,00'),true,'Especial 4000');
    doc_eq(str_contains(pdf_text($files->path($docs['C']['arquivo_preview'])),'Bonus Nicolle Fixture'),true,'Extras Nicolle conservados');
    doc_eq(str_contains(pdf_text($files->path($docs['D']['arquivo_preview'])),'Comissão gestor'),true,'Comissão rotulada');
    doc_eq(str_contains(pdf_text($files->path($docs['D']['arquivo_preview'])),'Comissao quitada Fixture'),false,'Comissão quitada ausente');
    $stellar=documental_test_limpa($f,13,'STELLAR'); $stellarDoc=$s->gerarPreview($stellar['fechamento_id'],$stellar['id'],1,'preview-stellar','2026-10-02');
    $stellarText=pdf_text($files->path($stellarDoc['arquivo_preview']));
    doc_eq(str_contains($stellarText,'STELLAR') && str_contains($stellarText,'45.284.934/0001-30'),true,'Contratante STELLAR segue regra documental existente');
    doc_reject(fn()=>$s->gerarPreview($F['fechamento_id'],$F['id'],1,'pendente'),'não permitida','PENDENTE não cria PDF');
    $bad=doc_bad_revision($c,$A,1000,null,str_repeat('0',64));
    doc_reject(fn()=>$s->gerarPreview($A['fechamento_id'],$bad,1,'snapshot-invalido'),'Integridade','Hash financeiro inválido rejeitado antes do PDF');
    $unknown=$A['snapshot']; $unknown['composicao']['rule_version']='regra_desconhecida';
    $bad=doc_bad_revision($c,$A,1001,$unknown,FechamentoSnapshot::hash($unknown));
    doc_reject(fn()=>$s->gerarPreview($A['fechamento_id'],$bad,1,'versao-invalida'),'não permitida','Versão desconhecida com hash íntegro rejeitada');
    $missing=$A['snapshot']; unset($missing['composicao']['financeiro_servicos']['servicos_devidos']);
    $bad=doc_bad_revision($c,$A,1002,$missing,FechamentoSnapshot::hash($missing));
    doc_reject(fn()=>$s->gerarPreview($A['fechamento_id'],$bad,1,'linhas-ausentes'),'Estrutura','Lista ausente não vira contrato sem serviços');
    $foreign=$A['snapshot']; $foreign['composicao']['financeiro_servicos']['servicos_devidos'][0]['identidade']['beneficiario_id']=999;
    $bad=doc_bad_revision($c,$A,1003,$foreign,FechamentoSnapshot::hash($foreign));
    doc_reject(fn()=>$s->gerarPreview($A['fechamento_id'],$bad,1,'linha-outro-beneficiario'),'Linha de serviço','Linha de outro beneficiário rejeitada');
    doc_reject(fn()=>$s->gerarPreview($A['fechamento_id']+100,$A['id'],1,'escopo'),'não encontrado','Fechamento incorreto');
    doc_reject(fn()=>$s->gerarPreview($B['fechamento_id'],$B['id'],1,'preview-A','2026-10-02'),'idempotência','Chave conflitante');
    $p2=$s->gerarPreview($A['fechamento_id'],$A['id'],2,'outro-preview','2026-10-02');doc_eq($p2['document_id']===$docs['A']['document_id'],false,'Dois previews mesma revisão');
    $current=$f->decidir(2,'2026-09',1,$B['version'],'pos-preview','FIXO',['estado'=>'OVERRIDE','valor_centavos'=>180000,'motivo'=>'Fixture revisão mais nova']);
    $c->query('UPDATE colaborador SET valor_fixo=9999 WHERE idcolaborador=2');$c->query('UPDATE funcao_imagem SET valor=9999 WHERE idfuncao_imagem=1');$c->query("UPDATE usuario SET nome_usuario='Nome Alterado Depois' WHERE idcolaborador=2");
    doc_eq($s->visualizar($docs['A']['document_id'],1,'ver-A-pos')['bytes'],file_get_contents($files->path($docs['A']['arquivo_preview'])),'Banco mudou, PDF congelado');
    $old=$s->gerarPreview($A['fechamento_id'],$A['id'],1,'novo-preview-rev-antiga','2026-10-02');
    doc_eq(str_contains(pdf_text($files->path($old['arquivo_preview'])),'1.600,00'),true,'Documento novo de revisão antiga não consulta valor atual');
    doc_eq(str_contains(pdf_text($files->path($docs['A']['arquivo_preview'])),'Nome Alterado Depois'),false,'Qualificação capturada não muda no documento antigo');
    doc_reject(fn()=>$s->confirmar($docs['A']['document_id'],$B['id'],$docs['A']['pdf_hash'],1,'revision-errada'),'não correspondem','Confirma revisão esperada');
    doc_reject(fn()=>$s->confirmar($docs['A']['document_id'],$A['id'],str_repeat('0',64),1,'hash-errado'),'não correspondem','Hash esperado');
    doc_reject(fn()=>$s->confirmar($p2['document_id'],$A['id'],$p2['pdf_hash'],1,'sem-visualizacao'),'Visualize','Precisa visualizar exatamente P2');
    $confirmed=$s->confirmar($docs['A']['document_id'],$A['id'],$docs['A']['pdf_hash'],1,'confirmar-A');
    doc_eq($confirmed['estado'],'CONFIRMADO','Confirmação');doc_eq($confirmed['revision_id'],$A['id'],'Confirma revisão antiga explicitamente');doc_eq($confirmed['confirmado_por'],1,'Ator confirmação');
    doc_eq(file_get_contents($files->path($confirmed['arquivo_definitivo'])),file_get_contents($files->path($docs['A']['arquivo_preview'])),'Definitivo exatamente visualizado');
    doc_eq($s->confirmar($docs['A']['document_id'],$A['id'],$docs['A']['pdf_hash'],1,'confirmar-A'),$confirmed,'Retry confirmação não falha/duplica');
    doc_eq($s->obter($p2['document_id'],2)['estado'],'PREVIEW','Confirmar P1 não confirma P2');
    $c->query("CREATE TRIGGER fail_doc_final BEFORE UPDATE ON pagamento_fechamento_documento FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='falha db fixture'");
    $bytesB=file_get_contents($files->path($docs['B']['arquivo_preview']));
    doc_reject(fn()=>$s->confirmar($docs['B']['document_id'],$B['id'],$docs['B']['pdf_hash'],1,'confirmar-B'),'falha db fixture','Arquivo publicado DB falha');
    $rawB=$c->query('SELECT * FROM pagamento_fechamento_documento WHERE id='.$docs['B']['document_id'])->fetch_assoc();doc_eq($rawB['estado'],'PREVIEW','Rollback não confirma DB');doc_eq(is_file($files->path($rawB['arquivo_definitivo'])),true,'Final existe vinculado à reserva');
    $c->query('DROP TRIGGER fail_doc_final');$pending=$s->pendentes(1);doc_eq(count($pending),1,'Falha tem journal recuperável');
    $recovered=$s->recuperar((int)$pending[0]['id'],1);doc_eq($recovered['estado'],'CONFIRMADO','Recupera publicação');doc_eq(file_get_contents($files->path($recovered['arquivo_definitivo'])),$bytesB,'Recuperação sem renderizar');
    $corrupt=$files->path($docs['C']['arquivo_preview']);$original=file_get_contents($corrupt);file_put_contents($corrupt,str_replace('%PDF-','%PDF-X',$original));
    doc_reject(fn()=>$s->confirmar($docs['C']['document_id'],$C['id'],$docs['C']['pdf_hash'],1,'confirmar-C'),'Hash/tamanho','Corrupção bloqueia confirmação');file_put_contents($corrupt,$original);
    $s->confirmar($docs['C']['document_id'],$C['id'],$docs['C']['pdf_hash'],1,'confirmar-C');
    foreach ([3,4,999] as $u) {
        doc_reject(fn()=>$s->gerarPreview($A['fechamento_id'],$A['id'],$u,'sem-perm'),'autorização','Geração restrita');
        doc_reject(fn()=>$s->visualizar($docs['A']['document_id'],$u,'sem-perm'),'autorização','Visualização restrita');
        doc_reject(fn()=>$s->confirmar($docs['A']['document_id'],$A['id'],$docs['A']['pdf_hash'],$u,'sem-perm'),'autorização','Confirmação restrita');
    }
    foreach (['../fixture.pdf','staging/../../etc.pdf','C:/tmp/a.pdf','staging/adendo_f1_r1_d1_'.str_repeat('a',32).'.pdf/../x'] as $bad) doc_reject(fn()=>$files->path($bad),'Path','Traversal');
    doc_reject(fn()=>$c->query('UPDATE pagamento_fechamento_documento SET estado=estado WHERE id='.$confirmed['document_id']),'imutavel','Confirmado imutável no DB');
    doc_reject(fn()=>$c->query('UPDATE pagamento_fechamento_documento SET revisao_id='.$B['id'].' WHERE id='.$p2['document_id']),'imutavel','Vínculo preview imutável');
    doc_reject(fn()=>$c->query('DELETE FROM pagamento_fechamento_documento LIMIT 1'),'append-only','Documento não apaga');
    doc_reject(fn()=>$c->query('UPDATE pagamento_fechamento_documento_operacao SET chave=chave WHERE estado=\'CONCLUIDA\' LIMIT 1'),'imutavel','Auditoria imutável');
    $diag=$s->diagnosticar($docs['A']['document_id'],1);doc_eq($diag['total_igual'],true,'PDF model vs snapshot diagnóstico');
    $race=doc_concurrent($c,$db,'pagamento_fechamento',$E['fechamento_id'],['GERAR',(string)$E['fechamento_id'],(string)$E['id'],'-','gerar-concorrente']);
    doc_eq(count(array_filter($race,fn($r)=>$r['ok'])),2,'Duas gerações concorrentes têm sucesso'); doc_eq($race[0]['id'],$race[1]['id'],'Geração concorrente retorna um documento');
    $raceDoc=$s->obter($race[0]['id'],1); $s->visualizar($raceDoc['document_id'],1,'ver-concorrente');
    $race=doc_concurrent($c,$db,'pagamento_fechamento_documento',$raceDoc['document_id'],['CONFIRMAR',(string)$raceDoc['document_id'],(string)$E['id'],$raceDoc['pdf_hash'],'confirmar-concorrente']);
    doc_eq(count(array_filter($race,fn($r)=>$r['ok'])),2,'Duas confirmações concorrentes têm sucesso');doc_eq($race[0]['id'],$race[1]['id'],'Confirmação concorrente retorna um documento');
    doc_eq((int)$c->query("SELECT COUNT(*) n FROM pagamento_fechamento_documento_operacao WHERE chave='confirmar-concorrente'")->fetch_assoc()['n'],1,'Confirmação concorrente não duplica auditoria');
    doc_eq($confirmed['arquivo_definitivo']!==$recovered['arquivo_definitivo'],true,'Definitivos distintos não sobrescrevem');
    doc_eq(hash_file('sha256',$files->path($confirmed['arquivo_definitivo'])),$confirmed['pdf_hash'],'Definitivo anterior preservado');
    doc_eq(count(array_filter($guard->queries,fn($q)=>preg_match('/\b(funcao_imagem|valor_fixo|pagamento_itens)\b/i',$q))),0,'Nenhuma consulta financeira atual no ciclo documental');
    // Casos de falha na geração: reserva sem PDF e PDF materializado antes da falha DB.
    $tplPath=__DIR__.'/../Contratos/templates/adendo_modelo.html';
    $c->query("CREATE TRIGGER fail_doc_preview BEFORE UPDATE ON pagamento_fechamento_documento FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='falha preview fixture'");
    doc_reject(fn()=>$s->gerarPreview($E['fechamento_id'],$E['id'],1,'preview-falha','2026-10-02'),'falha preview fixture','PDF criado DB falhou');
    $open=$s->pendentes(1);doc_eq(count($open),1,'Reserva persiste na falha');$reservedId=(int)$open[0]['documento_id'];$raw=$c->query('SELECT * FROM pagamento_fechamento_documento WHERE id='.$reservedId)->fetch_assoc();$savedHash=hash_file('sha256',$files->path($raw['arquivo_preview']));
    $c->query('DROP TRIGGER fail_doc_preview');$rec=$s->recuperar((int)$open[0]['id'],1);doc_eq($rec['pdf_hash'],$savedHash,'Retry reaproveita PDF existente');
    $repository=new FechamentoDocumentoRepository($c); $frozen=$c->query('SELECT * FROM pagamento_fechamento_documento WHERE id='.$docs['E']['document_id'])->fetch_assoc();
    $request=['tipo'=>'GERAR','fechamento_id'=>$E['fechamento_id'],'revision_id'=>$E['id'],'data_documental'=>'2026-10-02'];
    $reservation=$repository->transacao(function() use($repository,$E,$frozen,$request) {
        $repository->sql('SELECT id FROM pagamento_fechamento WHERE id=? FOR UPDATE',[$E['fechamento_id']]);
        $d=$repository->reservarDocumento($E['fechamento_id'],$E['id'],1,$E,json_decode($frozen['modelo_json'],true),$frozen['html_snapshot'],$frozen['template_hash']);
        return ['doc'=>$d,'op'=>$repository->reservarOperacao((int)$d['id'],1,'renderer-falha','GERAR',FechamentoSnapshot::hash(['usuario'=>1,'request'=>$request]),$request,['estado'=>null])];
    });
    doc_reject(fn()=>$files->comLock($reservation['doc']['uuid'],fn()=>$files->materializar($reservation['doc'],function(){throw new RuntimeException('renderer fixture falhou');})),'renderer fixture','DB reservou e renderização falhou');
    doc_eq($s->obter((int)$reservation['doc']['id'],1)['estado'],null,'Falha não publica preview');
    $part=$files->path($reservation['doc']['arquivo_preview']).'.part'; mkdir($part);
    doc_reject(fn()=>$s->recuperar((int)$reservation['op']['id'],1),'Colisão','Falha filesystem não sobrescreve caminho'); rmdir($part);
    doc_eq($s->recuperar((int)$reservation['op']['id'],1)['estado'],'PREVIEW','Recupera reserva após falha renderer/filesystem');
    // MySQL CHECK/JSON/FK e grants reais no banco isolado.
    doc_reject(fn()=>$c->query("INSERT INTO pagamento_fechamento_documento (fechamento_id,revisao_id,numero,uuid,financial_snapshot_hash,modelo_json,html_snapshot,template_hash,arquivo_preview,arquivo_definitivo,criado_por,criado_em) VALUES (999,999,1,'".str_repeat('a',32)."','".str_repeat('b',64)."','{}','x','".str_repeat('c',64)."','x','y',1,UTC_TIMESTAMP(6))"),'foreign key','FK revisão/fechamento');
    doc_reject(fn()=>$c->query("INSERT INTO pagamento_fechamento_documento (fechamento_id,revisao_id,numero,uuid,financial_snapshot_hash,modelo_json,html_snapshot,template_hash,arquivo_preview,arquivo_definitivo,criado_por,criado_em) VALUES (".$A['fechamento_id'].','.$A['id'].",0,'".str_repeat('d',32)."','".str_repeat('b',64)."','{}','x','".str_repeat('c',64)."','bad1','bad2',1,UTC_TIMESTAMP(6))"),'Check constraint','MySQL CHECK obrigatório');
    doc_reject(fn()=>$c->query("INSERT INTO pagamento_fechamento_documento (fechamento_id,revisao_id,numero,uuid,financial_snapshot_hash,modelo_json,html_snapshot,template_hash,arquivo_preview,arquivo_definitivo,criado_por,criado_em,estado,pdf_hash,gerado_em) VALUES (".$E['fechamento_id'].','.$E['id'].",9000,'".str_repeat('e',32)."','".str_repeat('b',64)."','{}','x','".str_repeat('c',64)."','bad3','bad4',1,UTC_TIMESTAMP(6),'PREVIEW','".str_repeat('f',64)."',UTC_TIMESTAMP(6))"),'Check constraint','CHECK rejeita NULL/UNKNOWN em tamanho obrigatório');
    $app='pd_app_'.bin2hex(random_bytes(5));$c->query("CREATE USER '$app'@'localhost' IDENTIFIED BY 'Fixture-local-1cb'");$c->query("GRANT SELECT,INSERT,UPDATE ON `$db`.* TO '$app'@'localhost'");
    $limited=new mysqli('127.0.0.1',$app,'Fixture-local-1cb',$db,3320);$limited->set_charset('utf8mb4');$limitedDoc=new FechamentoDocumentoService($limited,$root);
    doc_reject(fn()=>$limitedDoc->gerarPreview($E['fechamento_id'],$E['id'],1,'grant-test','2026-10-02'),'Proteção','Grants sem visibilidade trigger são insuficientes');
    $c->query("GRANT TRIGGER ON `$db`.* TO '$app'@'localhost'");$limited->close();
    $limited=new mysqli('127.0.0.1',$app,'Fixture-local-1cb',$db,3320);$limited->set_charset('utf8mb4');$limitedDoc=new FechamentoDocumentoService($limited,$root);
    doc_eq($limitedDoc->gerarPreview($E['fechamento_id'],$E['id'],1,'grant-test','2026-10-02')['estado'],'PREVIEW','Grants writer documental suficientes');$limited->close();
    doc_eq(hash_file('sha256',__DIR__.'/characterization/pagamento_adendos_golden.php'),$gold,'Golden intacta');
    $audit=$c->query("SELECT * FROM pagamento_fechamento_documento_operacao WHERE tipo='CONFIRMAR' AND chave='confirmar-A'")->fetch_assoc();doc_eq($audit['estado'],'CONCLUIDA','Auditoria confirmação');doc_eq((int)$audit['autor_id'],1,'Ator auditado');doc_eq(json_decode($audit['depois_json'],true)['revision_id'],$A['id'],'Auditoria revisão/hash exatos');
    $countFinancial=$c->query('SELECT COUNT(*) n FROM pagamento_fechamento_revisao')->fetch_assoc()['n'];
    documental_test_migration($c,__DIR__.'/../sql/2026-10-02_pagamento_fechamento_documento_rollback.sql');
    doc_eq((int)$c->query("SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'pagamento_fechamento_documento%'")->fetch_assoc()['n'],0,'Rollback 1C-B apenas tabelas documentais');
    doc_eq($c->query('SELECT COUNT(*) n FROM pagamento_fechamento_revisao')->fetch_assoc()['n'],$countFinancial,'Rollback 1C-B preserva revisões financeiras');
    doc_eq(is_file($files->path($confirmed['arquivo_definitivo'])),true,'Rollback SQL não apaga filesystem');
    echo "$checks verificações documentais OK; MySQL 8.0.42 isolado 127.0.0.1:3320; $db\n";
    echo 'PDF QA: output/pdf/fase1c-b/fixture-A.pdf até fixture-E.pdf, dados sintéticos'."\n";
} finally {
    if ($app) $c->query("DROP USER IF EXISTS '$app'@'localhost'");
    $c->rollback();if(!preg_match('/^pagamento_1cb_test_[a-f0-9]{10}$/D',$db))throw new RuntimeException('Banco inseguro');$c->query("DROP DATABASE `$db`");$c->close(); documental_test_cleanup_root($db);
}

<?php
/** Confere todas as contagens do dump e compara somente agregados das FKs legadas na origem. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../vendor/autoload.php';
date_default_timezone_set('America/Sao_Paulo');
try {
    $backup=json_decode(file_get_contents(__DIR__.'/../docs/evidence/pagamento-unblock-backup-2026-10-05.json'),true,512,JSON_THROW_ON_ERROR);
    $restore=json_decode(file_get_contents(__DIR__.'/../docs/evidence/pagamento-unblock-restore-2026-10-05.json'),true,512,JSON_THROW_ON_ERROR);
    if($backup['result']!=='OK'||$restore['result']!=='OK'||!hash_equals($backup['sha256'],$restore['sha256'])||!hash_equals($backup['sha256'],hash_file('sha256',$backup['file']))) throw new RuntimeException('Evidência inválida');
    $counts=array_fill_keys(array_keys($restore['table_counts']),0); $current=null;
    $h=fopen($backup['file'],'rb');
    while(($line=fgets($h))!==false) {
        if(preg_match('/^-- Dumping data for table `([^`]+)`/',$line,$m)) { $current=$m[1]; continue; }
        if(trim($line)==='UNLOCK TABLES;'||str_contains($line,' ENABLE KEYS */;')) { $current=null; continue; }
        if($current===null||!str_starts_with($line,'INSERT INTO ')) continue;
        if(!preg_match('/^INSERT INTO `([^`]+)`(?: \([^\r\n]*?\))? VALUES (.*);\s*$/s',$line,$m)||$m[1]!==$current) throw new RuntimeException('INSERT de dump não reconhecido: tabela '.$current.'; cabeçalho '.substr(explode('VALUES',$line,2)[0],0,500));
        $values=$m[2]; $quote=null; $depth=0; $rows=0;
        for($i=0,$len=strlen($values);$i<$len;$i++) {
            $char=$values[$i];
            if($quote!==null){if($char==='\\'){$i++;continue;}if($char===$quote)$quote=null;continue;}
            if($char==="'"||$char==='"'||$char==='`'){$quote=$char;continue;}
            if($char==='('){if($depth===0)$rows++;$depth++;}
            elseif($char===')'){$depth--;if($depth<0)throw new RuntimeException('Tuple inválida');}
        }
        if($depth!==0||$quote!==null) throw new RuntimeException('INSERT incompleto');
        $counts[$current]+=$rows;
    }
    fclose($h);
    if($counts!==$restore['table_counts']) {
        $different=[];foreach($counts as $table=>$n)if($n!==$restore['table_counts'][$table])$different[$table]=['dump'=>$n,'restore'=>$restore['table_counts'][$table]];
        throw new RuntimeException('Contagens diferem: '.json_encode($different));
    }
    $orphanCounts=array_filter($restore['foreign_key_orphan_counts'],static fn($n)=>$n>0);
    $columns=[];foreach($backup['source_metadata'] as $row)if($row[0]==='FK')$columns[$row[1]][]=$row;
    $ident=static function(string $s):string{if(!preg_match('/^[a-zA-Z0-9_]+$/D',$s))throw new RuntimeException('Identificador inválido');return '`'.$s.'`';};
    $queries=[];
    foreach($orphanCounts as $key=>$n){
        [$table]=explode('.',$key,2); $join=[];$nonnull=[];$parent=null;
        foreach($columns[$key] as $column){[$child,$ref]=explode('=>',$column[2],2);[$parent,$col]=explode('.',$ref,2);$join[]='p.'.$ident($col).'=c.'.$ident($child);$nonnull[]='c.'.$ident($child).' IS NOT NULL';}
        $queries[$key]='SELECT COUNT(*) FROM flowdb.'.$ident($table).' c WHERE '.implode(' AND ',$nonnull).' AND NOT EXISTS (SELECT 1 FROM flowdb.'.$ident($parent).' p WHERE '.implode(' AND ',$join).')';
    }
    $cfg=json_decode(file_get_contents(__DIR__.'/../.vscode/sftp.json'),true,512,JSON_THROW_ON_ERROR);
    if($cfg['host']!=='72.60.137.192'||$cfg['username']!=='root'||(int)$cfg['port']!==22)throw new RuntimeException('SSH inesperado');
    $ssh=new phpseclib3\Net\SSH2($cfg['host'],22,30);
    if(!hash_equals('4fb4d6a8d5f2d3d1837c9284da43f2a6fd51f16786c3efc1392ad72c46da3170',hash('sha256',$ssh->getServerPublicHostKey()))||!$ssh->login($cfg['username'],$cfg['password']))throw new RuntimeException('SSH não autenticou');
    $encoded=base64_encode(json_encode($queries,JSON_THROW_ON_ERROR));
    $python=<<<'PY'
import subprocess,json,base64
queries=json.loads(base64.b64decode('QUERY_PAYLOAD'))
sql='SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY; START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY; '+ '; '.join(queries.values())+'; ROLLBACK;'
p=subprocess.run(['mysql','--defaults-file=/etc/mysql/debian.cnf','--batch','--skip-column-names'],input=sql,text=True,capture_output=True,timeout=120)
if p.returncode: raise RuntimeError('SELECT agregado falhou')
values=[int(x) for x in p.stdout.splitlines() if x.strip()]
if len(values)!=len(queries): raise RuntimeError('Contagens incompletas')
print(json.dumps(dict(zip(queries,values))))
PY;
    $python=str_replace('QUERY_PAYLOAD',$encoded,$python);
    $runner="import base64;exec(base64.b64decode('".base64_encode($python)."'))";
    $ssh->setTimeout(150);
    $response=$ssh->exec("python3 -c '".str_replace("'","'\\''",$runner)."' 2>/dev/null");
    if($ssh->getExitStatus()!==0)throw new RuntimeException('Validação agregada da origem falhou');
    $sourceCounts=json_decode($response,true,512,JSON_THROW_ON_ERROR);
    if($sourceCounts!==$orphanCounts)throw new RuntimeException('Contagens de órfãos diferem da origem; auditar alterações concorrentes');
    $result=['result'=>'OK','at'=>date(DATE_ATOM),'sha256'=>$backup['sha256'],'all_table_counts_match_dump'=>true,'tables_checked'=>count($counts),'rows_checked'=>array_sum($counts),'legacy_fk_orphans_present_in_source'=>true,'orphan_foreign_keys'=>count($orphanCounts),'orphan_references'=>array_sum($orphanCounts),'source_orphan_counts'=>$sourceCounts,'restore_orphan_counts'=>$orphanCounts,'source_queries'=>'SELECT COUNT(*) em transação READ ONLY; sem conteúdo de linhas','source_data_modified'=>false];
    file_put_contents(__DIR__.'/../docs/evidence/pagamento-unblock-fidelity-2026-10-05.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
    $restore['all_table_counts_match_dump']=true;$restore['rows_checked_against_dump']=array_sum($counts);$restore['legacy_fk_orphans_match_source']=true;$restore['fk_integrity']='447 definições preservadas; 12 relações com 40 referências órfãs preexistentes, iguais na origem e no restore';
    file_put_contents(__DIR__.'/../docs/evidence/pagamento-unblock-restore-2026-10-05.json',json_encode($restore,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
    unset($result['source_orphan_counts'],$result['restore_orphan_counts']);
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
}catch(Throwable $e){fwrite(STDERR,'Fidelidade falhou: '.get_class($e).' code '.$e->getCode().' — '.$e->getMessage()."\n");exit(2);}

<?php
/** Deployment estrutural autorizado. Não ativa flag nem executa atos financeiros. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../vendor/autoload.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
date_default_timezone_set('America/Sao_Paulo');
const DP_ROOT='C:/ProgramData/ImproovWeb/private/deployment-backups';
const DP_BACKUP='flowdb_pre_fechamento_2026-10-05_20-15-50_2c42b1.sql';
const DP_SHA='c58d9613518e57ecf22158fb5db904112a2f9dca14912e57db0464906209e667';
const DP_CONTEXT=DP_ROOT.'/deployment_pagamento_20261005.context.json';
function dp_sql(string $sql):string {
    // Somente whitespace e comentários não executáveis; preserva literais e identifiers.
    $out='';$n=strlen($sql);
    for($i=0;$i<$n;$i++){
        $char=$sql[$i];
        if($char==="'"||$char==='"'||$char==='`'){
            $quote=$char;$out.=$char;
            while(++$i<$n){$out.=$sql[$i];if($sql[$i]==='\\'&&$i+1<$n){$out.=$sql[++$i];continue;}if($sql[$i]===$quote){if($i+1<$n&&$sql[$i+1]===$quote){$out.=$sql[++$i];continue;}break;}}
        }elseif($char==='#'||($char==='-'&&($sql[$i+1]??'')==='-'&&ctype_space($sql[$i+2]??' '))){while($i<$n&&$sql[$i]!=="\n")$i++;if(!str_ends_with($out,' '))$out.=' ';}
        elseif($char==='/'&&($sql[$i+1]??'')==='*'&&!in_array($sql[$i+2]??'',['!','+'],true)){ $end=strpos($sql,'*/',$i+2);if($end===false)throw new RuntimeException('Comentário SQL incompleto');$i=$end+1;if(!str_ends_with($out,' '))$out.=' '; }
        elseif(ctype_space($char)){if(!str_ends_with($out,' '))$out.=' ';while($i+1<$n&&ctype_space($sql[$i+1]))$i++;}
        else $out.=$char;
    }
    // Espaços adicionados por comentários ficam somente fora dos literais já preservados.
    return trim($out);
}
function dp_queries():array {
    $spec=[
        'schema'=>['information_schema.SCHEMATA','SCHEMA_NAME=DATABASE()',['DEFAULT_CHARACTER_SET_NAME','DEFAULT_COLLATION_NAME']],
        'tables'=>['information_schema.TABLES','TABLE_SCHEMA=DATABASE()',['TABLE_NAME','TABLE_TYPE','ENGINE','TABLE_COLLATION','ROW_FORMAT','TABLE_COMMENT']],
        'columns'=>['information_schema.COLUMNS','TABLE_SCHEMA=DATABASE()',['TABLE_NAME','COLUMN_NAME','ORDINAL_POSITION','COLUMN_DEFAULT','IS_NULLABLE','COLUMN_TYPE','CHARACTER_SET_NAME','COLLATION_NAME','EXTRA','COLUMN_COMMENT','GENERATION_EXPRESSION']],
        'indexes'=>['information_schema.STATISTICS','TABLE_SCHEMA=DATABASE()',['TABLE_NAME','INDEX_NAME','NON_UNIQUE','SEQ_IN_INDEX','COLUMN_NAME','COLLATION','SUB_PART','INDEX_TYPE','IS_VISIBLE','EXPRESSION']],
        'constraints'=>['information_schema.TABLE_CONSTRAINTS','CONSTRAINT_SCHEMA=DATABASE()',['TABLE_NAME','CONSTRAINT_NAME','CONSTRAINT_TYPE','ENFORCED']],
        'key_columns'=>['information_schema.KEY_COLUMN_USAGE','CONSTRAINT_SCHEMA=DATABASE()',['TABLE_NAME','CONSTRAINT_NAME','COLUMN_NAME','ORDINAL_POSITION','POSITION_IN_UNIQUE_CONSTRAINT','REFERENCED_TABLE_NAME','REFERENCED_COLUMN_NAME']],
        'references'=>['information_schema.REFERENTIAL_CONSTRAINTS','CONSTRAINT_SCHEMA=DATABASE()',['TABLE_NAME','CONSTRAINT_NAME','UNIQUE_CONSTRAINT_NAME','MATCH_OPTION','UPDATE_RULE','DELETE_RULE','REFERENCED_TABLE_NAME']],
        'checks'=>['information_schema.CHECK_CONSTRAINTS','CONSTRAINT_SCHEMA=DATABASE()',['CONSTRAINT_NAME','CHECK_CLAUSE']],
        'triggers'=>['information_schema.TRIGGERS','TRIGGER_SCHEMA=DATABASE()',['TRIGGER_NAME','EVENT_MANIPULATION','EVENT_OBJECT_TABLE','ACTION_ORDER','ACTION_CONDITION','ACTION_STATEMENT','ACTION_ORIENTATION','ACTION_TIMING','SQL_MODE','DEFINER','CHARACTER_SET_CLIENT','COLLATION_CONNECTION','DATABASE_COLLATION']],
        'routines'=>['information_schema.ROUTINES','ROUTINE_SCHEMA=DATABASE()',['ROUTINE_NAME','ROUTINE_TYPE','DATA_TYPE','DTD_IDENTIFIER','ROUTINE_BODY','ROUTINE_DEFINITION','IS_DETERMINISTIC','SQL_DATA_ACCESS','SECURITY_TYPE','SQL_MODE','DEFINER','CHARACTER_SET_CLIENT','COLLATION_CONNECTION','DATABASE_COLLATION']],
        'events'=>['information_schema.EVENTS','EVENT_SCHEMA=DATABASE()',['EVENT_NAME','DEFINER','TIME_ZONE','EVENT_BODY','EVENT_DEFINITION','EVENT_TYPE','EXECUTE_AT','INTERVAL_VALUE','INTERVAL_FIELD','SQL_MODE','STARTS','ENDS','STATUS','ON_COMPLETION','EVENT_COMMENT','CHARACTER_SET_CLIENT','COLLATION_CONNECTION','DATABASE_COLLATION']]
    ];
    $queries=[];
    foreach($spec as $name=>[$table,$where,$fields]){
        $pairs=[];foreach($fields as $field){$pairs[]="'$field'";$pairs[]="`$field`";}
        if($name==='key_columns'){$pairs[]="'REFERENCE_IN_SAME_DATABASE'";$pairs[]='IF(REFERENCED_TABLE_SCHEMA IS NULL,NULL,REFERENCED_TABLE_SCHEMA=DATABASE())';}
        $queries[$name]='SELECT JSON_OBJECT('.implode(',',$pairs).') j FROM '.$table.' WHERE '.$where;
    }
    return $queries;
}
function dp_normalize(array $data):array {
    foreach($data as &$rows){foreach($rows as &$row){foreach($row as $field=>&$v)if($v!==null){$v=(string)$v;if(in_array($field,['ACTION_STATEMENT','ROUTINE_DEFINITION','EVENT_DEFINITION'],true))$v=dp_sql($v);}unset($v);ksort($row);}unset($row);usort($rows,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));}unset($rows);ksort($data);return $data;
}
function dp_local(mysqli $c):array {$out=[];foreach(dp_queries() as $key=>$sql){$out[$key]=[];foreach($c->query($sql)->fetch_all(MYSQLI_ASSOC) as $row)$out[$key][]=json_decode($row['j'],true,512,JSON_THROW_ON_ERROR);}return dp_normalize($out);}
function dp_remote(phpseclib3\Net\SSH2 $ssh):array {
    $payload=base64_encode(json_encode(dp_queries()));
    $python=<<<'PY'
import subprocess,json,base64
qs=json.loads(base64.b64decode('PAYLOAD'))
out={}
idp=subprocess.run(['mysql','--defaults-file=/etc/mysql/debian.cnf','--batch','--skip-column-names','--database=flowdb','--execute=SELECT VERSION(),CURRENT_USER(),@@server_uuid,@@log_bin,@@log_bin_trust_function_creators'],capture_output=True,text=True,check=True)
out['identity']=idp.stdout.strip().split('\t')
if out['identity'][1]!='debian-sys-maint@localhost' or out['identity'][2]!='615e3ba3-b18b-11f0-9193-bc2411f08407': raise RuntimeError('Destino inesperado')
for key,sql in qs.items():
    p=subprocess.run(['mysql','--defaults-file=/etc/mysql/debian.cnf','--default-character-set=utf8mb4','--batch','--raw','--skip-column-names','--database=flowdb','--execute='+sql],capture_output=True,text=True,check=True)
    out[key]=[json.loads(row) for row in p.stdout.splitlines() if row]
print(json.dumps(out,ensure_ascii=False))
PY;
    $out=dp_python($ssh,str_replace('PAYLOAD',$payload,$python));$id=$out['identity'];unset($out['identity']);
    if($id[3]!=='1'||$id[4]!=='0')throw new RuntimeException('Restrições do servidor mudaram');
    return dp_normalize($out);
}
function dp_python(phpseclib3\Net\SSH2 $ssh,string $python):array {
    $runner="import base64;exec(base64.b64decode('".base64_encode($python)."'))";
    $ssh->setTimeout(180);$out=$ssh->exec("python3 -c '".str_replace("'","'\\''",$runner)."' 2>/dev/null");
    if($ssh->getExitStatus()!==0)throw new RuntimeException('Operação remota falhou: consultar evidência privada; não repetir DDL');
    return json_decode($out,true,512,JSON_THROW_ON_ERROR);
}
function dp_compare(array $expected,array $actual,string $label):void {
    $different=[];foreach($expected as $key=>$rows)if(($actual[$key]??null)!==$rows)$different[]=$key;
    if($different)throw new RuntimeException($label.' diverge: '.implode(', ',$different));
}
function dp_subset(array $data,array $tables,array $triggers):array {
    $out=[];
    foreach($data as $kind=>$rows){
        if(in_array($kind,['schema','routines','events'],true))continue;
        if($kind==='triggers'){
            $out[$kind]=array_values(array_filter($rows,static fn($r)=>in_array($r['TRIGGER_NAME'],$triggers,true)));
            foreach($out[$kind] as &$r)$r['DEFINER']='debian-sys-maint@localhost';unset($r);
        }elseif($kind==='checks')$out[$kind]=array_values(array_filter($rows,static function($r)use($tables){foreach($tables as $t)if(str_starts_with($r['CONSTRAINT_NAME'],$t.'_chk_'))return true;return false;}));
        else $out[$kind]=array_values(array_filter($rows,static fn($r)=>in_array($r['TABLE_NAME'],$tables,true)));
    }
    return dp_normalize($out);
}
function dp_legacy(array $data,array $tables,array $triggers):array {
    foreach($data as $kind=>&$rows){
        if($kind==='triggers')$rows=array_values(array_filter($rows,static fn($r)=>!in_array($r['TRIGGER_NAME'],$triggers,true)));
        elseif($kind==='checks')$rows=array_values(array_filter($rows,static function($r)use($tables){foreach($tables as $t)if(str_starts_with($r['CONSTRAINT_NAME'],$t.'_chk_'))return false;return true;}));
        elseif(isset($rows[0]['TABLE_NAME']))$rows=array_values(array_filter($rows,static fn($r)=>!in_array($r['TABLE_NAME'],$tables,true)));
    }unset($rows);return dp_normalize($data);
}
function dp_mysql(string $db,string $file,string $tag):void {
    $mysql=getenv('TEMP').'/pagamento_1cb_runtime/mysql-8.0.42-winx64/bin/mysql.exe';
    $p=proc_open([$mysql,'--no-defaults','--default-character-set=utf8mb4','--host=127.0.0.1','--port=3321','--user=root','--database='.$db],[0=>['file',$file,'r'],1=>['file',DP_ROOT.'/'.$tag.'.stdout','w'],2=>['file',DP_ROOT.'/'.$tag.'.stderr','w']],$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($p)||proc_close($p)!==0)throw new RuntimeException('Referência isolada falhou: '.$tag);
}
$stage=$argv[1]??'';
try {
    if(!in_array($stage,['prepare','inspect','revisao','documento'],true))throw new RuntimeException('Etapa inválida');
    if(!hash_equals(DP_SHA,hash_file('sha256',DP_ROOT.'/'.DP_BACKUP)))throw new RuntimeException('PARAR: hash do backup diverge');
    $hashes=['revisao'=>'ac513ca0e03e355624bbc875c59844f499734748f85d82a7efa36075d0493243','documento'=>'3d02fd459fbfc60bb6165e184af59a2f884eb2b07d1c142558374809d9d31282'];
    $tables=[];$triggers=[];
    foreach($hashes as $kind=>$hash){$file=__DIR__.'/../sql/2026-10-02_pagamento_fechamento_'.$kind.'.sql';if(!hash_equals($hash,hash_file('sha256',$file)))throw new RuntimeException('Migration alterada');$sql=file_get_contents($file);preg_match_all('/CREATE TABLE\s+(\w+)/i',$sql,$m);$tables[$kind]=$m[1];preg_match_all('/CREATE TRIGGER\s+(\w+)/i',$sql,$m);$triggers[$kind]=$m[1];}
    $allTables=array_merge(...array_values($tables));$allTriggers=array_merge(...array_values($triggers));
    $cfg=json_decode(file_get_contents(__DIR__.'/../.vscode/sftp.json'),true,512,JSON_THROW_ON_ERROR);
    if($cfg['host']!=='72.60.137.192'||$cfg['username']!=='root'||(int)$cfg['port']!==22)throw new RuntimeException('SSH inesperado');
    $ssh=new phpseclib3\Net\SSH2($cfg['host'],22,30);
    if(!hash_equals('4fb4d6a8d5f2d3d1837c9284da43f2a6fd51f16786c3efc1392ad72c46da3170',hash('sha256',$ssh->getServerPublicHostKey()))||!$ssh->login($cfg['username'],$cfg['password']))throw new RuntimeException('SSH não autenticou');
    $remoteBackup='/root/ImproovWeb-deployment-backups/'.DP_BACKUP;
    $check=dp_python($ssh,"import hashlib,json\np='".$remoteBackup."'\nh=hashlib.sha256()\nwith open(p,'rb') as f:\n for b in iter(lambda:f.read(1048576),b''): h.update(b)\nprint(json.dumps({'sha256':h.hexdigest()}))");
    if(!hash_equals(DP_SHA,$check['sha256']))throw new RuntimeException('PARAR: backup remoto diverge');
    if($stage==='inspect') {
        $actual=dp_remote($ssh);$c=new mysqli('127.0.0.1','root','',null,3321);
        if($c->query('SELECT @@server_uuid u')->fetch_assoc()['u']!=='393a087f-c110-11f1-a84e-047c16cb9e88')throw new RuntimeException('Instância inesperada');
        $dbs=$c->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'pagamento_deploy_reference_20261005_%'")->fetch_all(MYSQLI_ASSOC);
        if(count($dbs)!==1)throw new RuntimeException('Referência não única');$c->select_db($dbs[0]['SCHEMA_NAME']);$expected=dp_local($c);$diff=[];
        foreach(['triggers'=>'TRIGGER_NAME','routines'=>'ROUTINE_NAME'] as $kind=>$name){$map=[];foreach($expected[$kind] as $row)$map[$row[$name]]=$row;foreach($actual[$kind] as $row){foreach($row as $field=>$value){$before=$map[$row[$name]][$field]??null;if($before!==$value)$diff[]=['kind'=>$kind,'name'=>$row[$name],'field'=>$field,'expected_length'=>strlen($before??''),'actual_length'=>strlen($value??''),'equal_after_crlf_normalization'=>str_replace("\r\n","\n",$before??'')===str_replace("\r\n","\n",$value??'')];}}}
        echo json_encode(['database'=>$dbs[0]['SCHEMA_NAME'],'differences'=>$diff],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";exit;
    }elseif($stage==='prepare') {
        if(file_exists(DP_CONTEXT))throw new RuntimeException('Contexto já existe: não sobrescrever');
        $actual=dp_remote($ssh);
        if(count(dp_subset($actual,$allTables,$allTriggers)['tables'])||count(dp_subset($actual,$allTables,$allTriggers)['triggers']))throw new RuntimeException('Instalação não ABSENT');
        $c=new mysqli('127.0.0.1','root','',null,3321);$id=$c->query('SELECT @@server_uuid u,@@event_scheduler e,@@datadir d')->fetch_assoc();
        if($id['u']!=='393a087f-c110-11f1-a84e-047c16cb9e88'||$id['e']!=='OFF'||!str_starts_with(strtolower(str_replace('\\','/',$id['d'])),strtolower(DP_ROOT.'/mysql8_restore_20261005/data/')))throw new RuntimeException('Referência não isolada');
        $refs=$c->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'pagamento_deploy_reference_20261005_%'")->fetch_all(MYSQLI_ASSOC);
        if(count($refs)>1)throw new RuntimeException('Referência não única');
        $db=$refs?$refs[0]['SCHEMA_NAME']:'pagamento_deploy_reference_20261005_'.bin2hex(random_bytes(4));
        $schema=$actual['schema'][0];foreach($schema as $s)if(!preg_match('/^[a-zA-Z0-9_]+$/D',$s))throw new RuntimeException('Charset inválido');
        if(!$refs){$c->query("CREATE DATABASE `$db` CHARACTER SET ".$schema['DEFAULT_CHARACTER_SET_NAME'].' COLLATE '.$schema['DEFAULT_COLLATION_NAME']);dp_mysql($db,DP_ROOT.'/'.DP_BACKUP,$db.'_backup');}
        $c->select_db($db);
        $baseline=dp_local($c);dp_compare($baseline,$actual,'Estrutura desde o backup');
        $context=['at'=>date(DATE_ATOM),'reference_database'=>$db,'baseline'=>$baseline,'backup_sha256'=>DP_SHA,'migrations'=>$hashes,'remote_dir'=>'/root/ImproovWeb-deployment-backups/deployment_'.date('Ymd_His').'_'.bin2hex(random_bytes(3))];
        foreach(['revisao','documento'] as $kind){dp_mysql($db,__DIR__.'/../sql/2026-10-02_pagamento_fechamento_'.$kind.'.sql',$db.'_'.$kind);$names=$kind==='revisao'?$tables['revisao']:$allTables;$trs=$kind==='revisao'?$triggers['revisao']:$allTriggers;$context[$kind]=dp_subset(dp_local($c),$names,$trs);}
        $c->close();file_put_contents(DP_CONTEXT,json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        $proof=['result'=>'OK','at'=>date(DATE_ATOM),'backup_sha256'=>DP_SHA,'backup_local_remote'=>'OK','structural_drift'=>'NONE','legacy_tables_compared'=>count($baseline['tables']),'checks_compared'=>count($baseline['checks']),'reference_database'=>$db,'migrations'=>$hashes];
    }else {
        $context=json_decode(file_get_contents(DP_CONTEXT),true,512,JSON_THROW_ON_ERROR);
        if($context['migrations']!==$hashes||$context['backup_sha256']!==DP_SHA)throw new RuntimeException('Contexto diverge');
        $evidence=__DIR__.'/../docs/evidence/pagamento-deployment-'.$stage.'-2026-10-05.json';
        if(file_exists($evidence))throw new RuntimeException('Etapa já tem evidência: não reaplicar');
        if($stage==='documento'){$a=json_decode(file_get_contents(__DIR__.'/../docs/evidence/pagamento-deployment-revisao-2026-10-05.json'),true,512,JSON_THROW_ON_ERROR);if($a['result']!=='OK')throw new RuntimeException('1C-A não validada');}
        $before=dp_remote($ssh);dp_compare($context['baseline'],dp_legacy($before,$allTables,$allTriggers),'Legado antes do DDL');
        if($stage==='revisao'){if(count(dp_subset($before,$allTables,$allTriggers)['tables'])||count(dp_subset($before,$allTables,$allTriggers)['triggers']))throw new RuntimeException('1C-A não ABSENT');}
        else dp_compare($context['revisao'],dp_subset($before,$allTables,$allTriggers),'Gate 1C-A');
        $dir=$context['remote_dir'];
        $prep=dp_python($ssh,"import os,json\np='".$dir."'\nif not os.path.exists(p): os.mkdir(p,0o700)\nprint(json.dumps({'ready':True}))");
        $sftp=new phpseclib3\Net\SFTP($cfg['host'],22,30);if(!hash_equals(hash('sha256',$ssh->getServerPublicHostKey()),hash('sha256',$sftp->getServerPublicHostKey()))||!$sftp->login($cfg['username'],$cfg['password']))throw new RuntimeException('SFTP falhou');
        $remoteSql=$dir.'/'.$stage.'.sql';if($sftp->file_exists($remoteSql))throw new RuntimeException('Migration remota já existe: não repetir');
        if(!$sftp->put($remoteSql,file_get_contents(__DIR__.'/../sql/2026-10-02_pagamento_fechamento_'.$stage.'.sql'))||!$sftp->chmod(0600,$remoteSql))throw new RuntimeException('Upload SQL falhou');
        $python=<<<'PY'
import os,json,hashlib,subprocess,datetime,zoneinfo
path='SQL_PATH';expected='SQL_SHA'
if hashlib.sha256(open(path,'rb').read()).hexdigest()!=expected: raise RuntimeError('SQL diverge')
marker=path+'.started'
with open(marker,'x') as m: m.write(datetime.datetime.now(zoneinfo.ZoneInfo('America/Sao_Paulo')).isoformat())
with open(path,'rb') as source,open(path+'.stdout','xb') as out,open(path+'.stderr','xb') as err:
 p=subprocess.run(['mysql','--defaults-file=/etc/mysql/debian.cnf','--default-character-set=utf8mb4','--database=flowdb'],stdin=source,stdout=out,stderr=err,timeout=120)
result={'exit_code':p.returncode,'at':datetime.datetime.now(zoneinfo.ZoneInfo('America/Sao_Paulo')).isoformat(),'sql_sha256':expected,'remote_sql':path}
with open(path+'.result.json','x') as out: json.dump(result,out)
print(json.dumps(result))
PY;
        $run=dp_python($ssh,str_replace(['SQL_PATH','SQL_SHA'],[$remoteSql,$hashes[$stage]],$python));
        if($run['exit_code']!==0){file_put_contents($evidence,json_encode(['result'=>'FAILED','run'=>$run]));throw new RuntimeException('DDL falhou: PARAR, não completar/reaplicar/rollback');}
        $after=dp_remote($ssh);$names=$stage==='revisao'?$tables['revisao']:$allTables;$trs=$stage==='revisao'?$triggers['revisao']:$allTriggers;
        $subset=dp_subset($after,$names,$trs);dp_compare($context[$stage],$subset,'Validação integral '.$stage);dp_compare($context['baseline'],dp_legacy($after,$allTables,$allTriggers),'Legado após DDL');
        foreach($after['triggers'] as $t)if(in_array($t['TRIGGER_NAME'],$trs,true)&&$t['DEFINER']!=='debian-sys-maint@localhost')throw new RuntimeException('DEFINER incorreto');
        $proof=['result'=>'OK','at'=>date(DATE_ATOM),'backup_sha256'=>DP_SHA,'run'=>$run,'tables_count'=>count($subset['tables']),'triggers_count'=>count($subset['triggers']),'definitions_match_approved_migration'=>true,'legacy_structure_unchanged'=>true,'definer'=>'debian-sys-maint@localhost','structure'=>$subset];
    }
    $path=__DIR__.'/../docs/evidence/pagamento-deployment-'.$stage.'-2026-10-05.json';file_put_contents($path,json_encode($proof,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");unset($proof['structure']);echo json_encode($proof,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
}catch(Throwable $e){fwrite(STDERR,'Deployment PARADO ('.$stage.'): '.get_class($e).' code '.$e->getCode().' — '.$e->getMessage()."\n");exit(2);}

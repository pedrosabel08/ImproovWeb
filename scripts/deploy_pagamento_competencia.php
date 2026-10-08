<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/secure_env.php'; improov_load_env_once();
require_once __DIR__.'/../config/pagamento_fechamento.php';
$c=pagamento_fechamento_connection();
$identity=$c->query('SELECT DATABASE() db,@@server_uuid uuid')->fetch_assoc();
if ($identity['db']!=='flowdb' || $identity['uuid']!=='615e3ba3-b18b-11f0-9193-bc2411f08407') throw new RuntimeException('Destino de implantação inesperado.');
$private='C:/ProgramData/ImproovWeb/private/deployment-backups';
if (!is_dir($private) || !is_writable($private)) throw new RuntimeException('Diretório privado de backup ausente.');
$backup=['database'=>$identity,'created_at'=>date(DATE_ATOM),'tables'=>[],'triggers'=>$c->query("SHOW TRIGGERS WHERE `Trigger`='pc_snapshot'")->fetch_all(MYSQLI_ASSOC)];
foreach(array_merge(['pagamento_fechamento_decisao','pagamento_fechamento_operacao'],in_array('--retiradas',$argv,true)?['pagamento_competencia']:[]) as $t) $backup['tables'][$t]=['schema'=>$c->query('SHOW CREATE TABLE '.$t)->fetch_assoc()['Create Table'],'rows'=>$c->query('SELECT * FROM '.$t)->fetch_all(MYSQLI_ASSOC)];
$path=$private.'/competencia_pre_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.json';
if (file_put_contents($path,json_encode($backup,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Backup não gravado.');
echo 'Backup privado das tabelas e proteções afetadas: '.basename($path).PHP_EOL;
$file=__DIR__.'/../sql/'.(in_array('--retiradas',$argv,true)?'2026-10-08_pagamento_retiradas.sql':'2026-10-07_pagamento_competencia.sql');
$ssh=null;
if(in_array('--dba',$argv,true)) {
 require_once __DIR__.'/../vendor/autoload.php';
 $cfg=json_decode(file_get_contents(__DIR__.'/../.vscode/sftp.json'),true,512,JSON_THROW_ON_ERROR);
 if($cfg['host']!=='72.60.137.192'||$cfg['username']!=='root'||(int)$cfg['port']!==22) throw new RuntimeException('Destino administrativo inesperado.');
 $ssh=new phpseclib3\Net\SSH2($cfg['host'],22,30);
 if(!hash_equals('4fb4d6a8d5f2d3d1837c9284da43f2a6fd51f16786c3efc1392ad72c46da3170',hash('sha256',$ssh->getServerPublicHostKey())) || !$ssh->login($cfg['username'],$cfg['password'])) throw new RuntimeException('SSH administrativo não autenticado.');
}
$delimiter=';'; $buffer='';
foreach(explode("\n",file_get_contents($file)) as $line) {
    if (preg_match('/^\s*--/',$line)) continue;
    if (preg_match('/^DELIMITER (\S+)$/',trim($line),$m)) { $delimiter=$m[1]; continue; }
    $buffer.=$line."\n";
    if (str_ends_with(rtrim($buffer),$delimiter)) {
        $sql=substr(rtrim($buffer),0,-strlen($delimiter)); $buffer='';
        if (preg_match('/CREATE TRIGGER (\w+)/',$sql,$m)) {
            $stmt=$c->prepare('SELECT 1 FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=?');
            $stmt->bind_param('s',$m[1]); $stmt->execute(); $has=$stmt->get_result()->num_rows; $stmt->close();
            if ($has) continue;
        }
        if (trim($sql)!=='') {
            if (!in_array('--retiradas',$argv,true) && preg_match('/ALTER TABLE (pagamento_fechamento_(?:decisao|operacao)) MODIFY tipo/',$sql,$enumMatch)) {
                $column=$c->query("SHOW COLUMNS FROM ".$enumMatch[1]." LIKE 'tipo'")->fetch_assoc();
                if (str_contains($column['Type'],"'SERVICOS'")) continue;
            }
            if (!$ssh) $c->query($sql);
            else {
                $python="import subprocess,base64; p=subprocess.run(['mysql','--defaults-file=/etc/mysql/debian.cnf','--default-character-set=utf8mb4','--database=flowdb'],input=base64.b64decode('".base64_encode("DELIMITER $$\n".$sql."$$\nDELIMITER ;\n")."'),capture_output=True); print('OK' if p.returncode==0 else p.stderr.decode()); exit(p.returncode)";
                $result=$ssh->exec("python3 -c '".str_replace("'","'\\''",$python)."'");
                if($ssh->getExitStatus()!==0 || trim($result)!=='OK') throw new RuntimeException('DDL administrativa falhou: '.trim($result));
            }
        }
    }
}
if (trim($buffer)!=='') throw new RuntimeException('Migration incompleta.');
if (in_array('--retiradas',$argv,true)) {
 require_once __DIR__.'/../Pagamento/services/FechamentoCompetenciaService.php';
 $refs=$c->query("SELECT competencia FROM pagamento_competencia WHERE estado='EM_ANDAMENTO'")->fetch_all(MYSQLI_ASSOC);
 foreach($refs as $row) { $r=(new FechamentoCompetenciaService($c,1))->atualizarPrevisao($row['competencia']); echo json_encode(['competencia'=>$row['competencia'],'previsto_em'=>$r['previsto_em']],JSON_UNESCAPED_UNICODE).PHP_EOL; }
}
echo "Estrutura de competências aplicada; sem criação de ciclos ou pagamentos.\n";

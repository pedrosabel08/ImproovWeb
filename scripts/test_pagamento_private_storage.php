<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__.'/../config/pagamento_fechamento.php';
$root='C:/ProgramData/ImproovWeb/private/pagamento-fechamento';
if (($argv[1]??'')==='--lock-probe') {
    $path=$argv[2]??'';
    if (!preg_match('~^C:/ProgramData/ImproovWeb/private/pagamento-fechamento/locks/unblock_[a-f0-9]{24}\.lock$~D',$path)) exit(2);
    $h=fopen($path,'r+b'); if (!$h) exit(2);
    $locked=flock($h,LOCK_EX|LOCK_NB); if($locked) flock($h,LOCK_UN); fclose($h);
    echo $locked?'ACQUIRED':'BLOCKED'; exit($locked?2:0);
}
putenv('PAGAMENTO_FECHAMENTO_STORAGE_ROOT='.$root);
$files=[]; $h=null;
try {
    if (pagamento_fechamento_storage() !== realpath($root)) throw new RuntimeException('Raiz inválida');
    $id=bin2hex(random_bytes(12)); $data=random_bytes(64);
    $a=$root.'/staging/unblock_'.$id.'.test'; $b=$root.'/definitivo/unblock_'.$id.'.test'; $l=$root.'/locks/unblock_'.$id.'.lock';
    $files=[$a,$b,$l];
    $h=fopen($a,'x+b'); if (!$h || fwrite($h,$data)!==strlen($data) || !fflush($h) || !fsync($h)) throw new RuntimeException('Escrita/flush falhou');
    fclose($h); $h=null;
    if (!rename($a,$b) || file_get_contents($b)!==$data) throw new RuntimeException('Rename/leitura falhou');
    $h=fopen($l,'x+b'); if (!$h || !flock($h,LOCK_EX|LOCK_NB)) throw new RuntimeException('Lock falhou');
    $p=proc_open([PHP_BINARY,__FILE__,'--lock-probe',$l],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); fclose($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[2]); $exit=proc_close($p);
    if ($exit!==0 || trim($out)!=='BLOCKED') throw new RuntimeException('Exclusão mútua falhou');
    flock($h,LOCK_UN); fclose($h); $h=null;
    foreach ($files as $file) if (is_file($file) && !unlink($file)) throw new RuntimeException('Exclusão falhou');
    echo json_encode(['result'=>'OK','root'=>$root,'create'=>true,'write'=>true,'fflush'=>true,'fsync'=>true,'rename'=>true,'cross_process_lock'=>true,'read'=>true,'delete'=>true,'php'=>PHP_VERSION,'at'=>date(DATE_ATOM)],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
} catch(Throwable $e) { fwrite(STDERR,'Teste storage falhou: '.get_class($e)."\n"); $status=2; }
finally { if (is_resource($h)) fclose($h); foreach($files as $file) if(is_file($file)) unlink($file); }
exit($status??0);

<?php
// Regressao offline do teste pre-merge; preserva o conflito externo no teste original.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$path=__DIR__.'/pagamento_resumo_test.php';
$source=file_get_contents($path);
$resolved=preg_replace('/^<<<<<<< HEAD\R(.*?)^=======\R.*?^>>>>>>> 093e0b0c8aa585f296f434253713285fa571e732\R/ms','$1',$source,-1,$count);
if($count!==1)throw new RuntimeException('Conflito do teste mudou: reauditar');
$p=proc_open(['git','show','d04145bf:tests/pagamento_resumo_test.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,__DIR__.'/..');
fclose($pipes[0]);$historical=stream_get_contents($pipes[1]);fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);
if(proc_close($p)!==0||str_replace("\r\n","\n",$resolved)!==str_replace("\r\n","\n",$historical))throw new RuntimeException('Teste resolvido difere do commit pre-conflito');
if(!str_starts_with($resolved,'<?php'))throw new RuntimeException('PHP inesperado');
echo "Teste original preservado; corpo executado igual a d04145bf, offline.\n";
eval(substr($resolved,5));

<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if(!in_array('--db-readonly',$argv,true)){fwrite(STDERR,"Use --db-readonly.\n");exit(2);}
require_once __DIR__ . '/../Pagamento/services/FechamentoComposicaoService.php';
$checks=0;function comp_db_check(bool $ok,string $m):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($m);}
require __DIR__ . '/../conexao.php';$conn->close();$conn=new FechamentoFinanceiroReadOnlyConnection($servername,$username,$password,$dbname);
try{
 $conn->set_charset('utf8mb4');$base=new FechamentoFinanceiroService(new FechamentoFinanceiroRepository($conn));
 $service=new FechamentoComposicaoService($base,new FechamentoComposicaoRepository());
 foreach([[7,'2026-08'],[4,'2026-08'],[1,'2026-08']] as [$b,$ref]){
  $start=count($conn->audit);$read=$service->calcularComContexto($b,$ref);$r=$read['composicao'];$sql=array_slice($conn->audit,$start);
  comp_db_check($read['contexto']['snapshot_em']===$r['financeiro_servicos']['snapshot_em'],'Snapshot único 1A/1B.');
  comp_db_check(count(array_filter($sql,fn($q)=>str_starts_with($q,'START TRANSACTION')))===1,'Uma transação consistente.');
  comp_db_check(end($conn->audit)==='ROLLBACK','Transação encerrada antes da composição.');
  comp_db_check($r['extras']['estado']==='PENDENTE','Ausência de storage não vira SEM_BONUS.');
  comp_db_check($r['total_final_centavos']===null&&!$r['total_final_determinado'],'Total vivo indeterminado.');
  comp_db_check($r['bloqueado'],'Pendência conservada.');
  if($b===4)comp_db_check($r['fixo']['estado']==='DEFINIDO'&&$r['fixo']['saldo_centavos']===0,'Zero real conhecido.');
  else comp_db_check($r['fixo']['estado_liquidacao']==='LIQUIDACAO_INDETERMINADA'&&$r['fixo']['pago_centavos']===null,'Legado não prova liquidação.');
  comp_db_check($r['acompanhamento_especial']['aplicavel']===($b===1),'Especial só no beneficiário aprovado.');
 }
 $failed=false;try{$service->calcular(2147483647,'2026-08');}catch(InvalidArgumentException $e){$failed=true;}
 comp_db_check($failed,'Colaborador inexistente rejeitado.');comp_db_check(end($conn->audit)==='ROLLBACK','Erro encerra leitura.');
 echo "OK: $checks verificações de integração composição read-only; nenhum DML/DDL/PDF/adendo.\n";
}finally{$conn->close();}

<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../Pagamento/services/FechamentoComposicaoService.php';
require __DIR__.'/../conexao.php';
$conn->close();
$conn=new FechamentoFinanceiroReadOnlyConnection($servername,$username,$password,$dbname);
try {
    $conn->set_charset('utf8mb4');
    $s=new FechamentoComposicaoService(new FechamentoFinanceiroService(new FechamentoFinanceiroRepository($conn)),new FechamentoComposicaoRepository());
    $r=$s->calcular(14,'2026-09');$f=$r['financeiro_servicos'];
    $counts=[];foreach($f['itens_analisados'] as $item)$counts[$item['situacao']]=($counts[$item['situacao']]??0)+1;
    $out=['mode'=>'READ_ONLY_NO_REVISION','revision_id'=>null,'version'=>null,'situacao'=>$r['situacao'],'snapshot_em'=>$r['snapshot_em'],'quantidades'=>$counts,'servicos_devidos'=>count($f['servicos_devidos']),'subtotal_servicos_centavos'=>$f['subtotal_servicos_centavos'],'subtotal_servicos_completo'=>$f['subtotal_servicos_completo'],'fixo'=>array_intersect_key($r['fixo'],array_flip(['estado','configurado_centavos','utilizado_centavos','pago_centavos','saldo_centavos','estado_liquidacao'])),'especial'=>$r['acompanhamento_especial'],'extras'=>array_intersect_key($r['extras'],array_flip(['estado','subtotal_centavos','determinado'])),'pendencias'=>array_map(fn($p)=>array_intersect_key($p,array_flip(['codigo','componente','mensagem'])),$r['pendencias']),'componentes'=>$r['componentes'],'componentes_conhecidos_centavos'=>$r['componentes_conhecidos_centavos'],'total_final_centavos'=>$r['total_final_centavos'],'read_only_transaction'=>in_array('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY',$conn->audit,true),'transaction_end'=>end($conn->audit)];
    file_put_contents('C:/ProgramData/ImproovWeb/private/deployment-backups/canary_adriana_202609/motor_readonly.json',json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){fwrite(STDERR,'Leitura canary falhou: '.get_class($e)."\n");exit(2);}finally{$conn->close();}

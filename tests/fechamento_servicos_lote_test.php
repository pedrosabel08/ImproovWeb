<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/fechamento_plantas_integration_test.php';
$read=$s->obter(6,'2026-10');
$list=$read['revisao']['financeiro_servicos']['itens_analisados'];
$one=array_values(array_filter($list,fn($i)=>(int)$i['identidade']['origem_id']===5001))[0];
$two=array_values(array_filter($list,fn($i)=>(int)$i['identidade']['origem_id']===5002))[0];
$ops=[['estado'=>'APLICAR_TARIFA','alvo'=>'ITEM','identidade'=>$one['identidade'],'categoria_planta'=>'APTO_INDIVIDUAL'],
 ['estado'=>'ALTERAR_VALOR','alvo'=>'ITEM','identidade'=>$two['identidade'],'categoria_planta'=>'APTO_VARIACAO','valor'=>'150','motivo'=>'Exceção sintética lote']];
$body=['colaborador_id'=>6,'competencia'=>'2026-10','expected_version'=>$read['latest_version'],'idempotency_key'=>'lote-integration','tipo'=>'SERVICOS','input'=>['estado'=>'LOTE','operacoes'=>$ops]];
$valid=FechamentoHttp::validar('decidir',$body);
$bad=$valid['input'];$bad['operacoes'][1]['valor']='-1';
$before=(int)$c->query('SELECT COUNT(*) n FROM pagamento_fechamento_revisao')->fetch_assoc()['n'];
plantNo(fn()=>$s->decidir(6,'2026-10',$read['latest_version'],'lote-invalid','SERVICOS',$bad),'Lote inválido rejeitado');
plantOk((int)$c->query('SELECT COUNT(*) n FROM pagamento_fechamento_revisao')->fetch_assoc()['n']===$before,'Lote inválido não publica revisão parcial');
$result=$s->decidir(6,'2026-10',$read['latest_version'],'lote-integration','SERVICOS',$valid['input']);
plantOk($result['latest_version']===$read['latest_version']+1,'Lote cria somente uma revisão');
plantOk($result['revisao']['total_final_centavos']==='84000','Duas alterações calculadas juntas');
plantOk($s->decidir(6,'2026-10',$read['latest_version'],'lote-integration','SERVICOS',$valid['input'])['revisao']['id']===$result['revisao']['id'],'Retry idempotente');
$audit=json_decode($c->query("SELECT depois_json FROM pagamento_fechamento_decisao WHERE tipo='SERVICOS' ORDER BY id DESC LIMIT 1")->fetch_assoc()['depois_json'],true);
plantOk(count($audit['historico_lote'])===2 && $audit['historico_lote'][1]['motivo']==='Exceção sintética lote','Motivos individuais auditados');
$pdf=$s->gerar(6,'2026-10',$result['revisao']['fechamento_id'],$result['revisao']['id'],'lote-preview');
$model=json_decode($c->query('SELECT modelo_json FROM pagamento_fechamento_documento WHERE id='.(int)$pdf['document_id'])->fetch_assoc()['modelo_json'],true);
plantOk(array_sum(array_column($model['servicos'],'valor_centavos'))===84000,'PDF reflete lote completo');
plantNo(fn()=>$s->decidir(6,'2026-10',$read['latest_version'],'lote-stale','SERVICOS',$valid['input']),'Versão antiga bloqueada');
$badBody=$body;$badBody['input']['operacoes'][0]['valor']='1';
plantNo(fn()=>FechamentoHttp::validar('decidir',$badBody),'Lote rejeita valor arbitrário em tarifa padrão');
$nested=$body;$nested['input']['operacoes'][]=['estado'=>'LOTE','operacoes'=>[]];
plantNo(fn()=>FechamentoHttp::validar('decidir',$nested),'Lote aninhado rejeitado');
echo "Lote: atomicidade, validação, auditoria, PDF, concorrência e idempotência passaram.\n";

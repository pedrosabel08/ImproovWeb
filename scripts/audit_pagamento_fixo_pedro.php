<?php
/** Read-only diagnosis of the user-reported Pedro Henrique September review. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/pagamento_fechamento.php';
$c=pagamento_fechamento_connection();
$c->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
try {
    $people=$c->query("SELECT idcolaborador,nome_colaborador,valor_fixo FROM colaborador WHERE nome_colaborador='Pedro Henrique'")->fetch_all(MYSQLI_ASSOC);
    if(count($people)!==1)throw new RuntimeException('Ambiguous collaborator');
    $id=(int)$people[0]['idcolaborador'];
    $stmt=$c->prepare("SELECT r.id,r.numero,r.estado,r.snapshot_json FROM pagamento_fechamento_revisao r JOIN pagamento_fechamento f ON f.id=r.fechamento_id WHERE f.colaborador_id=? AND f.competencia='2026-09' ORDER BY r.numero DESC LIMIT 1");
    $stmt->bind_param('i',$id);$stmt->execute();$r=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$r)throw new RuntimeException('No revision');
    $x=json_decode($r['snapshot_json'],true,512,JSON_THROW_ON_ERROR)['composicao'];
    $fixo=array_intersect_key($x['fixo'],array_flip(['estado','configurado_centavos','utilizado_centavos','pago_centavos','saldo_centavos','estado_liquidacao','origem']));
    $out=['at'=>(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format(DATE_ATOM),'colaborador_id'=>$id,'nome'=>'Pedro Henrique','competencia'=>'2026-09','cadastro_valor_fixo'=>$people[0]['valor_fixo'],'revision_id'=>(int)$r['id'],'version'=>(int)$r['numero'],'estado'=>$r['estado'],'fixo'=>$fixo,'evidencias_liquidacao_count'=>count($x['fixo']['evidencias_liquidacao']),'bonus_estado'=>$x['extras']['estado'],'extras_count'=>count($x['extras']['itens']),'componentes'=>$x['componentes'],'total_final_centavos'=>$x['total_final_centavos'],'pendencias'=>array_column($x['pendencias'],'codigo'),'financial_writes_performed'=>false];
    file_put_contents(__DIR__.'/../docs/evidence/pagamento-pedro-henrique-fixo-diagnostico.json',json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
    echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
}finally{$c->rollback();$c->close();}

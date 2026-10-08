<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/secure_env.php'; improov_load_env_once();
require_once __DIR__.'/../config/pagamento_fechamento.php';
require_once __DIR__.'/../Pagamento/services/FechamentoCompetenciaService.php';
$c=pagamento_fechamento_connection();
$ref=$argv[1]??'2026-09'; pagamento_competencia_nova($ref);
$out=['competencia'=>$ref,'migration'=>FechamentoCompetenciaService::disponivel($c)];
foreach(['pagamentos','pagamento_itens','pagamento_fechamento','pagamento_fechamento_revisao','pagamento_fechamento_documento'] as $t) {
 $out['historico'][$t]=(int)$c->query('SELECT COUNT(*) n FROM '.$t)->fetch_assoc()['n'];
}
$out['ledger_total']=$c->query('SELECT SUM(valor) total FROM pagamento_itens')->fetch_assoc()['total'];
$out['adriana']=$c->query("SELECT p.idpagamento,p.mes_ref,p.status,p.valor_total,COUNT(pi.idpagamento_item) itens,SUM(pi.valor) pago FROM pagamentos p JOIN pagamento_itens pi ON pi.pagamento_id=p.idpagamento WHERE p.colaborador_id=14 AND p.mes_ref='2026-09' GROUP BY p.idpagamento")->fetch_all(MYSQLI_ASSOC);
if($out['migration']) {
 $r=(new FechamentoCompetenciaService($c,1))->resumo($ref);
 $out['ciclo']=array_diff_key($r,array_flip(['colaboradores','pendencias_configuracao']));
 require_once __DIR__.'/../helpers/pendencias_operacionais_helper.php';
 require_once __DIR__.'/../helpers/pagamento_pendencias_helper.php';
 foreach([21,9,43] as $responsavel) {
  $modules=[]; pagamento_pendencias_append($c,$modules,$responsavel,0);
  $out['pendencias_responsaveis'][$responsavel]=array_map(fn($i)=>array_intersect_key($i,array_flip(['title','subtitle','action_url','status'])), $modules['pagamentos']['items']??[]);
 }
 $out['colaboradores']=array_map(fn($p)=>array_intersect_key($p,array_flip(['colaborador_id','nome','status','total_centavos','pago_centavos','pagamento_status','reconciliacao'])),$r['colaboradores']);
}
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;

<?php
if (PHP_SAPI!=='cli') exit(2);
if (!preg_match('/^pagamento_1cb_test_[a-f0-9]{10}$/D',$argv[1]??'')) exit(2);
require_once __DIR__.'/fixtures/pagamento_adendos_documental.php';
require_once __DIR__.'/../Pagamento/services/FechamentoCompetenciaService.php';
$c=documental_test_connection($argv[1]);
try {
 $s=new FechamentoCompetenciaService($c,1);
 $r=($argv[2]??'')==='criar'?$s->criar('2026-10'):$s->pagar('2026-09',3,'concurrent-paid-3','2026-10-07','Concorrência sintética');
 echo json_encode(['ok'=>true,'ciclo'=>$r['ciclo_id'],'pago'=>$r['pago_centavos']]);
} catch(Throwable $e) { echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit(1); }

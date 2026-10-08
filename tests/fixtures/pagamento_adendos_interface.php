<?php
/** Ambiente sintético explicitamente isolado; nunca inclui conexao.php. */
require_once __DIR__.'/pagamento_adendos_documental.php';
function interface_test_seed(): array {
    $c=documental_test_connection(); $db='pagamento_1cb_test_'.bin2hex(random_bytes(5));
    $c->query("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $c->select_db($db);
    documental_test_schema($c);
    $c->query("UPDATE colaborador SET nome_colaborador=CONCAT(nome_colaborador,' — sintético')");
    $f=new FechamentoRevisaoService($c);
    documental_test_limpa($f,3,'UI-A');
    $r=$f->prepararRevisao(2,'2026-09',1,0,'UI-B');
    $f->decidir(2,'2026-09',1,$r['version'],'UI-B-fixo','LIQUIDACAO',['motivo'=>'Apuração sintética','evidencias'=>[['tipo'=>'APURACAO_FIXO_SEM_PAGAMENTO','referencia'=>'UI-B-evidencia','valor_centavos'=>0,'origem_verificavel'=>'Fixture sintética sem pagamentos de fixo']]]);
    documental_test_limpa($f,1,'UI-F');
    $r=$f->prepararRevisao(4,'2026-09',1,0,'UI-D');
    $f->decidir(4,'2026-09',1,$r['version'],'UI-D-bonus','BONUS',['estado'=>'SEM_BONUS','motivo'=>'Fixture sintética']);
    $r=$f->prepararRevisao(5,'2026-09',1,0,'UI-E');
    $f->decidir(5,'2026-09',1,$r['version'],'UI-E-bonus','BONUS',['estado'=>'SEM_BONUS','motivo'=>'Fixture sintética']);
    documental_test_limpa($f,6,'UI-G'); documental_test_limpa($f,8,'UI-J');
    $root=documental_test_root($db); mkdir($root,0700,true); $c->close();
    return ['db'=>$db,'root'=>$root];
}

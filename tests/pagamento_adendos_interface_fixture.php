<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/fixtures/pagamento_adendos_interface.php';
require_once __DIR__.'/../Pagamento/services/FechamentoDocumentoService.php';
if (($argv[1]??'')==='--seed') { echo json_encode(interface_test_seed(),JSON_UNESCAPED_SLASHES); exit; }
if (($argv[1]??'')==='--cleanup') {
    $fixture=json_decode(file_get_contents($argv[2]??''),true,512,JSON_THROW_ON_ERROR);
    $db=$fixture['db']??''; $c=documental_test_connection($db);
    if(!preg_match('/^pagamento_1cb_test_[a-f0-9]{10}$/D',$db)) throw new RuntimeException('Cleanup não autorizado.');
    documental_test_cleanup_root($db); $c->query("DROP DATABASE `$db`"); $c->close(); echo "Fixture sintética removida.\n"; exit;
}
if(in_array($argv[1]??'',['--delay-prepare','--clear-delay','--fail-preview','--clear-failure','--audit','--multipage'],true)) {
    $fixture=json_decode(file_get_contents($argv[2]??''),true,512,JSON_THROW_ON_ERROR);
    $c=documental_test_connection($fixture['db']); // guard loopback/prefixo/MySQL 8
    $action=$argv[1];
    if($action==='--delay-prepare') {
        $f=(int)$c->query("SELECT id FROM pagamento_fechamento WHERE colaborador_id=6 AND competencia='2026-09'")->fetch_assoc()['id'];
        $c->query("CREATE TRIGGER fixture_ui_delay BEFORE INSERT ON pagamento_fechamento_revisao FOR EACH ROW BEGIN IF NEW.fechamento_id=$f THEN DO SLEEP(28); END IF; END");
    } elseif($action==='--clear-delay') $c->query('DROP TRIGGER IF EXISTS fixture_ui_delay');
    elseif($action==='--fail-preview') {
        $f=(int)$c->query("SELECT id FROM pagamento_fechamento WHERE colaborador_id=8 AND competencia='2026-09'")->fetch_assoc()['id'];
        $c->query("CREATE TRIGGER fixture_ui_failure BEFORE UPDATE ON pagamento_fechamento_documento FOR EACH ROW BEGIN IF NEW.fechamento_id=$f AND NEW.estado='PREVIEW' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture UI failure'; END IF; END");
    } elseif($action==='--clear-failure') $c->query('DROP TRIGGER IF EXISTS fixture_ui_failure');
    elseif($action==='--multipage') {
        $s=new FechamentoRevisaoService($c); $list=$s->listarRevisoes(3,'2026-09',1); $version=(int)$list[count($list)-1]['numero'];
        $items=[];for($i=1;$i<=45;$i++)$items[]=['categoria'=>'Extra sintético de homologação multipágina '.$i,'referencia'=>'UI-M-'.$i,'valor_centavos'=>100];
        $r=$s->decidir(3,'2026-09',1,$version,'UI-multipage-bonus','BONUS',['estado'=>'DEFINIDO','motivo'=>'Homologação de paginação do PDF','itens'=>$items]);
        echo json_encode(['revision'=>$r['id'],'total'=>$r['snapshot']['composicao']['total_final_centavos']])."\n";
    } else {
        $out=[]; $files=new FechamentoDocumentoFiles($fixture['root']);
        $rows=$c->query("SELECT d.id,d.revisao_id,d.estado,d.pdf_hash,d.arquivo_preview,d.arquivo_definitivo,r.numero,r.total_final_centavos FROM pagamento_fechamento_documento d JOIN pagamento_fechamento_revisao r ON r.id=d.revisao_id ORDER BY d.id")->fetch_all(MYSQLI_ASSOC);
        foreach($rows as $row) {
            $result=array_intersect_key($row,array_flip(['id','revisao_id','estado','pdf_hash','numero','total_final_centavos']));
            if($row['pdf_hash']) {
                $preview=$files->path($row['arquivo_preview']);$result['preview_hash_ok']=hash_file('sha256',$preview)===$row['pdf_hash'];
                if($row['estado']==='CONFIRMADO')$result['same_bytes']=file_get_contents($preview)===file_get_contents($files->path($row['arquivo_definitivo']));
            }
            $out[]=$result;
        }
        $financial=$c->query("SELECT f.colaborador_id,COUNT(r.id) revisoes,MAX(r.numero) ultima FROM pagamento_fechamento f JOIN pagamento_fechamento_revisao r ON r.fechamento_id=f.id GROUP BY f.id ORDER BY f.colaborador_id")->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['documents'=>$out,'financial'=>$financial,'reserved'=>(int)$c->query("SELECT COUNT(*) n FROM pagamento_fechamento_documento_operacao WHERE estado='RESERVADA'")->fetch_assoc()['n']],JSON_PRETTY_PRINT)."\n";
    }
    $c->close(); exit;
}
throw new RuntimeException('Use --seed somente em MySQL 8 sintético loopback 3320.');

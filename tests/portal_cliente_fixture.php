<?php
declare(strict_types=1);
// CLI only. Persistent, synthetic browser fixtures. No email delivery here.
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../PortalCliente/auth.php';
$db=briefing_conn();$mode=$argv[1] ?? '';
$path=sys_get_temp_dir().'/improov-portal-browser-fixture.json';
if($mode==='create') {
    if(is_file($path))throw new RuntimeException('Existing fixture must be cleaned first.');
    $db->begin_transaction();
    try {
        $tag='PORTAL QA '.date('YmdHis');
        portal_exec($db,'INSERT INTO cliente(nome_cliente) VALUES (?)','s',[$tag]);$client=(int)$db->insert_id;$ids=[];
        foreach(['A','B'] as $suffix){portal_exec($db,'INSERT INTO obra(nome_obra,nome_real,cliente,status_obra,local) VALUES (?,?,?,0,?)','ssis',[$tag.' '.$suffix,'Projeto de teste '.$suffix,$client,'Ambiente de validação']);$ids[]=(int)$db->insert_id;}
        $existing=portal_one($db,'SELECT idcontato_cliente FROM contato_cliente WHERE email_normalizado=?','s',['pedrosabel08@gmail.com']);
        $state=['tag'=>$tag,'client'=>$client,'obras'=>$ids,'real_contact_existed'=>(bool)$existing,'created_at'=>date('c')];
        file_put_contents($path,json_encode($state,JSON_THROW_ON_ERROR));$db->commit();echo json_encode($state,JSON_UNESCAPED_UNICODE)."\n";
    }catch(Throwable $e){$db->rollback();throw $e;}
} elseif($mode==='otp') {
    $s=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$obra=(int)($argv[2] ?? 0);$name=$argv[3] ?? 'Visitante';
    if(!in_array($obra,$s['obras'],true)||!preg_match('/^[A-Za-z]+$/',$name))throw new RuntimeException('Invalid fixture');
    $db->begin_transaction();try{$p=portal_project($db,$obra,true);$email=strtolower($name).'-'.$s['client'].'@example.invalid';$code='';portal_issue_otp($db,$p,['email'=>$email,'nome'=>$name.' Teste','telefone'=>'47900000002'],function($e,$c)use(&$code){$code=$c;return true;});$db->commit();echo json_encode(['email'=>$email,'nome'=>$name.' Teste','code'=>$code])."\n";}catch(Throwable $e){$db->rollback();throw $e;}
} elseif($mode==='cleanup') {
    $s=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$db->begin_transaction();
    try {
        foreach($s['obras'] as $obra){
            $o=portal_one($db,'SELECT nome_obra,cliente FROM obra WHERE idobra=? FOR UPDATE','i',[$obra]);if(!$o||!str_starts_with($o['nome_obra'],$s['tag'])||(int)$o['cliente']!==$s['client'])throw new RuntimeException('Fixture identity mismatch');
            portal_exec($db,'DELETE f FROM portal_material_formato f JOIN portal_material m ON m.id=f.material_id WHERE m.obra_id=?','i',[$obra]);
            portal_exec($db,'DELETE x FROM portal_material_origem x JOIN portal_material m ON m.id=x.material_id WHERE m.obra_id=?','i',[$obra]);
            foreach(['portal_material','portal_solicitacao','portal_participante_disciplina','portal_projeto_disciplina','portal_participante','portal_evento'] as $t)portal_exec($db,"DELETE FROM $t WHERE obra_id=?",'i',[$obra]);
            portal_exec($db,'DELETE FROM external_otp_challenge WHERE portal_obra_id=?','i',[$obra]);
            portal_exec($db,'DELETE FROM portal_projeto WHERE obra_id=?','i',[$obra]);portal_exec($db,'DELETE FROM obra_contato WHERE obra_id=?','i',[$obra]);portal_exec($db,'DELETE FROM obra WHERE idobra=?','i',[$obra]);
        }
        // Only identities created under the synthetic client belong to this fixture.
        portal_exec($db,'DELETE s FROM external_auth_session s JOIN contato_cliente c ON c.idcontato_cliente=s.contato_cliente_id WHERE c.cliente_id=?','i',[$s['client']]);
        portal_exec($db,'DELETE FROM contato_cliente WHERE cliente_id=?','i',[$s['client']]);portal_exec($db,'DELETE FROM cliente WHERE idcliente=?','i',[$s['client']]);
        $db->commit();unlink($path);echo "Synthetic browser fixture removed.\n";
    }catch(Throwable $e){$db->rollback();throw $e;}
} else { throw new RuntimeException('Use create | otp <fixture obra> <name> | cleanup'); }

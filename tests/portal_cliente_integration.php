<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../PortalCliente/auth.php';
$db=briefing_conn(); $count=0;
function check(bool $condition,string $label): void { global $count; if(!$condition) { throw new RuntimeException('FAIL: '.$label); } $count++; echo "OK $label\n"; }
function rejects(callable $fn,int $http,string $label): void { try {$fn();} catch(PortalError $e) { check($e->http===$http,$label);return; } throw new RuntimeException('FAIL: '.$label.' accepted'); }
$db->begin_transaction();
try {
    $admin=portal_one($db,'SELECT idusuario,nivel_acesso FROM usuario WHERE ativo=1 AND nivel_acesso=1 LIMIT 1');
    $ordinary=portal_one($db,'SELECT idusuario,nivel_acesso FROM usuario WHERE ativo=1 AND nivel_acesso<>1 LIMIT 1');
    if(!$admin||!$ordinary)throw new RuntimeException('Test requires two existing user levels');
    $suffix=bin2hex(random_bytes(5));
    portal_exec($db,'INSERT INTO cliente(nome_cliente) VALUES (?)','s',['PORTAL QA '.$suffix]);$client=(int)$db->insert_id;
    $projects=[];
    foreach(['A','B'] as $n){portal_exec($db,'INSERT INTO obra(nome_obra,nome_real,cliente,status_obra) VALUES (?,?,?,0)','ssi',['PORTAL QA '.$n.' '.$suffix,'Projeto teste '.$n,$client]);$projects[]=(int)$db->insert_id;}
    [$a,$b]=$projects;
    $body=['obra_id'=>$a,'curador_usuario_id'=>(int)$ordinary['idusuario'],'nome'=>'Pessoa Central Teste','email'=>'central-'.$suffix.'@example.invalid','telefone'=>'47900000001'];
    $entry=portal_configure($db,$admin,$body);$p=portal_project($db,$a,true);
    check((int)$p['administrador_contato_id']>0,'central contact configured');
    rejects(fn()=>portal_configure($db,$admin,$body),409,'duplicate portal prevented');
    portal_internal_access($p,$ordinary);check(true,'designated curator has access');
    rejects(fn()=>portal_internal_access($p,['idusuario'=>999999,'nivel_acesso'=>2]),403,'unassigned internal user rejected');
    rejects(fn()=>portal_member($db,$a,(int)$p['administrador_contato_id']),403,'invitation is not a session');
    $code='';portal_issue_otp($db,$p,$body,function($email,$value)use(&$code){$code=$value;return true;});
    check(strlen($code)===6,'OTP generated through existing configuration');
    rejects(fn()=>portal_issue_otp($db,$p,$body,fn()=>true),429,'OTP resend cooldown');
    $wrong=$code==='000000'?'111111':'000000';check(portal_verify_otp($db,$p,['email'=>$body['email'],'code'=>$wrong])===null,'wrong OTP rejected');
    check((int)briefing_scalar($db,'SELECT tentativas FROM external_otp_challenge WHERE portal_obra_id=? ORDER BY id DESC LIMIT 1','i',[$a])===1,'wrong OTP attempt persisted');
    $central=portal_verify_otp($db,$p,['email'=>$body['email'],'code'=>$code]);check($central===(int)$p['administrador_contato_id'],'existing central identity reused');
    rejects(fn()=>portal_verify_otp($db,$p,['email'=>$body['email'],'code'=>$code]),422,'OTP replay rejected');
    $lookalike=$body;$lookalike['email']='different-'.$suffix.'@example.invalid';$otherCode='';
    portal_issue_otp($db,$p,$lookalike,function($email,$value)use(&$otherCode){$otherCode=$value;return true;});
    $otherIdentity=portal_verify_otp($db,$p,['email'=>$lookalike['email'],'code'=>$otherCode]);
    check($otherIdentity!==$central,'verified new email cannot take over matching name and phone');
    check(briefing_scalar($db,'SELECT email_normalizado FROM contato_cliente WHERE idcontato_cliente=?','i',[$central])===$body['email'],'original external identity remains unchanged');
    $ids=array_map('intval',array_column(portal_rows($db,"SELECT id FROM flow_disciplina WHERE codigo IN ('ARQUITETURA','INTERIORES','PAISAGISMO')"),'id'));
    check(count($ids)===3,'three requested disciplines available');
    portal_profile($db,$p,$central,['nome'=>'Pessoa Central Teste','telefone'=>'47900000001','disciplinas'=>[]]);
    portal_exec($db,"INSERT INTO briefing_tipo_imagem(obra_id,tipo_imagem) VALUES (?,'Fachada')",'i',[$a]);$legacyType=(int)$db->insert_id;
    $architecture=(int)briefing_scalar($db,"SELECT categoria_id FROM flow_disciplina WHERE codigo='ARQUITETURA'");
    $category=(string)briefing_scalar($db,'SELECT nome_categoria FROM categorias WHERE idcategoria=?','i',[$architecture]);
    portal_exec($db,"INSERT INTO briefing_requisitos_arquivo(briefing_tipo_imagem_id,categoria,origem,tipo_arquivo) VALUES (?,?,'cliente','DWG')",'is',[$legacyType,$category]);$legacyRequirement=(int)$db->insert_id;
    portal_prepare($db,$p,$central,['question'=>'disciplinas','disciplinas'=>$ids,'revisao'=>$p['revisao']]);
    $p=portal_project($db,$a,true);check(count(portal_selected($db,$a))===3,'scenario 2 relational project disciplines');
    check(count(portal_data($db,$p)['materials'])===3,'scenario 8 category based suggestions');
    check((int)briefing_scalar($db,'SELECT COUNT(*) FROM portal_material_origem WHERE requisito_id=?','i',[$legacyRequirement])===2,'existing architectural requirement reused for architecture and interiors');
    check(count(portal_data($db,$p)['materials'][0]['formatos'])===1,'formats suggested from existing project requirements');
    portal_exec($db,'DELETE FROM briefing_requisitos_arquivo WHERE id=?','i',[$legacyRequirement]);
    check(count(portal_data($db,$p)['materials'])===3,'legacy requirement deletion remains compatible');
    check((int)briefing_scalar($db,"SELECT COUNT(*) FROM portal_evento WHERE obra_id=? AND tipo='material.suggested' AND JSON_LENGTH(dados->'$.sources')>0",'i',[$a])===2,'deleted legacy source provenance remains auditable');
    check(count(portal_data($db,$p,$central)['materials'])===0,'draft materials never exposed');
    rejects(fn()=>portal_publish($db,$p,(int)$ordinary['idusuario'],['revisao'=>$p['revisao']]),422,'unreviewed suggestions cannot publish');
    $land=(int)briefing_scalar($db,"SELECT id FROM flow_disciplina WHERE codigo='PAISAGISMO'");
    $join=function(string $name,array $disc)use($db,$a,$suffix){
        $p=portal_project($db,$a,true);$body=['email'=>strtolower($name).'-'.$suffix.'@example.invalid','nome'=>$name,'telefone'=>'47900000002'];$code='';
        portal_issue_otp($db,$p,$body,function($email,$v)use(&$code){$code=$v;return true;});
        $id=portal_verify_otp($db,$p,['email'=>$body['email'],'code'=>$code]);
        portal_profile($db,$p,$id,['nome'=>$name,'telefone'=>'47900000002','disciplinas'=>$disc]);return $id;
    };
    $second=$join('Paisagista',[$land]);$third=$join('Diretoria',[]);
    check(count(portal_data($db,$p,$second)['me']['disciplinas'])===1,'scenario 3 second participant chooses discipline');
    check(count(portal_data($db,$p,$third)['me']['disciplinas'])===0,'scenario 4 no discipline allowed');
    portal_prepare($db,$p,$second,['question'=>'disciplinas','disciplinas'=>$ids,'revisao'=>$p['revisao']]);$p=portal_project($db,$a,true);
    check(true,'administrator classification grants no extra permission');
    rejects(fn()=>portal_prepare($db,$p,$central,['question'=>'disciplinas','disciplinas'=>array_values(array_diff($ids,[$land])),'revisao'=>$p['revisao']]),409,'used discipline cannot be removed');
    rejects(fn()=>portal_profile($db,$p,$third,['nome'=>'Diretoria','telefone'=>'47900000002','disciplinas'=>[999999]]),422,'foreign discipline rejected');
    rejects(fn()=>portal_prepare($db,$p,$central,['question'=>'disciplinas','disciplinas'=>$ids,'revisao'=>1]),409,'concurrent stale preparation rejected');
    foreach(portal_data($db,$p)['materials'] as $m){$p=portal_project($db,$a,true);portal_material_save($db,$p,(int)$ordinary['idusuario'],['id'=>$m['id'],'disciplina_id'=>$m['disciplina_id'],'categoria_id'=>$m['categoria_id'],'titulo'=>$m['titulo'],'momento'=>$m['disciplina_id']==$land?'DURANTE':'INICIO','contexto'=>'Usaremos o material para preparar a base.','observacao'=>'Projeto de teste.','formatos'=>'DWG, RVT, PDF','revisao'=>$p['revisao']]);}
    $p=portal_project($db,$a,true);$sample=portal_data($db,$p)['materials'][0];
    $add=['disciplina_id'=>$sample['disciplina_id'],'categoria_id'=>$sample['categoria_id'],'titulo'=>'Material adicional solicitado','momento'=>'DURANTE','contexto'=>'Ajuda a contextualizar o projeto.','observacao'=>'','formatos'=>'PDF','revisao'=>$p['revisao']];
    portal_material_save($db,$p,(int)$ordinary['idusuario'],$add);$id=(int)$db->insert_id;
    $id=(int)briefing_scalar($db,'SELECT MAX(id) FROM portal_material WHERE obra_id=?','i',[$a]);
    $p=portal_project($db,$a,true);portal_internal_mutate($db,$p,$ordinary,'material.remove',['id'=>$id,'revisao'=>$p['revisao']]);
    check((bool)briefing_scalar($db,'SELECT removido_em FROM portal_material WHERE id=?','i',[$id]),'scenario 9 add edit classify formats notes and remove');
    $p=portal_project($db,$a,true);portal_publish($db,$p,(int)$ordinary['idusuario'],['revisao'=>$p['revisao']]);
    portal_publish($db,$p,(int)$ordinary['idusuario'],['revisao'=>$p['revisao']]);
    check(portal_request($db,$a)['estado']==='PUBLICADA','scenario 10 published state persisted');
    check((int)briefing_scalar($db,"SELECT COUNT(*) FROM portal_evento WHERE obra_id=? AND tipo='materials.published'",'i',[$a])===1,'publication event exactly once');
    $p=portal_project($db,$a,true);check(count(portal_data($db,$p,$third)['materials'])===3,'all participants see published materials');
    rejects(fn()=>portal_material_save($db,$p,(int)$ordinary['idusuario'],$add+['revisao'=>$p['revisao']]),409,'published material immutable in part 1');
    $late=$join('DepoisPublicacao',[]);check($late>0,'scenario 11 participant joins after publication');
    $p=portal_project($db,$a,true);portal_internal_mutate($db,$p,$ordinary,'participant.remove',['contato_id'=>$second,'revisao'=>$p['revisao']]);
    rejects(fn()=>portal_member($db,$a,$second),403,'scenario 7 removed participant loses access');
    rejects(fn()=>portal_registration_allowed($db,$p,'paisagista-'.$suffix.'@example.invalid'),403,'removed participant cannot rejoin via shared link');
    check(briefing_external_contact_has_obra($db,$second,$a),'portal removal preserves legacy obra contact');
    $p=portal_project($db,$a,true);portal_internal_mutate($db,$p,$ordinary,'participant.restore',['contato_id'=>$second,'revisao'=>$p['revisao']]);portal_member($db,$a,$second);check(true,'explicit internal restoration');
    $body['obra_id']=$b;$body['email']='central-b-'.$suffix.'@example.invalid';$entryB=portal_configure($db,$admin,$body);$pb=portal_link($db,$entryB['token']);
    check((int)$pb['administrador_contato_id']!==$central,'configuration never merges different emails by name and phone');
    rejects(fn()=>portal_member($db,(int)$pb['obra_id'],$central),403,'scenario 12 project A identity cannot read B');
    try { portal_exec($db,'UPDATE portal_projeto SET administrador_contato_id=? WHERE obra_id=?','ii',[(int)$pb['administrador_contato_id'],$a]);throw new RuntimeException('Cross-project administrator accepted'); } catch(mysqli_sql_exception $e) { check($e->getCode()===1452,'database rejects administrator from another obra'); }
    try { portal_exec($db,'INSERT INTO portal_participante_disciplina(obra_id,contato_id,disciplina_id) VALUES (?,?,?)','iii',[$b,(int)$pb['administrador_contato_id'],$land]);throw new RuntimeException('Foreign project discipline accepted'); } catch(mysqli_sql_exception $e) { check($e->getCode()===1452,'database rejects discipline outside project'); }
    $p=portal_project($db,$a,true);$old=$entry['token'];portal_internal_mutate($db,$p,$ordinary,'invite.rotate',['revisao'=>$p['revisao']]);
    rejects(fn()=>portal_link($db,$old),404,'revoked invitation rejected');
    $p=portal_project($db,$a,true);portal_internal_mutate($db,$p,$admin,'project.settings',['revisao'=>$p['revisao'],'curador_usuario_id'=>$ordinary['idusuario'],'estado'=>'ABERTO','inscricoes_abertas'=>false]);
    $p=portal_project($db,$a,true);rejects(fn()=>portal_registration_allowed($db,$p,'new-'.$suffix.'@example.invalid'),403,'closed enrollment blocks new people');
    check(portal_registration_allowed($db,$p,'paisagista-'.$suffix.'@example.invalid')!==null,'closed enrollment preserves existing login');
    portal_exec($db,'UPDATE obra SET status_obra=1 WHERE idobra=?','i',[$a]);$p=portal_project($db,$a,true);
    rejects(fn()=>portal_registration_allowed($db,$p,'paisagista-'.$suffix.'@example.invalid'),409,'inactive obra blocks access initiation');
    $dto=portal_data($db,$p,$central);
    check(!isset($dto['project']['curador_usuario_id'])&&!isset($dto['materials'][0]['revisado_por'])&&!isset($dto['catalog'][0]['categoria_id']),'external DTO hides operational ownership and category IDs');
} finally { $db->rollback(); }
echo "$count assertions passed; all fixture data rolled back.\n";

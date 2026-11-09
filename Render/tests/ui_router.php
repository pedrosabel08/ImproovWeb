<?php
// Explicit local UI simulation. This file has no Deadline/process execution code.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$route = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (in_array($route,['/ImproovWeb/','/ImproovWeb/index.html'],true)) {require __DIR__.'/../../login-preview.php';return true;}
if (!str_ends_with($route,'/') && is_dir('C:/xampp/htdocs'.$route)) {
    header('Location: '.$route.'/'); return true;
}
if (in_array($route,['/ImproovWeb/Render/','/ImproovWeb/Render/index.php'],true)) {
    $previousDirectory=getcwd();chdir(__DIR__.'/..');
    ob_start();require __DIR__.'/../index.php';$html=ob_get_clean();
    chdir($previousDirectory);
    $panel=file_get_contents(__DIR__.'/snapshot_test_panel.html');
    echo str_replace('<div class="render-ef-status"',$panel.'<div class="render-ef-status"',$html);
    return true;
}
if ($route !== '/ImproovWeb/Render/render_ef.php') return false;
require __DIR__.'/../../config/session_bootstrap.php';
require __DIR__.'/../../conexao.php';
require __DIR__.'/../render_ef_helpers.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if (empty($_SESSION['logado'])) { http_response_code(401); throw new RuntimeException('Entre no ambiente local de testes.'); }
    $post=$_SERVER['REQUEST_METHOD']==='POST';
    if ($post && !hash_equals($_SESSION['render_ef_csrf']??'',$_POST['csrf']??'')) throw new RuntimeException('CSRF inválido.');
    $render=(int)($_POST['render_id']??$_GET['render_id']??0);
    $source=render_ef_rows($conn,'SELECT r.*,i.obra_id,i.imagem_nome,s.nome_status etapa_atual,o.nomenclatura,o.nome_obra FROM render_alta r JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=r.imagem_id JOIN status_imagem s ON s.idstatus=i.status_id JOIN obra o ON o.idobra=i.obra_id WHERE r.idrender_alta=?','i',[$render])[0]??null;
    if (!$source || !render_ef_authorized($conn,$_SESSION,$source)) { http_response_code(403); throw new RuntimeException('Sem permissão para esta imagem.'); }
    $source['nome_obra']='SIMULAÇÃO LOCAL — nenhum envio à farm';
    $files=render_ef_files($conn,(int)$source['imagem_id']);
    $request=$_SESSION['ef_snapshot_fixture'][$render]??null;
    $action=$_POST['action']??$_GET['action']??'status';
    if ($post && $action==='qa_reset') {
        $scenario=$_POST['scenario']??'valid';
        if (!in_array($scenario,['valid','missing','dimensions'],true)) throw new RuntimeException('Cenário inválido.');
        $_SESSION['ef_snapshot_scenario'][$render]=$scenario;unset($_SESSION['ef_snapshot_fixture'][$render]);$request=null;
    }

    if ($post && $action==='prepare' && ($request['submissao_uuid']??'')!==($_POST['client_key']??'')) {
        $chosen=array_values(array_filter($files,fn($f)=>(int)$f['id']===(int)($_POST['file_id']??0)))[0]??null;
        if (!$chosen || ($_POST['file_confirmed']??'')!=='1') throw new RuntimeException('Confirme o arquivo final.');
        $request=['id'=>100,'origem'=>'NAS','arquivo_log_id'=>(int)$chosen['id'],'arquivo_confirmado_por'=>(int)$_SESSION['idcolaborador'],'arquivo_confirmado_em'=>date('c'),'submissao_uuid'=>$_POST['client_key'],'novo_job_id'=>null,'submissao_iniciada_em'=>null,'render_ef_id'=>null,'snapshot_id'=>100,'status'=>'SNAPSHOT_PREPARAR','etapa'=>'SNAPSHOT','mensagem'=>'SIMULAÇÃO: recuperando snapshot previamente preservado, mesmo com o job original indisponível.','plan_hash'=>'fixture','criado_em'=>date('c'),'plan'=>['scene'=>$chosen['caminho'],'snapshot_id'=>100,'camera'=>'Camera_aprovada_SIMULADA','renderer'=>'Corona:Corona','generic'=>['Pool'=>'still','Group'=>'farm_dedicated','Priority'=>'100','ChunkSize'=>'1','Frames'=>'0'],'blockers'=>[],'selection'=>['edge'=>(int)($_POST['edge']??5000)]]];
    } elseif (!$post && $action==='status' && $request && $request['status']==='SNAPSHOT_PREPARAR') {
        $scenario=$_SESSION['ef_snapshot_scenario'][$render]??'valid';
        if ($scenario==='missing') {$request['status']='ERRO';$request['etapa']='SNAPSHOT';$request['mensagem']='SIMULAÇÃO: snapshot ausente e job original indisponível. O envio foi bloqueado, sem inspeção como fallback.';$request['snapshot_id']=null;$request['plan']=['scene'=>$request['plan']['scene'],'blockers'=>[]];}
        elseif ($scenario==='dimensions') {$request['status']='CONFIGURAR';$request['etapa']='DIMENSOES';$request['mensagem']='SIMULAÇÃO: resolução original indisponível. Confirme as dimensões comprovadas.';}
        else {$request['status']='PRONTO';$request['etapa']='REVISAO';$request['mensagem']='SIMULAÇÃO: configuração recuperada do snapshot aprovado. Nenhum Max foi aberto.';$request['plan']+=['old_width'=>1920,'old_height'=>1080,'width'=>$request['plan']['selection']['edge'],'height'=>(int)floor(1080*$request['plan']['selection']['edge']/1920+0.5),'frames'=>'0','destination'=>'Destino exclusivo SIMULADO','outputs'=>['RenderOutput'=>'Saída SIMULADA.exr']];}
    } elseif ($post && $action==='configure' && $request['status']==='CONFIGURAR') {
        $w=(int)($_POST['source_width']??0);$h=(int)($_POST['source_height']??0);$edge=$request['plan']['selection']['edge'];
        if (min($w,$h)<1 || max($w,$h)>65536 || ($_POST['dimensions_confirmed']??'')!=='1') throw new RuntimeException('Confirme as dimensões válidas.');
        $request['status']='PRONTO';$request['etapa']='REVISAO';$request['mensagem']='SIMULAÇÃO: dimensões confirmadas. Pronto para revisão.';
        $request['plan']+=['old_width'=>$w,'old_height'=>$h,'width'=>(int)floor($w*$edge/max($w,$h)+0.5),'height'=>(int)floor($h*$edge/max($w,$h)+0.5),'frames'=>'0','destination'=>'Destino exclusivo SIMULADO','outputs'=>['RenderOutput'=>'Saída SIMULADA.exr']];
    } elseif ($post && $action==='cancel') {$request['status']='CANCELADO';$request['mensagem']='SIMULAÇÃO encerrada.';}
    elseif ($post && $action==='confirm') {
        if (!$request || $request['status']!=='PRONTO') throw new RuntimeException('Prepare e revise o plano primeiro.');
        $request['status']='CONCLUIDO';$request['etapa']='SIMULADO';$request['mensagem']='TESTE CONCLUÍDO: nenhum job foi criado ou liberado no Deadline. Nenhuma tentativa real foi alterada.';
    }
    $_SESSION['ef_snapshot_fixture'][$render]=$request;
    echo json_encode(['status'=>'sucesso','schema_ready'=>true,'worker_online'=>true,'submission_enabled'=>true,'snapshot_enabled'=>true,'snapshot'=>!empty($request['snapshot_id'])?['id'=>100,'estado'=>'CAPTURADO']:null,'files'=>$files,'context'=>array_intersect_key($source,array_flip(['imagem_nome','nomenclatura','nome_obra','status','etapa_atual'])),'request'=>$request,'runtime'=>null,'history'=>$request?[$request]:[]],JSON_UNESCAPED_UNICODE);
} catch(Throwable $error) {http_response_code(http_response_code()>=400?http_response_code():400);echo json_encode(['status'=>'erro','message'=>$error->getMessage()]);}
return true;

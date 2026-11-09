<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/deadline_flow.php';
require_once __DIR__ . '/render_ef_helpers.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if (empty($_SESSION['logado'])) {
        http_response_code(401);
        throw new RuntimeException('Sessão expirada. Entre novamente.');
    }
    $session = $_SESSION;
    $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    if ($post && (empty($session['render_ef_csrf']) || !hash_equals($session['render_ef_csrf'], (string)($_POST['csrf'] ?? '')))) {
        http_response_code(403);
        throw new RuntimeException('Atualize a página antes de solicitar o render.');
    }
    session_write_close();
    $renderId = (int)($_POST['render_id'] ?? $_GET['render_id'] ?? 0);
    $source = render_ef_rows($conn, 'SELECT r.*,i.obra_id,i.imagem_nome,i.status_id AS etapa_atual_id,s.nome_status AS etapa_atual,o.nomenclatura,o.nome_obra FROM render_alta r JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=r.imagem_id JOIN status_imagem s ON s.idstatus=i.status_id JOIN obra o ON o.idobra=i.obra_id WHERE r.idrender_alta=?', 'i', [$renderId])[0] ?? null;
    if (!$source || !render_ef_authorized($conn, $session, $source)) {
        http_response_code(403);
        throw new RuntimeException('Você não tem permissão para enviar render desta imagem e obra.');
    }
    $image = (int)$source['imagem_id'];
    $action = (string)($_POST['action'] ?? $_GET['action'] ?? 'status');
    $files = render_ef_files($conn, $image);
    $context = array_intersect_key($source,array_flip(['imagem_nome','nomenclatura','nome_obra','status','etapa_atual']));
    $snapshotSchema=(bool)render_ef_rows($conn, "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='render_job_snapshots'")
        && count(render_ef_rows($conn,"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='render_ef_solicitacoes' AND COLUMN_NAME IN ('snapshot_id','origem_tentativa_id','arquivo_confirmado_por','arquivo_confirmado_em')"))===4;
    $schema = $snapshotSchema && (bool)render_ef_rows($conn, "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='render_ef_solicitacoes' AND COLUMN_NAME='submissao_uuid'");
    if (!$schema) {
        if ($post) throw new RuntimeException('As migrations de preparação NAS e snapshots estão pendentes. Solicitações permanecem bloqueadas.');
        echo json_encode(['status'=>'sucesso','schema_ready'=>false,'request'=>null,'files'=>$files,'context'=>$context,'history'=>[],'worker_online'=>false],JSON_UNESCAPED_UNICODE);
        exit;
    }
    $conn->begin_transaction();
    $locked = render_ef_rows($conn, 'SELECT status_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra=? FOR UPDATE', 'i', [$image])[0];
    // Re-read approval while holding the image lock; never trust the browser's state.
    $current = render_ef_rows($conn,'SELECT status,excluido_em,deadline_job_id FROM render_alta WHERE idrender_alta=? FOR UPDATE','i',[$renderId])[0];
    $requestId = (int)($_POST['request_id'] ?? $_GET['request_id'] ?? 0);
    $request = $requestId
        ? (render_ef_rows($conn,'SELECT * FROM render_ef_solicitacoes WHERE imagem_id=? AND id=? FOR UPDATE','ii',[$image,$requestId])[0] ?? null)
        : (render_ef_rows($conn,'SELECT * FROM render_ef_solicitacoes WHERE imagem_id=? ORDER BY operacao_ativa_imagem IS NOT NULL DESC,id DESC LIMIT 1 FOR UPDATE','i',[$image])[0] ?? null);
    $ownRecovery = $action === 'resume' && !empty($request['novo_job_id']) && $current['deadline_job_id'] === $request['novo_job_id'];
    if ($post && $action !== 'cancel' && ((int)$locked['status_id'] !== (int)$source['etapa_atual_id'] || strtoupper($source['etapa_atual']) !== 'EF' || ($current['status'] !== 'Aprovado' && !$ownRecovery) || $current['excluido_em'])) {
        throw new RuntimeException('A imagem deve estar em EF e o render de origem deve estar aprovado e disponível.');
    }
    if ($post && $action === 'prepare') {
        $key = (string)($_POST['client_key'] ?? '');
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $key)) throw new RuntimeException('Identificador de solicitação inválido.');
        $same = render_ef_rows($conn,'SELECT * FROM render_ef_solicitacoes WHERE submissao_uuid=? AND imagem_id=?','si',[$key,$image])[0] ?? null;
        $active = render_ef_rows($conn,'SELECT * FROM render_ef_solicitacoes WHERE imagem_id=? AND operacao_ativa_imagem IS NOT NULL FOR UPDATE','i',[$image])[0] ?? null;
        if ($same || $active) {
            $request = $same ?: $active;
        } else {
            $origin = (string)($_POST['origin'] ?? '');
            if (!in_array($origin,['JOB','NAS'],true)) throw new RuntimeException('Selecione a origem da submissão.');
            $file = $origin === 'NAS' ? (int)($_POST['file_id'] ?? 0) : null;
            if ($origin === 'NAS' && !in_array($file,array_map('intval',array_column($files,'id')),true)) throw new RuntimeException('Selecione um .max registrado para esta imagem.');
            if ($origin !== 'NAS') throw new RuntimeException('Selecione o .max final. A preparação usa a configuração do render aprovado.');
            if (($_POST['file_confirmed']??'') !== '1') throw new RuntimeException('Confirme o arquivo .max final desta imagem.');
            $edge=(int)($_POST['edge']??5000);
            if ($edge<1 || $edge>65536) throw new RuntimeException('Dimensão de alta inválida.');
            $snapshotWorker=render_ef_rows($conn,"SELECT detalhes FROM deadline_workers WHERE versao='2.4' AND status IN ('ATIVO','DRY_RUN') AND ultimo_heartbeat>=NOW()-INTERVAL 180 SECOND ORDER BY ultimo_heartbeat DESC LIMIT 1")[0]??null;
            if (!$snapshotWorker || empty(json_decode($snapshotWorker['detalhes'],true)['snapshot_flow_enabled'])) throw new RuntimeException('A preparação por snapshots ainda não foi habilitada.');
            $approved=render_ef_rows($conn,"SELECT rt.id,rt.deadline_job_id FROM render_tentativas rt WHERE rt.render_id=? AND rt.imagem_id=? AND rt.deadline_job_id IS NOT NULL AND (rt.status='APROVADA' OR rt.motivo_encerramento='APROVADA_NO_FLOW') AND (? IS NULL OR ?='' OR rt.deadline_job_id=?)",'iisss',[$renderId,$image,$current['deadline_job_id'],$current['deadline_job_id'],$current['deadline_job_id']]);
            if (count($approved)!==1) throw new RuntimeException('Não há uma única tentativa vinculada ao render aprovado. A configuração não será escolhida pela data.');
            $job=deadline_flow_valid_job_id($approved[0]['deadline_job_id']);
            if (!$job) throw new RuntimeException('Job ID do render aprovado indisponível.');
            if (render_ef_rows($conn,"SELECT rt.id FROM render_tentativas rt WHERE rt.imagem_id=? AND rt.status_id=? AND rt.ativa=1 AND rt.deadline_job_id IS NOT NULL AND rt.status IN ('VINCULADA','EM_ANDAMENTO','EM_APROVACAO','ERRO','EXCLUSAO_PENDENTE') LIMIT 1",'ii',[$image,(int)$source['etapa_atual_id']])) throw new RuntimeException('Já existe um Job EF ativo para esta imagem.');
            render_ef_exec($conn,"INSERT INTO render_ef_solicitacoes (render_origem_id,imagem_id,criado_por,origem_job_id,origem,arquivo_log_id,submissao_uuid,status,mensagem) VALUES (?,?,?,?,?,?,?,'SNAPSHOT_PREPARAR','Preparando configuração a partir do render aprovado.')",'iiissis',[$renderId,$image,(int)$session['idcolaborador'],$job,$origin,$file,$key]);
            $newRequestId=$conn->insert_id;
            render_ef_exec($conn,'UPDATE render_ef_solicitacoes SET configuracao_json=?,arquivo_confirmado_por=?,arquivo_confirmado_em=NOW(),origem_tentativa_id=? WHERE id=?','siii',[json_encode(['edge'=>$edge],JSON_THROW_ON_ERROR),(int)$session['idcolaborador'],(int)$approved[0]['id'],$newRequestId]);
            $request = render_ef_rows($conn,'SELECT * FROM render_ef_solicitacoes WHERE id=?','i',[$newRequestId])[0];
            render_ef_audit($conn,(int)$request['id'],(int)$session['idcolaborador']);
        }
    } elseif ($post) {
        if (!$request || $requestId !== (int)$request['id']) throw new RuntimeException('Solicitação inválida ou desatualizada.');
        if ($action === 'configure' && $request['status'] === 'CONFIGURAR' && $request['origem'] === 'NAS') {
            $prior=json_decode($request['configuracao_json']??'{}',true)?:[];
            $selection=['source_width'=>(int)($_POST['source_width']??0),'source_height'=>(int)($_POST['source_height']??0),'dimensions_confirmed'=>($_POST['dimensions_confirmed']??'')==='1','edge'=>(int)($prior['edge']??5000),'dimensions_by'=>(int)$session['idcolaborador'],'dimensions_at'=>date('c')];
            if (!$selection['dimensions_confirmed'] || min($selection['source_width'],$selection['source_height'])<1 || max($selection['source_width'],$selection['source_height'])>65536) throw new RuntimeException('Informe e confirme as dimensões originais comprovadas.');
            render_ef_exec($conn,"UPDATE render_ef_solicitacoes SET configuracao_json=?,status='CONFIGURER',etapa='CONFIGURACAO',mensagem='Validando os parâmetros selecionados.' WHERE id=?",'si',[json_encode($selection,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$requestId]);
        } elseif ($action === 'confirm' && $request['status'] === 'PRONTO') {
            $readyWorker=render_ef_rows($conn,"SELECT detalhes FROM deadline_workers WHERE versao='2.4' AND status IN ('ATIVO','DRY_RUN') AND ultimo_heartbeat>=NOW()-INTERVAL 180 SECOND ORDER BY ultimo_heartbeat DESC LIMIT 1")[0]??null;
            if (!$readyWorker || empty(json_decode($readyWorker['detalhes'],true)['ef_submit_enabled'])) throw new RuntimeException('A submissão real ainda não foi habilitada no worker autorizado.');
            if (!hash_equals(hash('sha256',(string)$request['plano_json']),(string)($_POST['plan_hash'] ?? ''))) throw new RuntimeException('O plano mudou. Confira novamente.');
            render_ef_exec($conn,"UPDATE render_ef_solicitacoes SET status='EXECUTAR_V2',etapa='FILA',confirmado_por=?,confirmado_em=NOW(),mensagem='Envio confirmado. Aguardando o worker.' WHERE id=?",'ii',[(int)$session['idcolaborador'],$requestId]);
        } elseif ($action === 'resume' && in_array($request['status'],['ERRO','ATENCAO'],true) && ($request['novo_job_id'] || $request['submissao_iniciada_em'])) {
            render_ef_exec($conn,"UPDATE render_ef_solicitacoes SET status='RECONCILIAR',mensagem='Consultando o mesmo identificador, sem nova submissão.' WHERE id=?",'i',[$requestId]);
        } elseif ($action === 'cancel' && !$request['novo_job_id'] && !$request['submissao_iniciada_em'] && in_array($request['status'],['CONFIGURAR','PRONTO','ERRO'],true)) {
            render_ef_exec($conn,"UPDATE render_ef_solicitacoes SET status='CANCELADO',mensagem='Preparação encerrada pelo usuário.' WHERE id=?",'i',[$requestId]);
        } else throw new RuntimeException('Ação indisponível no estado atual. Consulte a solicitação novamente.');
        render_ef_audit($conn,$requestId,(int)$session['idcolaborador']);
    } elseif ($action !== 'status') throw new RuntimeException('Ação inválida.');
    if ($request) $request = render_ef_rows($conn,'SELECT * FROM render_ef_solicitacoes WHERE id=?','i',[(int)$request['id']])[0];
    $history = render_ef_rows($conn,'SELECT id,origem,arquivo_log_id,criado_por,criado_em,status,etapa,novo_job_id,submissao_uuid,mensagem FROM render_ef_solicitacoes WHERE imagem_id=? ORDER BY id DESC LIMIT 30','i',[$image]);
    $runtime = !empty($request['novo_job_id']) ? (render_ef_rows($conn,'SELECT rt.status,r.has_error,r.errors FROM render_tentativas rt JOIN render_alta r ON r.idrender_alta=rt.render_id WHERE rt.deadline_job_id=? LIMIT 1','s',[$request['novo_job_id']])[0] ?? null) : null;
    $conn->commit();
    $worker = render_ef_rows($conn,"SELECT detalhes FROM deadline_workers WHERE versao='2.4' AND status IN ('ATIVO','DRY_RUN') AND ultimo_heartbeat>=NOW()-INTERVAL 180 SECOND ORDER BY ultimo_heartbeat DESC LIMIT 1")[0]??null;
    $enabled=$worker && !empty(json_decode($worker['detalhes'],true)['ef_submit_enabled']);
    $snapshotState=null;
    if ($request && $request['origem_job_id']) $snapshotState=render_ef_rows($conn,'SELECT s.id,s.deadline_job_id,s.tentativa_id,s.estado,s.capturado_em FROM render_job_snapshots s WHERE s.deadline_job_id=?','s',[$request['origem_job_id']])[0]??null;
    echo json_encode(['status'=>'sucesso','schema_ready'=>true,'worker_online'=>(bool)$worker,'submission_enabled'=>$enabled,'snapshot_enabled'=>$worker && !empty(json_decode($worker['detalhes'],true)['snapshot_flow_enabled']),'snapshot'=>$snapshotState,'files'=>$files,'context'=>$context,'runtime'=>$runtime,'request'=>render_ef_public_request($request),'history'=>$history],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    if (isset($conn)) { try { $conn->rollback(); } catch (Throwable $ignored) {} }
    if (http_response_code() < 400) http_response_code(400);
    echo json_encode(['status'=>'erro','message'=>$error->getMessage()],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}

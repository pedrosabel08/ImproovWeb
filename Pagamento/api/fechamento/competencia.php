<?php
require_once __DIR__.'/../../pagamento_auth.php';
require_once __DIR__.'/../../../config/pagamento_fechamento.php';
require_once __DIR__.'/../../services/FechamentoCompetenciaService.php';
header('Cache-Control: no-store');
$conn=null;
try {
    $post=($_SERVER['REQUEST_METHOD']??'')==='POST';
    if (!$post && ($_SERVER['REQUEST_METHOD']??'')!=='GET') pagamento_json(['success'=>false,'error'=>'Método não permitido.'],405);
    pagamento_require_gestor($post);
    if (!pagamento_fechamento_enabled()) pagamento_json(['success'=>false,'error'=>'Fechamento indisponível.'],404);
    $d=$post?pagamento_request_json():$_GET;
    $action=$post?($d['acao']??''):'resumo';
    $allowed=match($action) {
        'resumo'=>['competencia'], 'concluir','quitar'=>['acao','competencia','idempotency_key'],
        'pagar'=>['acao','competencia','idempotency_key','colaborador_id','data_pagamento','observacao'],
        default=>throw new InvalidArgumentException('Ação inválida.')
    };
    if (array_diff(array_keys($d),$allowed)) throw new InvalidArgumentException('Campos não permitidos.');
    $ref=$d['competencia']??'';
    if (!is_string($ref) || !pagamento_competencia_nova($ref)) throw new InvalidArgumentException('Competência fora do novo fluxo.');
    $u=pagamento_current_user_id(); session_write_close();
    $conn=pagamento_fechamento_connection();
    if (!FechamentoCompetenciaService::disponivel($conn)) throw new RuntimeException('Migration da competência ausente.');
    $s=new FechamentoCompetenciaService($conn,$u);
    if ($post) {
        $key=$d['idempotency_key']??'';
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D',$key)) throw new InvalidArgumentException('Chave idempotente obrigatória.');
    }
    $data=match($action) {
        'resumo'=>$s->resumo($ref), 'concluir'=>$s->concluir($ref,$key), 'quitar'=>$s->concluirPagamento($ref,$key),
        'pagar'=>$s->pagar($ref,filter_var($d['colaborador_id']??0,FILTER_VALIDATE_INT)?:0,$key,(string)($d['data_pagamento']??''),(string)($d['observacao']??''))
    };
    pagamento_json(['success'=>true,'data'=>$data]);
} catch(InvalidArgumentException|DomainException $e) {
    pagamento_json(['success'=>false,'error'=>$e->getMessage()],409);
} catch(Throwable $e) {
    error_log('pagamento_competencia: '.get_class($e));
    pagamento_json(['success'=>false,'error'=>'Não foi possível carregar ou registrar a competência. Confira a implantação e tente novamente.'],503);
} finally { if ($conn instanceof mysqli) $conn->close(); }

<?php
require_once __DIR__.'/../config/session_bootstrap.php';
require_once __DIR__.'/../conexaoMain.php';
require_once __DIR__.'/../helpers/planejamento_alteracao_helper.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (empty($_SESSION['logado'])) throw new RuntimeException('Sessão inválida.',401);
    $conn=conectarBanco();
    if (!improov_usuario_eh_gestor_sidebar($conn)) throw new RuntimeException('Somente a gestão pode configurar a capacidade.',403);
    $_SESSION['planejamento_alteracao_csrf']??=bin2hex(random_bytes(32));
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if (!in_array($method,['GET','POST'],true)) throw new RuntimeException('Método não permitido.',405);
    if ($method==='POST') {
        $dados=json_decode(file_get_contents('php://input'),true);
        if (!is_array($dados)||!hash_equals($_SESSION['planejamento_alteracao_csrf'],(string)($dados['csrf_token']??''))) throw new RuntimeException('Recarregue a página para renovar a autorização.',403);
        $novo=flow_pac_config_validar($dados['configuracao']??[]);
        $elegiveis=array_map('intval',array_column(flow_pa_colaboradores($conn),'id'));
        if (array_diff(array_keys($novo['colaboradores']),$elegiveis)) throw new InvalidArgumentException('Override exige colaborador elegível de Alteração.');
        $conn->begin_transaction();
        try {
            $atual=flow_pac_config($conn,true);
            if (!hash_equals(hash('sha256',flow_planejamento_json($atual)),(string)($dados['fingerprint']??''))) throw new RuntimeException('A configuração mudou. Recarregue antes de salvar.',409);
            $params=flow_planejamento_json(['niveis'=>$novo['niveis'],'colaboradores'=>(object)$novo['colaboradores']]);
            $ator=(int)($_SESSION['idcolaborador']??0)?:null;
            flow_pa_query($conn,"UPDATE planejamento_capacidade_etapa SET capacidade_padrao=?,parametros_json=?,atualizado_por_colaborador_id=? WHERE codigo_etapa='ALTERACAO_PONTOS'",'dsi',[$novo['capacidade_padrao'],$params,$ator])->close();
            $conn->commit();
        } catch (Throwable $e) { $conn->rollback();throw $e; }
    }
    $config=flow_pac_config($conn);
    echo json_encode(['success'=>true,'configuracao'=>$config,'colaboradores'=>flow_pa_colaboradores($conn),'fingerprint'=>hash('sha256',flow_planejamento_json($config)),'csrf_token'=>$_SESSION['planejamento_alteracao_csrf']],JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(in_array($e->getCode(),[401,403,405,409],true)?$e->getCode():422);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}

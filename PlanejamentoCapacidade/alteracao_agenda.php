<?php
require_once __DIR__.'/../config/session_bootstrap.php';
require_once __DIR__.'/../conexaoMain.php';
require_once __DIR__.'/../helpers/planejamento_alteracao_helper.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if (empty($_SESSION['logado'])) throw new RuntimeException('Sessão inválida.',401);
    $conn=conectarBanco();
    if (!improov_usuario_eh_gestor_sidebar($conn)) throw new RuntimeException('Sem permissão para consultar capacidade global.',403);
    $inicio=(string)($_GET['inicio']??date('Y-m-d'));
    $fim=(string)($_GET['fim']??entregas_adicionar_dias_uteis($inicio,9));
    if (!entregas_valid_date($inicio)||!entregas_valid_date($fim)||(strtotime($fim)-strtotime($inicio))/86400>93) throw new InvalidArgumentException('Use um período de até três meses.');
    $pessoas=flow_pa_colaboradores($conn);$config=flow_pac_config($conn);
    echo json_encode(['success'=>true,'colaboradores'=>$pessoas,'dias'=>flow_pac_dias($inicio,$fim),'agenda'=>flow_pac_agenda($conn,0,$config,$pessoas,$inicio,$fim)],JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(in_array($e->getCode(),[401,403],true)?$e->getCode():422);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}

<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SESSION['logado'] ?? false) !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../helpers/entrega_imagem_etapa_helper.php';
$imagemId = filter_input(INPUT_GET, 'imagem_id', FILTER_VALIDATE_INT);
$entregaId = filter_input(INPUT_GET, 'entrega_id', FILTER_VALIDATE_INT);
if (!$imagemId || !$entregaId || $imagemId < 1 || $entregaId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Imagem e entrega obrigatórias.']);
    exit;
}
try {
    $stmt = $conn->prepare('SELECT e.id, e.obra_id, e.status_id, e.tipo_entrega, s.nome_status AS nome_etapa FROM entregas e JOIN status_imagem s ON s.idstatus=e.status_id WHERE e.id=?');
    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $entrega = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$entrega || !improov_usuario_pode_acessar_obra($conn, (int) $entrega['obra_id'])) {
        http_response_code($entrega ? 403 : 404);
        echo json_encode(['success' => false, 'error' => 'Entrega indisponível.']);
        exit;
    }
    $stmt = $conn->prepare('SELECT i.idimagens_cliente_obra AS imagem_id, i.tipo_imagem, i.status_id AS ciclo_atual FROM imagens_cliente_obra i WHERE i.idimagens_cliente_obra=? AND i.obra_id=? AND (EXISTS(SELECT 1 FROM entregas_itens ei WHERE ei.entrega_id=? AND ei.imagem_id=i.idimagens_cliente_obra) OR EXISTS(SELECT 1 FROM entregas_p00_versoes v WHERE v.entrega_id=? AND v.imagem_id=i.idimagens_cliente_obra))');
    $stmt->bind_param('iiii', $imagemId, $entrega['obra_id'], $entregaId, $entregaId);
    $stmt->execute();
    $imagem = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$imagem) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Imagem indisponível nesta entrega.']);
        exit;
    }
    $producao = flow_entrega_etapas_lote($conn, $entrega, [$imagem])[$imagemId];
    $e = $producao['atual'];
    echo json_encode(['success' => true, 'data' => ['nome_funcao' => $e['nome'], 'status' => $e['situacao_nome'],
        'nome_colaborador' => $e['responsavel_nome'], 'prazo' => $e['prazo'], 'producao' => $producao]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Entregas/get_imagem_funcao: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Não foi possível consultar a etapa.']);
}

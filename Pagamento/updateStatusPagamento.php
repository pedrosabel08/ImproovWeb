<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/pagamento_auth.php';
pagamento_require_gestor(true);
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/PagamentoService.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

$colaborador_id = isset($input['colaborador_id']) ? intval($input['colaborador_id']) : 0;
$ano = isset($input['ano']) ? intval($input['ano']) : 0;
$mes = isset($input['mes']) ? intval($input['mes']) : 0;
$status = isset($input['status']) ? trim($input['status']) : '';
$usuario_id = pagamento_current_user_id();

if (!$colaborador_id || !$ano || !$mes) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros obrigatórios ausentes ou inválidos']);
    exit;
}

$status = PagamentoService::normalizarStatus($status);
if ($status === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Status inválido']);
    exit;
}

$mes_ref = PagamentoService::competencia($mes, $ano);

if ($status === 'pago') {
    require_once __DIR__ . '/financeiro_v2.php';
    try {
        $items = financeiro_pagar($conn, $input, $usuario_id);
        pagamento_json(['success' => true, 'itens' => $items]);
    } catch (InvalidArgumentException | DomainException $e) {
        pagamento_json(['success' => false, 'error' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        error_log('Pagamento lote: ' . $e->getMessage());
        pagamento_json(['success' => false, 'error' => 'Falha ao registrar lote. Nenhum item alterado.'], 500);
    }
}
$conn->begin_transaction();
try {
    $paymentService = new PagamentoService($conn, $usuario_id);
    $pagamento_id = $paymentService->garantirPagamento($colaborador_id, $mes, $ano);

    // Status handling
    // handle new workflow statuses (normalized to lowercase earlier)
    if ($status === 'aguardando_retorno' || $status === 'pendente_envio') {
        // When sending the list for validation -> aguardando_retorno
        $upd = $conn->prepare("UPDATE pagamentos SET status = ?, data_envio_validacao = NOW() WHERE idpagamento = ?");
        $upd->bind_param('si', $status, $pagamento_id);
        $upd->execute();
        $upd->close();

        $ev = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
        $t = 'lista_enviada';
        $d = 'Lista enviada para validação / status: ' . $status;
        $ev->bind_param('issi', $pagamento_id, $t, $d, $usuario_id);
        $ev->execute();
        $ev->close();
    } elseif ($status === 'validado') {
        // mark that a valid response was received
        $upd = $conn->prepare("UPDATE pagamentos SET status = 'validado', data_resposta = NOW() WHERE idpagamento = ?");
        $upd->bind_param('i', $pagamento_id);
        $upd->execute();
        $upd->close();

        $ev = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
        $t = 'lista_respondida';
        $d = 'Lista respondida e validada';
        $ev->bind_param('issi', $pagamento_id, $t, $d, $usuario_id);
        $ev->execute();
        $ev->close();
    } elseif ($status === 'adendo_gerado') {
        // adendo generation
        $upd = $conn->prepare("UPDATE pagamentos SET status = 'adendo_gerado', data_geracao_adendo = NOW() WHERE idpagamento = ?");
        $upd->bind_param('i', $pagamento_id);
        $upd->execute();
        $upd->close();

        $ev = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
        $t = 'adendo_gerado';
        $d = 'Adendo gerado para este pagamento';
        $ev->bind_param('issi', $pagamento_id, $t, $d, $usuario_id);
        $ev->execute();
        $ev->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'pagamento_id' => $pagamento_id]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    error_log('Pagamento status update failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Não foi possível atualizar o status do pagamento.']);
}

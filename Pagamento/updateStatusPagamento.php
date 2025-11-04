<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';

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
$usuario_id = isset($input['usuario_id']) ? intval($input['usuario_id']) : null;

if (!$colaborador_id || !$ano || !$mes) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros obrigatórios ausentes ou inválidos']);
    exit;
}

// Normalize status to lowercase to support new workflow values while keeping backwards compatibility
$status_map = [
    'PENDENTE' => 'pendente_envio',
    'ENVIADO' => 'aguardando_retorno',
    'CONFIRMANDO' => 'aguardando_retorno',
    'PAGO' => 'pago'
];
if (isset($status_map[strtoupper($status)])) {
    $status = $status_map[strtoupper($status)];
} else {
    $status = strtolower($status);
}

// Allowed statuses for the new flow
$allowed = ['pendente_envio','aguardando_retorno','validado','adendo_gerado','pago'];
if (!in_array($status, $allowed)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Status inválido']);
    exit;
}

$mes_ref = sprintf('%04d-%02d', $ano, $mes);

$conn->begin_transaction();
try {
    // Ensure pagamentos row exists
    $stmt = $conn->prepare("SELECT idpagamento, status FROM pagamentos WHERE colaborador_id = ? AND mes_ref = ? FOR UPDATE");
    $stmt->bind_param('is', $colaborador_id, $mes_ref);
    $stmt->execute();
    $res = $stmt->get_result();
    $pagamento = $res->fetch_assoc();
    $stmt->close();

    if (!$pagamento) {
        // create pagamentos row with new initial status name
        $ins = $conn->prepare("INSERT INTO pagamentos (colaborador_id, mes_ref, status, criado_por) VALUES (?,?, 'pendente_envio', ?)");
        $ins->bind_param('isi', $colaborador_id, $mes_ref, $usuario_id);
        $ins->execute();
        $pagamento_id = $ins->insert_id;
        $ins->close();
        // log evento created
        $ev = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
        $t = 'created'; $d = 'Pagamento criado automaticamente';
        $ev->bind_param('issi', $pagamento_id, $t, $d, $usuario_id);
        $ev->execute();
        $ev->close();
    } else {
        $pagamento_id = (int)$pagamento['idpagamento'];
    }

    // Status handling
    // handle new workflow statuses (normalized to lowercase earlier)
    if ($status === 'aguardando_retorno' || $status === 'pendente_envio') {
        // When sending the list for validation -> aguardando_retorno
        $upd = $conn->prepare("UPDATE pagamentos SET status = ?, data_envio_validacao = NOW() WHERE idpagamento = ?");
        $upd->bind_param('si', $status, $pagamento_id);
        $upd->execute();
        $upd->close();

        $ev = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
        $t = 'lista_enviada'; $d = 'Lista enviada para validação / status: ' . $status;
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
        $t = 'lista_respondida'; $d = 'Lista respondida e validada';
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
        $t = 'adendo_gerado'; $d = 'Adendo gerado para este pagamento';
        $ev->bind_param('issi', $pagamento_id, $t, $d, $usuario_id);
        $ev->execute();
        $ev->close();
    } elseif ($status === 'pago') {
        // Collect all unpaid items for the month
        $idsFI = [];$idsAC = [];$idsAN = [];$valor_total = 0.0;
        // funcao_imagem by prazo
        $q = $conn->prepare("SELECT idfuncao_imagem, IFNULL(valor,0) AS valor FROM funcao_imagem WHERE colaborador_id = ? AND pagamento = 0 AND YEAR(prazo) = ? AND MONTH(prazo) = ?");
        $q->bind_param('iii', $colaborador_id, $ano, $mes);
        $q->execute(); $rs = $q->get_result();
        while ($row = $rs->fetch_assoc()) { $idsFI[] = (int)$row['idfuncao_imagem']; $valor_total += (float)$row['valor']; }
        $q->close();
        // acompanhamento by data
        $q = $conn->prepare("SELECT idacompanhamento, IFNULL(valor,0) AS valor FROM acompanhamento WHERE colaborador_id = ? AND pagamento = 0 AND YEAR(data) = ? AND MONTH(data) = ?");
        $q->bind_param('iii', $colaborador_id, $ano, $mes);
        $q->execute(); $rs = $q->get_result();
        while ($row = $rs->fetch_assoc()) { $idsAC[] = (int)$row['idacompanhamento']; $valor_total += (float)$row['valor']; }
        $q->close();
        // animacao by data_anima
        $q = $conn->prepare("SELECT idanimacao, IFNULL(valor,0) AS valor FROM animacao WHERE colaborador_id = ? AND pagamento = 0 AND YEAR(data_anima) = ? AND MONTH(data_anima) = ?");
        $q->bind_param('iii', $colaborador_id, $ano, $mes);
        $q->execute(); $rs = $q->get_result();
        while ($row = $rs->fetch_assoc()) { $idsAN[] = (int)$row['idanimacao']; $valor_total += (float)$row['valor']; }
        $q->close();

        // Update origin tables: mark as paid
        if (!empty($idsFI)) {
            $ids = implode(',', array_map('intval', $idsFI));
            $conn->query("UPDATE funcao_imagem SET pagamento = 1, data_pagamento = NOW() WHERE idfuncao_imagem IN ($ids)");
        }
        if (!empty($idsAC)) {
            $ids = implode(',', array_map('intval', $idsAC));
            $conn->query("UPDATE acompanhamento SET pagamento = 1, data_pagamento = NOW() WHERE idacompanhamento IN ($ids)");
        }
        if (!empty($idsAN)) {
            $ids = implode(',', array_map('intval', $idsAN));
            $conn->query("UPDATE animacao SET pagamento = 1, data_pagamento = NOW() WHERE idanimacao IN ($ids)");
        }

        // Insert items rows
        $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor) VALUES (?,?,?,?)");
        foreach ($idsFI as $id) { $o='funcao_imagem'; $v=null; $insItem->bind_param('isid', $pagamento_id, $o, $id, $v); $insItem->execute(); }
        foreach ($idsAC as $id) { $o='acompanhamento'; $v=null; $insItem->bind_param('isid', $pagamento_id, $o, $id, $v); $insItem->execute(); }
        foreach ($idsAN as $id) { $o='animacao'; $v=null; $insItem->bind_param('isid', $pagamento_id, $o, $id, $v); $insItem->execute(); }
        $insItem->close();

    // Update aggregate pagamento (use new lowercase status and set data_pagamento)
    $upd = $conn->prepare("UPDATE pagamentos SET status='pago', valor_total = ?, data_pagamento = NOW(), pago_em = NOW() WHERE idpagamento = ?");
    $upd->bind_param('di', $valor_total, $pagamento_id);
        $upd->execute();
        $upd->close();

        // Log evento
        $ev = $conn->prepare("INSERT INTO pagamento_eventos (pagamento_id, tipo, descricao, usuario_id) VALUES (?,?,?,?)");
        $t = 'pago'; $d = 'Pagamento marcado como PAGO e itens confirmados (' . (count($idsFI)+count($idsAC)+count($idsAN)) . ' itens)';
        $ev->bind_param('issi', $pagamento_id, $t, $d, $usuario_id);
        $ev->execute();
        $ev->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'pagamento_id' => $pagamento_id]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

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
    // funcao_imagem by prazo (also fetch imagem status_id to detect Finalização Parcial)
    // include funcao_id and imagem_id and detect if a 'Pré-Finalização' exists for the same imagem
    $q = $conn->prepare("SELECT fi.idfuncao_imagem, IFNULL(fi.valor,0) AS valor, i.status_id, fi.funcao_id, fi.imagem_id, (SELECT COUNT(1) FROM funcao_imagem fi_sub JOIN funcao f_sub ON fi_sub.funcao_id = f_sub.idfuncao WHERE fi_sub.imagem_id = fi.imagem_id AND f_sub.nome_funcao = 'Pré-Finalização') AS has_prefinalizacao FROM funcao_imagem fi LEFT JOIN imagens_cliente_obra i ON fi.imagem_id = i.idimagens_cliente_obra WHERE fi.colaborador_id = ? AND fi.pagamento = 0 AND YEAR(fi.prazo) = ? AND MONTH(fi.prazo) = ?");
    $q->bind_param('iii', $colaborador_id, $ano, $mes);
    $q->execute(); $rs = $q->get_result();
    while ($row = $rs->fetch_assoc()) { $idsFI[] = ['id' => (int)$row['idfuncao_imagem'], 'valor' => (float)$row['valor'], 'status_id' => isset($row['status_id']) ? intval($row['status_id']) : null, 'funcao_id' => isset($row['funcao_id']) ? intval($row['funcao_id']) : null, 'imagem_id' => isset($row['imagem_id']) ? intval($row['imagem_id']) : null, 'has_prefinalizacao' => isset($row['has_prefinalizacao']) ? intval($row['has_prefinalizacao']) : 0 ]; $valor_total += (float)$row['valor']; }
    $q->close();
    // acompanhamento by data
    $q = $conn->prepare("SELECT idacompanhamento, IFNULL(valor,0) AS valor FROM acompanhamento WHERE colaborador_id = ? AND pagamento = 0 AND YEAR(data) = ? AND MONTH(data) = ?");
    $q->bind_param('iii', $colaborador_id, $ano, $mes);
    $q->execute(); $rs = $q->get_result();
    while ($row = $rs->fetch_assoc()) { $idsAC[] = ['id' => (int)$row['idacompanhamento'], 'valor' => (float)$row['valor']]; $valor_total += (float)$row['valor']; }
    $q->close();
    // animacao by data_anima
    $q = $conn->prepare("SELECT idanimacao, IFNULL(valor,0) AS valor FROM animacao WHERE colaborador_id = ? AND pagamento = 0 AND YEAR(data_anima) = ? AND MONTH(data_anima) = ?");
    $q->bind_param('iii', $colaborador_id, $ano, $mes);
    $q->execute(); $rs = $q->get_result();
    while ($row = $rs->fetch_assoc()) { $idsAN[] = ['id' => (int)$row['idanimacao'], 'valor' => (float)$row['valor']]; $valor_total += (float)$row['valor']; }
    $q->close();

        // Update origin tables: mark as paid
        if (!empty($idsFI)) {
            $ids = implode(',', array_map(function($x){return intval($x['id']);}, $idsFI));
            $conn->query("UPDATE funcao_imagem SET pagamento = 1, data_pagamento = NOW() WHERE idfuncao_imagem IN ($ids)");
        }
        if (!empty($idsAC)) {
            $ids = implode(',', array_map(function($x){return intval($x['id']);}, $idsAC));
            $conn->query("UPDATE acompanhamento SET pagamento = 1, data_pagamento = NOW() WHERE idacompanhamento IN ($ids)");
        }
        if (!empty($idsAN)) {
            $ids = implode(',', array_map(function($x){return intval($x['id']);}, $idsAN));
            $conn->query("UPDATE animacao SET pagamento = 1, data_pagamento = NOW() WHERE idanimacao IN ($ids)");
        }

        // Insert items rows with valor and observacao (if applicable)
        // Some environments might not yet have the 'observacao' column in pagamento_itens.
        // Detect column existence and prepare the appropriate INSERT to avoid failing the whole transaction.
        $hasObservacao = false;
        $colChk = $conn->query("SHOW COLUMNS FROM pagamento_itens LIKE 'observacao'");
        if ($colChk && $colChk->num_rows > 0) $hasObservacao = true;

        if ($hasObservacao) {
            $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor, observacao) VALUES (?,?,?,?,?)");
            if (!$insItem) throw new Exception('Prepare failed (pagamento_itens with observacao): ' . $conn->error);
            foreach ($idsFI as $item) {
                $o = 'funcao_imagem';
                $id = $item['id'];
                $v = $item['valor'];
                $isFinalizacaoFunc = (isset($item['funcao_id']) && intval($item['funcao_id']) === 4);
                $hasPrefinal = (isset($item['has_prefinalizacao']) && intval($item['has_prefinalizacao']) > 0);
                $obs = ($isFinalizacaoFunc && ( (isset($item['status_id']) && intval($item['status_id']) === 1) || $hasPrefinal )) ? 'Finalização Parcial' : null;
                $insItem->bind_param('isids', $pagamento_id, $o, $id, $v, $obs);
                $insItem->execute();
            }
            foreach ($idsAC as $item) {
                $o = 'acompanhamento';
                $id = $item['id'];
                $v = $item['valor'];
                $obs = null;
                $insItem->bind_param('isids', $pagamento_id, $o, $id, $v, $obs);
                $insItem->execute();
            }
            foreach ($idsAN as $item) {
                $o = 'animacao';
                $id = $item['id'];
                $v = $item['valor'];
                $obs = null;
                $insItem->bind_param('isids', $pagamento_id, $o, $id, $v, $obs);
                $insItem->execute();
            }
            $insItem->close();
        } else {
            // Fallback: table has no 'observacao' column; insert without it
            $insItem = $conn->prepare("INSERT INTO pagamento_itens (pagamento_id, origem, origem_id, valor) VALUES (?,?,?,?)");
            if (!$insItem) throw new Exception('Prepare failed (pagamento_itens without observacao): ' . $conn->error);
            foreach ($idsFI as $item) {
                $o = 'funcao_imagem';
                $id = $item['id'];
                $v = $item['valor'];
                $insItem->bind_param('isid', $pagamento_id, $o, $id, $v);
                $insItem->execute();
            }
            foreach ($idsAC as $item) {
                $o = 'acompanhamento';
                $id = $item['id'];
                $v = $item['valor'];
                $insItem->bind_param('isid', $pagamento_id, $o, $id, $v);
                $insItem->execute();
            }
            foreach ($idsAN as $item) {
                $o = 'animacao';
                $id = $item['id'];
                $v = $item['valor'];
                $insItem->bind_param('isid', $pagamento_id, $o, $id, $v);
                $insItem->execute();
            }
            $insItem->close();
        }

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

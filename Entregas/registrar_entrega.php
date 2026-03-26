<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['entrega_id'], $input['imagens_entregues'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

$entrega_id = intval($input['entrega_id']);
$imagens_entregues = $input['imagens_entregues'];

try {
    $conn->begin_transaction();

    $hoje = date('Y-m-d');

    // Atualiza o status de cada item individualmente e coleta imagem_id processadas
    $processed_image_ids = array();
    if (!empty($imagens_entregues)) {
        $stmtSelect = $conn->prepare("SELECT ei.id, ei.imagem_id, e.data_prevista 
                                      FROM entregas_itens ei 
                                      JOIN entregas e ON ei.entrega_id = e.id 
                                      WHERE ei.id = ? AND ei.entrega_id = ?");
        $stmtUpdate = $conn->prepare("UPDATE entregas_itens SET status=?, data_entregue=NOW() WHERE id=?");

        foreach ($imagens_entregues as $item_id) {
            $item_id = intval($item_id);
            $stmtSelect->bind_param('ii', $item_id, $entrega_id);
            $stmtSelect->execute();
            $res = $stmtSelect->get_result()->fetch_assoc();
            if (!$res) continue;

            $status_item = ($hoje <= $res['data_prevista']) ? 'Entregue no prazo' : 'Entregue com atraso';
            $stmtUpdate->bind_param('si', $status_item, $item_id);
            $stmtUpdate->execute();

            if (isset($res['imagem_id']) && !empty($res['imagem_id'])) {
                $processed_image_ids[] = intval($res['imagem_id']);
            }
        }
        if ($stmtSelect) $stmtSelect->close();
        if ($stmtUpdate) $stmtUpdate->close();
    }

    // Verificar total de imagens, quantas já estão entregues e obter obra_id/data_prevista
    // Use agregação para evitar ONLY_FULL_GROUP_BY: data_prevista/obra_id são constantes por entrega, então
    // MAX() retorna o valor correto sem precisar de GROUP BY.
    $stmt = $conn->prepare("SELECT COUNT(*) AS total, 
                    SUM(CASE WHEN ei.status LIKE 'Entregue%' THEN 1 ELSE 0 END) AS entregues,
                    MAX(e.data_prevista) AS data_prevista,
                    MAX(e.obra_id) AS obra_id
                FROM entregas_itens ei
                JOIN entregas e ON ei.entrega_id = e.id
                WHERE ei.entrega_id=?");
    $stmt->bind_param('i', $entrega_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    $total = intval($res['total']);
    $entregues = intval($res['entregues']);
    $data_prevista = $res['data_prevista'];
    $obra_id = isset($res['obra_id']) ? intval($res['obra_id']) : null;

    // Determinar novo status da entrega
    if ($entregues === 0) {
        $novo_status = 'Pendente';
    } elseif ($entregues < $total) {
        $novo_status = 'Parcial';
    } elseif ($entregues === $total && $hoje < $data_prevista) {
        $novo_status = 'Entrega antecipada';
    } elseif ($entregues === $total && $hoje > $data_prevista) {
        $novo_status = 'Entregue com atraso';
    } elseif ($entregues === $total && $hoje == $data_prevista) {
        $novo_status = 'Entregue no prazo';
    } else {
        $novo_status = 'Concluída';
    }


    // Fetch previous status (and status_id) so we can detect transitions
    $old_status = null;
    $old_status_id = null;
    $stmtOld = $conn->prepare("SELECT status, status_id FROM entregas WHERE id = ?");
    $stmtOld->bind_param('i', $entrega_id);
    $stmtOld->execute();
    $rOld = $stmtOld->get_result()->fetch_assoc();
    if ($rOld) {
        $old_status = $rOld['status'];
        $old_status_id = isset($rOld['status_id']) ? intval($rOld['status_id']) : null;
    }

    // If certain transitions happen, also insert an entry into acompanhamento_email
    // Compute next ordem for this obra (simple MAX+1)
    $next_ordem = 1;
    if ($obra_id) {
        $stmtOrdem = $conn->prepare("SELECT IFNULL(MAX(ordem),0)+1 AS next_ordem FROM acompanhamento_email WHERE obra_id = ?");
        $stmtOrdem->bind_param('i', $obra_id);
        $stmtOrdem->execute();
        $rOrd = $stmtOrdem->get_result()->fetch_assoc();
        if ($rOrd && isset($rOrd['next_ordem'])) $next_ordem = intval($rOrd['next_ordem']);
        $stmtOrdem->close();
    }

    // Get obra nomenclatura for description
    $obra_nome = '';
    if ($obra_id) {
        $stmtObra = $conn->prepare("SELECT nomenclatura FROM obra WHERE idobra = ? LIMIT 1");
        $stmtObra->bind_param('i', $obra_id);
        $stmtObra->execute();
        $rObra = $stmtObra->get_result()->fetch_assoc();
        if ($rObra) $obra_nome = $rObra['nomenclatura'];
        $stmtObra->close();
    }

    // Calcular contagem total por obra (todas as entregas da obra)
    $total_obra = 0;
    $entregues_obra = 0;
    if ($obra_id) {
        $stmtObraCounts = $conn->prepare("SELECT COUNT(*) AS total_obra, SUM(CASE WHEN ei.status LIKE 'Entregue%' THEN 1 ELSE 0 END) AS entregues_obra
            FROM entregas_itens ei
            JOIN entregas e ON ei.entrega_id = e.id
            WHERE e.obra_id = ?");
        $stmtObraCounts->bind_param('i', $obra_id);
        $stmtObraCounts->execute();
        $rCounts = $stmtObraCounts->get_result()->fetch_assoc();
        if ($rCounts) {
            $total_obra = intval($rCounts['total_obra']);
            $entregues_obra = intval($rCounts['entregues_obra']);
        }
        $stmtObraCounts->close();
    }


    // Prepare insert into acompanhamento_email
    $insertAcompStmt = $conn->prepare("INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data, ordem, entrega_id, tipo, status) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");

    // Evento de entrega concluída
    $concluido_set = array('Entregue no prazo', 'Entregue com atraso', 'Entrega antecipada');
    if (in_array($novo_status, $concluido_set) && !in_array($old_status, $concluido_set)) {
        $assunto = 'Entrega ' . $status_nome . ' concluída';
        $tipo = 'entrega';
        $status_acomp = 'pendente';
        $data_today = date('Y-m-d');
        if ($insertAcompStmt) {
            $insertAcompStmt->bind_param('issiiss', $obra_id, $assunto, $data_today, $next_ordem, $entrega_id, $tipo, $status_acomp);
            $insertAcompStmt->execute();
        }
    }

    if ($insertAcompStmt) $insertAcompStmt->close();

    // Atualizar status da entrega (inclui status_id quando disponível)
    if ($novo_status_id !== null) {
        $stmt = $conn->prepare("UPDATE entregas SET status=?, status_id=?, data_conclusao=NOW() WHERE id=?");
        $stmt->bind_param('sii', $novo_status, $novo_status_id, $entrega_id);
    } else {
        $stmt = $conn->prepare("UPDATE entregas SET status=?, data_conclusao=NOW() WHERE id=?");
        $stmt->bind_param('si', $novo_status, $entrega_id);
    }
    $stmt->execute();

    // Atualizar substatus_id nas imagens vinculadas aos itens desta entrega.
    // Regra: se a imagem tiver status_id = 6 (EF) ou 1 => substatus_id = 9 (DRV), senão => substatus_id = 6 (RVW).
    if (!empty($processed_image_ids)) {
        // Busca o status_id atual de cada imagem para determinar o substatus correto
        $inIds = implode(',', $processed_image_ids); // safe: all cast to intval above
        $resImgStatus = $conn->query("SELECT idimagens_cliente_obra, status_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra IN ($inIds)");
        $imgStatusMap = [];
        if ($resImgStatus) {
            while ($rImg = $resImgStatus->fetch_assoc()) {
                $imgStatusMap[intval($rImg['idimagens_cliente_obra'])] = intval($rImg['status_id']);
            }
        }

        $stmtUpdateImg = $conn->prepare("UPDATE imagens_cliente_obra SET substatus_id = ? WHERE idimagens_cliente_obra = ?");
        if ($stmtUpdateImg) {
            foreach ($processed_image_ids as $img_id) {
                $img_status_id = $imgStatusMap[$img_id] ?? 0;
                $substatus_to_set = ($img_status_id === 6 || $img_status_id === 1) ? 9 : 6;
                $stmtUpdateImg->bind_param('ii', $substatus_to_set, $img_id);
                $stmtUpdateImg->execute();
            }
            $stmtUpdateImg->close();
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'novo_status' => $novo_status,
        'total_imagens' => $total,
        'entregues' => $entregues
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

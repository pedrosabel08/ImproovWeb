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

    // Atualiza o status de cada item individualmente
    if (!empty($imagens_entregues)) {
        $stmtSelect = $conn->prepare("SELECT ei.id, e.data_prevista 
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
        }
    }

    // Verificar total de imagens e quantas já estão entregues
    $stmt = $conn->prepare("SELECT COUNT(*) AS total, 
                                   SUM(CASE WHEN ei.status LIKE 'Entregue%' THEN 1 ELSE 0 END) AS entregues,
                                   e.data_prevista
                            FROM entregas_itens ei
                            JOIN entregas e ON ei.entrega_id = e.id
                            WHERE ei.entrega_id=?");
    $stmt->bind_param('i', $entrega_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    $total = intval($res['total']);
    $entregues = intval($res['entregues']);
    $data_prevista = $res['data_prevista'];

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


    // Atualizar status da entrega
    $stmt = $conn->prepare("UPDATE entregas SET status=?, data_conclusao=NOW() WHERE id=?");
    $stmt->bind_param('si', $novo_status, $entrega_id);
    $stmt->execute();

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

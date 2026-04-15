<?php
session_start();
include_once __DIR__ . '/../conexao.php';
require_once 'ws_notify.php';

header('Content-Type: application/json');

// Apenas colaboradores autorizados podem reordenar
$idcolab = (int) ($_SESSION['idcolaborador'] ?? 0);
if (!in_array($idcolab, [9, 21])) {
    echo json_encode(['success' => false, 'message' => 'Sem permissão.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'reorder') {
    // Recebe: [{ id: 1, prioridade: 1 }, { id: 2, prioridade: 2 }, ...]
    $items = $data['items'] ?? [];
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum item recebido.']);
        exit;
    }
    $stmt = $conn->prepare("UPDATE pos_producao SET prioridade = ? WHERE idpos_producao = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro no prepare: ' . $conn->error]);
        exit;
    }
    $conn->begin_transaction();
    try {
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            $pri = (int) ($item['prioridade'] ?? 0);
            if ($id <= 0)
                continue;
            $stmt->bind_param('ii', $pri, $id);
            $stmt->execute();
        }
        $conn->commit();
        notifyPosProducaoUpdate();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    $stmt->close();

} elseif ($action === 'urgente') {
    // Recebe: { id: 1, flag_urgente: 1 }
    $id = (int) ($data['id'] ?? 0);
    $flag = (int) ($data['flag_urgente'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        exit;
    }
    // Se marcou urgente (flag=1), sobe para prioridade 0 (à frente de todos).
    // Se desmarcou, volta o valor calculado (MAX da obra + 1 gráfico; aqui apenas remove o topo).
    $stmt = $conn->prepare("UPDATE pos_producao SET flag_urgente = ? WHERE idpos_producao = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro no prepare: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('ii', $flag, $id);
    if ($stmt->execute()) {
        notifyPosProducaoUpdate();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar.']);
    }
    $stmt->close();

} elseif ($action === 'reorder_obra') {
    // Recebe: [{ obra_id: 1, prioridade: 1 }, ...]
    $items = $data['items'] ?? [];
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Nenhum item recebido.']);
        exit;
    }
    $stmt = $conn->prepare(
        "INSERT INTO pos_prioridade_obra (obra_id, prioridade)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE prioridade = VALUES(prioridade)"
    );
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Erro no prepare: ' . $conn->error]);
        exit;
    }
    $conn->begin_transaction();
    try {
        foreach ($items as $item) {
            $obra_id = (int)($item['obra_id'] ?? 0);
            $pri     = (int)($item['prioridade'] ?? 0);
            if ($obra_id <= 0) continue;
            $stmt->bind_param('ii', $obra_id, $pri);
            $stmt->execute();
        }
        $conn->commit();
        notifyPosProducaoUpdate();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    $stmt->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
}

$conn->close();

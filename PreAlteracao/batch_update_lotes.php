<?php
// PreAlteracao/batch_update_lotes.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/pre_alt_helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nao autenticado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload invalido.']);
    exit;
}

$loteIds = array_values(array_unique(array_filter(array_map('intval', $data['lote_ids'] ?? []))));
$action = strtolower(trim((string) ($data['action'] ?? '')));
$value = $data['value'] ?? null;
$observacao = trim((string) ($data['observacao'] ?? 'Atualizacao em lote.'));

if (!$loteIds || $action === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Informe os lotes e a acao.']);
    exit;
}

pre_alt_ensure_schema($conn);

$allowedPrioridades = ['BAIXA', 'NORMAL', 'ALTA', 'CRITICA'];
$allowedStatus = ['EM_TRIAGEM', 'AGUARDANDO_CLIENTE', 'PRONTO_PLANEJAMENTO', 'PLANEJADO'];

$field = null;
$newValue = null;
$event = 'ALTERACAO_LOTE';

if ($action === 'responsavel') {
    $field = 'responsavel_id';
    $newValue = is_numeric($value) && (int) $value > 0 ? (int) $value : null;
} elseif ($action === 'prazo') {
    $field = 'prazo';
    $newValue = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    if ($newValue === null) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Prazo invalido.']);
        exit;
    }
} elseif ($action === 'prioridade') {
    $field = 'prioridade';
    $newValue = strtoupper(trim((string) $value));
    if (!in_array($newValue, $allowedPrioridades, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Prioridade invalida.']);
        exit;
    }
} elseif ($action === 'status') {
    $field = 'status';
    $newValue = strtoupper(trim((string) $value));
    $event = 'ALTERACAO_STATUS';
    if (!in_array($newValue, $allowedStatus, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Etapa invalida.']);
        exit;
    }
} elseif ($action === 'concluir') {
    $field = 'status';
    $newValue = 'PRONTO_PLANEJAMENTO';
    $event = 'CONCLUSAO_TRIAGEM';
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Acao invalida.']);
    exit;
}

$batchId = pre_alt_batch_id();
$updated = 0;

$select = $conn->prepare("SELECT id, {$field} AS current_value FROM pre_alt_lote WHERE id = ? AND status <> 'CANCELADO' LIMIT 1");
$updateSql = $field === 'responsavel_id'
    ? "UPDATE pre_alt_lote SET responsavel_id = ?, updated_at = NOW() WHERE id = ?"
    : "UPDATE pre_alt_lote SET {$field} = ?, updated_at = NOW() WHERE id = ?";
$update = $conn->prepare($updateSql);

if (!$select || !$update) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

foreach ($loteIds as $loteId) {
    $select->bind_param('i', $loteId);
    $select->execute();
    $row = $select->get_result()->fetch_assoc();
    if (!$row) {
        continue;
    }

    $oldValue = $row['current_value'];
    if ((string) ($oldValue ?? '') === (string) ($newValue ?? '')) {
        continue;
    }

    if ($field === 'responsavel_id') {
        $update->bind_param('ii', $newValue, $loteId);
    } else {
        $update->bind_param('si', $newValue, $loteId);
    }

    if ($update->execute()) {
        $updated += 1;
        pre_alt_registrar_historico(
            $conn,
            $loteId,
            $event,
            $field,
            $oldValue,
            $newValue,
            $observacao,
            null,
            $batchId,
            ['total_lotes_solicitados' => count($loteIds)]
        );
    }
}

$select->close();
$update->close();

echo json_encode([
    'success' => true,
    'updated' => $updated,
    'batch_id' => $batchId,
]);

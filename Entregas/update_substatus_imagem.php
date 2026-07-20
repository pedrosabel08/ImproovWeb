<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/p00_delivery_helpers.php';
require_once __DIR__ . '/review_cobranca_lib.php';
require_once __DIR__ . '/../Fotografico/fotografico_service.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nao autenticado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (
    !is_array($data)
    || !isset($data['imagem_ids'], $data['substatus_id'])
    || !is_array($data['imagem_ids'])
    || !is_numeric($data['substatus_id'])
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parametros invalidos.']);
    exit;
}

$imagemIds = array_values(array_unique(array_filter(
    array_map('intval', $data['imagem_ids']),
    static fn(int $id): bool => $id > 0
)));
$substatusId = (int) $data['substatus_id'];
if ($imagemIds === []) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nenhuma imagem informada.']);
    exit;
}

$stmt = $conn->prepare('SELECT id FROM substatus_imagem WHERE id = ?');
$stmt->bind_param('i', $substatusId);
$stmt->execute();
$validStatus = (bool) $stmt->get_result()->fetch_row();
$stmt->close();
if (!$validStatus) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Substatus invalido.']);
    exit;
}

$idsList = implode(',', $imagemIds);
$conn->begin_transaction();
try {
    $oldSubstatus = [];
    $result = $conn->query(
        "SELECT idimagens_cliente_obra, obra_id, substatus_id
           FROM imagens_cliente_obra
          WHERE idimagens_cliente_obra IN ($idsList)
          FOR UPDATE"
    );
    if (!$result || $result->num_rows !== count($imagemIds)) {
        throw new RuntimeException('Uma ou mais imagens nao foram encontradas.');
    }
    while ($row = $result->fetch_assoc()) {
        if (!improov_usuario_pode_acessar_obra($conn, (int) $row['obra_id'])) {
            throw new RuntimeException('Sem acesso a uma das obras selecionadas.');
        }
        $oldSubstatus[(int) $row['idimagens_cliente_obra']] = $row['substatus_id'] !== null
            ? (int) $row['substatus_id']
            : null;
    }

    $stmt = $conn->prepare(
        "UPDATE imagens_cliente_obra SET substatus_id = ? WHERE idimagens_cliente_obra IN ($idsList)"
    );
    $stmt->bind_param('i', $substatusId);
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($substatusId === FOTOGRAFICO_TODO_SUBSTATUS_ID) {
        foreach ($imagemIds as $imagemId) {
            improov_p00_register_handoff_for_image($conn, $imagemId);
        }
    }

    foreach ($imagemIds as $imagemId) {
        entregas_review_sync_standard_batch_state($conn, $imagemId, $substatusId);
        entregas_review_sync_p00_batch_state($conn, $imagemId, null, $substatusId);
        fotografico_sync_imagem_substatus(
            $conn,
            $imagemId,
            $oldSubstatus[$imagemId] ?? null,
            $substatusId,
            fotografico_actor_id(),
            'Entregas/update_substatus_imagem.php'
        );
    }

    $conn->commit();
    echo json_encode(['success' => true, 'affected' => $affected], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

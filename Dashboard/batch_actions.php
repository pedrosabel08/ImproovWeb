<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/../Entregas/p00_delivery_helpers.php';
require_once __DIR__ . '/../Entregas/review_cobranca_lib.php';
require_once __DIR__ . '/../helpers/pendencias_operacionais_helper.php';
require_once __DIR__ . '/../Fotografico/fotografico_service.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Nao autenticado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || !isset($data['ids'], $data['campos']) || !is_array($data['ids']) || !is_array($data['campos'])) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados invalidos']);
    exit;
}

$ids = array_values(array_unique(array_filter(
    array_map('intval', $data['ids']),
    static fn(int $id): bool => $id > 0
)));
$camposPermitidos = ['substatus_id', 'status_id', 'prazo', 'subtipo_id'];
$campos = [];
foreach ($data['campos'] as $coluna => $valor) {
    if (!in_array($coluna, $camposPermitidos, true)) {
        continue;
    }
    $campos[$coluna] = $coluna === 'prazo' ? trim((string) $valor) : (int) $valor;
}
$holdJustificativa = trim((string) ($data['hold_justificativa'] ?? ''));

if ($ids === [] || $campos === []) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Nenhuma imagem ou campo permitido foi informado']);
    exit;
}

$destinoHold = isset($campos['substatus_id']) && (int) $campos['substatus_id'] === FOTOGRAFICO_HOLD_SUBSTATUS_ID;
if ($destinoHold && $holdJustificativa === '') {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Justificativa de HOLD e obrigatoria']);
    exit;
}

$idsList = implode(',', $ids);
mysqli_begin_transaction($conn);

try {
    $oldSubstatus = [];
    $resRows = $conn->query(
        "SELECT idimagens_cliente_obra, obra_id, substatus_id
           FROM imagens_cliente_obra
          WHERE idimagens_cliente_obra IN ($idsList)
          FOR UPDATE"
    );
    if (!$resRows || $resRows->num_rows !== count($ids)) {
        throw new RuntimeException('Uma ou mais imagens nao foram encontradas.');
    }
    while ($row = $resRows->fetch_assoc()) {
        $obraId = (int) $row['obra_id'];
        if (!improov_usuario_pode_acessar_obra($conn, $obraId)) {
            throw new RuntimeException('Sem acesso a uma das obras selecionadas.');
        }
        $oldSubstatus[(int) $row['idimagens_cliente_obra']] = $row['substatus_id'] !== null
            ? (int) $row['substatus_id']
            : null;
    }

    $set = [];
    $types = '';
    $params = [];
    foreach ($campos as $coluna => $valor) {
        $set[] = "`$coluna` = ?";
        $types .= $coluna === 'prazo' ? 's' : 'i';
        $params[] = $valor;
    }
    $stmtUpdate = $conn->prepare(
        'UPDATE imagens_cliente_obra SET ' . implode(', ', $set) . " WHERE idimagens_cliente_obra IN ($idsList)"
    );
    if (!$stmtUpdate) {
        throw new RuntimeException($conn->error);
    }
    $stmtUpdate->bind_param($types, ...$params);
    if (!$stmtUpdate->execute()) {
        throw new RuntimeException($stmtUpdate->error);
    }
    $stmtUpdate->close();

    if ($destinoHold) {
        $stmtHold = $conn->prepare(
            'INSERT INTO status_hold (justificativa, imagem_id, obra_id) VALUES (?, ?, ?)'
        );
        if (!$stmtHold) {
            throw new RuntimeException($conn->error);
        }
        $resRows->data_seek(0);
        while ($row = $resRows->fetch_assoc()) {
            $imagemId = (int) $row['idimagens_cliente_obra'];
            $obraId = (int) $row['obra_id'];
            $stmtHold->bind_param('sii', $holdJustificativa, $imagemId, $obraId);
            if (!$stmtHold->execute()) {
                throw new RuntimeException($stmtHold->error);
            }
        }
        $stmtHold->close();
    }

    if (isset($campos['substatus_id']) && (int) $campos['substatus_id'] === FOTOGRAFICO_TODO_SUBSTATUS_ID) {
        foreach ($ids as $imagemId) {
            improov_p00_register_handoff_for_image($conn, $imagemId);
        }
    }

    if (isset($campos['substatus_id'])) {
        $novoSubstatusId = (int) $campos['substatus_id'];
        foreach ($ids as $imagemId) {
            entregas_review_sync_p00_batch_state($conn, $imagemId, null, $novoSubstatusId);
            fotografico_sync_imagem_substatus(
                $conn,
                $imagemId,
                $oldSubstatus[$imagemId] ?? null,
                $novoSubstatusId,
                fotografico_actor_id(),
                'Dashboard/batch_actions.php'
            );
        }
    }

    if (isset($campos['substatus_id']) || isset($campos['subtipo_id'])) {
        foreach ($ids as $imagemId) {
            pendencias_operacionais_sync_image_checklist($conn, $imagemId);
        }
    }

    mysqli_commit($conn);
    echo json_encode(['sucesso' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();

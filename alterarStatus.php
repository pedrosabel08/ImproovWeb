<?php
// alterarStatus.php

include 'conexao.php';
require_once __DIR__ . '/Entregas/p00_delivery_helpers.php';
require_once __DIR__ . '/Entregas/review_cobranca_lib.php';
require_once __DIR__ . '/helpers/pendencias_operacionais_helper.php';
require_once __DIR__ . '/Fotografico/fotografico_service.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['imagem_id']) || (int) $_POST['imagem_id'] <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Imagem não informada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$imagemId = (int) $_POST['imagem_id'];
$hasSubstatus = array_key_exists('substatus_id', $_POST) || array_key_exists('status_id', $_POST);
$substatusId = $hasSubstatus
    ? (int) ($_POST['substatus_id'] ?? $_POST['status_id'])
    : null;
$hasEtapa = array_key_exists('etapa_id', $_POST);
$etapaId = $hasEtapa ? (int) $_POST['etapa_id'] : null;
$hasPrazo = array_key_exists('prazo', $_POST);
$prazo = $hasPrazo ? trim((string) $_POST['prazo']) : null;
$hasSubtipo = array_key_exists('subtipo_id', $_POST);
$subtipoId = $hasSubtipo && trim((string) $_POST['subtipo_id']) !== ''
    ? (int) $_POST['subtipo_id']
    : null;
$holdJustificativa = trim((string) ($_POST['hold_justificativa'] ?? ''));

if (!$hasSubstatus && !$hasEtapa && !$hasPrazo && !$hasSubtipo) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Nenhum campo para atualizar foi informado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($hasSubstatus && $substatusId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Substatus inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($hasEtapa && $etapaId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Etapa inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($hasPrazo && $prazo !== '') {
    $dataPrazo = DateTimeImmutable::createFromFormat('!Y-m-d', $prazo);
    if (!$dataPrazo || $dataPrazo->format('Y-m-d') !== $prazo) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Prazo inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($hasSubtipo && $subtipoId !== null && $subtipoId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Subtipo inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($hasSubstatus && $substatusId === FOTOGRAFICO_HOLD_SUBSTATUS_ID && $holdJustificativa === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Justificativa de HOLD é obrigatória.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->begin_transaction();

try {
    $stmtImagem = $conn->prepare(
        'SELECT obra_id, substatus_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ? FOR UPDATE'
    );
    if (!$stmtImagem) {
        throw new RuntimeException($conn->error);
    }
    $stmtImagem->bind_param('i', $imagemId);
    if (!$stmtImagem->execute()) {
        throw new RuntimeException($stmtImagem->error);
    }
    $imagem = $stmtImagem->get_result()->fetch_assoc();
    $stmtImagem->close();

    if (!$imagem) {
        throw new RuntimeException('Imagem não encontrada.');
    }

    $substatusAnterior = $imagem['substatus_id'] !== null ? (int) $imagem['substatus_id'] : null;
    $set = [];
    $types = '';
    $params = [];

    if ($hasEtapa) {
        $set[] = 'status_id = ?';
        $types .= 'i';
        $params[] = $etapaId;
    }
    if ($hasSubstatus) {
        $set[] = 'substatus_id = ?';
        $types .= 'i';
        $params[] = $substatusId;
    }
    if ($hasPrazo) {
        if ($prazo === '') {
            $set[] = 'prazo = NULL';
        } else {
            $set[] = 'prazo = ?';
            $types .= 's';
            $params[] = $prazo;
        }
    }
    if ($hasSubtipo) {
        if ($subtipoId === null) {
            $set[] = 'subtipo_id = NULL';
        } else {
            $set[] = 'subtipo_id = ?';
            $types .= 'i';
            $params[] = $subtipoId;
        }
    }

    $stmtUpdate = $conn->prepare(
        'UPDATE imagens_cliente_obra SET ' . implode(', ', $set) . ' WHERE idimagens_cliente_obra = ?'
    );
    if (!$stmtUpdate) {
        throw new RuntimeException($conn->error);
    }
    $types .= 'i';
    $params[] = $imagemId;
    $stmtUpdate->bind_param($types, ...$params);
    if (!$stmtUpdate->execute()) {
        throw new RuntimeException($stmtUpdate->error);
    }
    $stmtUpdate->close();

    if ($hasSubstatus && $substatusId === FOTOGRAFICO_HOLD_SUBSTATUS_ID) {
        $obraId = (int) $imagem['obra_id'];
        $stmtHold = $conn->prepare(
            'INSERT INTO status_hold (justificativa, imagem_id, obra_id) VALUES (?, ?, ?)'
        );
        if (!$stmtHold) {
            throw new RuntimeException($conn->error);
        }
        $stmtHold->bind_param('sii', $holdJustificativa, $imagemId, $obraId);
        if (!$stmtHold->execute()) {
            throw new RuntimeException($stmtHold->error);
        }
        $stmtHold->close();
    }

    if ($hasSubstatus) {
        if ($substatusId === FOTOGRAFICO_TODO_SUBSTATUS_ID) {
            improov_p00_register_handoff_for_image($conn, $imagemId);
        }

        entregas_review_sync_p00_batch_state($conn, $imagemId, null, $substatusId);
        fotografico_sync_imagem_substatus(
            $conn,
            $imagemId,
            $substatusAnterior,
            $substatusId,
            fotografico_actor_id(),
            'alterarStatus.php'
        );
    }

    if ($hasSubstatus || $hasSubtipo) {
        pendencias_operacionais_sync_image_checklist($conn, $imagemId);
    }

    $conn->commit();
    fotografico_enviar_notificacoes_pendentes($conn);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();

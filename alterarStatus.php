<?php
// alterarStatus.php

include 'conexao.php';

header('Content-Type: application/json');

if (isset($_POST['imagem_id']) && isset($_POST['status_id'])) {
    $imagem_id = (int) $_POST['imagem_id'];
    $status_id = (int) $_POST['status_id'];
    $holdJustificativa = trim((string) ($_POST['hold_justificativa'] ?? ''));

    if ($status_id === 7 && $holdJustificativa === '') {
        echo json_encode(['success' => false, 'error' => 'Justificativa de HOLD é obrigatória.']);
        $conn->close();
        exit;
    }

    $conn->begin_transaction();

    $sql = "UPDATE imagens_cliente_obra SET substatus_id = ? WHERE idimagens_cliente_obra = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $status_id, $imagem_id);

    if (!$stmt->execute()) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }

    $stmt->close();

    if ($status_id === 7) {
        $obraId = null;
        $stmtObra = $conn->prepare("SELECT obra_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ? LIMIT 1");
        $stmtObra->bind_param('i', $imagem_id);
        if (!$stmtObra->execute()) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $stmtObra->error]);
            $stmtObra->close();
            $conn->close();
            exit;
        }

        $resObra = $stmtObra->get_result();
        if ($rowObra = $resObra->fetch_assoc()) {
            $obraId = isset($rowObra['obra_id']) ? (int) $rowObra['obra_id'] : null;
        }
        $stmtObra->close();

        $stmtHold = $conn->prepare("INSERT INTO status_hold (justificativa, imagem_id, obra_id) VALUES (?, ?, ?)");
        $stmtHold->bind_param('sii', $holdJustificativa, $imagem_id, $obraId);
        if (!$stmtHold->execute()) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $stmtHold->error]);
            $stmtHold->close();
            $conn->close();
            exit;
        }
        $stmtHold->close();
    }

    $conn->commit();
    echo json_encode(['success' => true]);

    $conn->close();
} else {
    echo json_encode(['success' => false, 'error' => 'ID da função não fornecido.']);
}

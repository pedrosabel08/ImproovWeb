<?php
require '../conexao.php';

$acao = $_POST['acao'] ?? '';

if ($acao === 'editar') {
    $id = intval($_POST['id']);
    $justificativa = $_POST['justificativa'] ?? null;
    $prazo = $_POST['prazo'] ?? null;

    $stmt = $conn->prepare("UPDATE status_hold
        SET justificativa = ?, prazo = ?
        WHERE imagem_id = ?
    ");
    $stmt->bind_param('ssi', $justificativa, $prazo, $id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar']);
    }
}

if ($acao === 'excluir') {
    $id = intval($_POST['id']);

    // Inicia transação para garantir consistência
    $conn->begin_transaction();

    try {
        // 1. Deleta da tabela status_hold
        $stmt = $conn->prepare("DELETE FROM status_hold WHERE imagem_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();

        // 2. Atualiza o status da imagem
        $stmt2 = $conn->prepare("UPDATE imagens_cliente_obra SET substatus_id = 2 WHERE idimagens_cliente_obra = ?");
        $stmt2->bind_param('i', $id);
        $stmt2->execute();

        // Confirma a transação
        $conn->commit();

        echo json_encode(['status' => 'ok']);
    } catch (Exception $e) {
        // Reverte se der erro
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Erro ao excluir e atualizar']);
    }
}

if ($acao === 'aplicar_todas') {
    $tipo_imagem = $_POST['tipo_imagem'] ?? null;
    $justificativa = $_POST['justificativa'] ?? null;
    $prazo = $_POST['prazo'] ?? null;

    $stmt = $conn->prepare("UPDATE status_hold AS sh
        JOIN imagens_cliente_obra AS ico 
            ON ico.idimagens_cliente_obra = sh.imagem_id
        SET sh.justificativa = ?, 
            sh.prazo = ?
        WHERE ico.tipo_imagem = ? 
          AND ico.substatus_id = 7
    ");

    $stmt->bind_param('ssi', $justificativa, $prazo, $tipo_imagem);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar']);
    }
}

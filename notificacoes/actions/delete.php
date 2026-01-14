<?php

require_once __DIR__ . '/../_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    // limpeza defensiva (mesmo se tiver FK cascade)
    $stmtA = $conn->prepare('DELETE FROM notificacoes_alvos WHERE notificacao_id = ?');
    if ($stmtA) {
        $stmtA->bind_param('i', $id);
        $stmtA->execute();
        $stmtA->close();
    }

    $stmtD = $conn->prepare('DELETE FROM notificacoes_destinatarios WHERE notificacao_id = ?');
    if ($stmtD) {
        $stmtD->bind_param('i', $id);
        $stmtD->execute();
        $stmtD->close();
    }

    $stmt = $conn->prepare('DELETE FROM notificacoes WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: ../index.php?ok=' . urlencode('Notificação excluída.'));
exit();

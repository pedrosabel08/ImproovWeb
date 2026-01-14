<?php

require_once __DIR__ . '/../_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

$id = (int)($_POST['id'] ?? 0);
$ativa = (int)($_POST['ativa'] ?? 0);
$novaAtiva = $ativa ? 0 : 1;

if ($id > 0) {
    $stmt = $conn->prepare('UPDATE notificacoes SET ativa = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $novaAtiva, $id);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: ../index.php?ok=' . urlencode('Status atualizado.'));
exit();

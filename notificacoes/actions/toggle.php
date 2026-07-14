<?php

require_once __DIR__ . '/../_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notificacaoJsonResponse(false, 'Método não permitido.', 405);
}

$id = (int)($_POST['id'] ?? 0);
$ativa = (int)($_POST['ativa'] ?? 0);
$novaAtiva = $ativa ? 0 : 1;

if ($id <= 0) {
    notificacaoJsonResponse(false, 'ID inválido.', 422);
}

$stmt = $conn->prepare('UPDATE notificacoes SET ativa = ? WHERE id = ?');
if (!$stmt) {
    notificacaoJsonResponse(false, 'Erro ao preparar atualização de status.', 500);
}
$stmt->bind_param('ii', $novaAtiva, $id);
if (!$stmt->execute()) {
    $stmt->close();
    notificacaoJsonResponse(false, 'Erro ao atualizar status.', 500);
}
$stmt->close();
notificacaoJsonResponse(true, 'Status atualizado.', 200, ['redirect' => 'index.php']);

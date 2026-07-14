<?php

require_once __DIR__ . '/../_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notificacaoJsonResponse(false, 'Método não permitido.', 405);
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    notificacaoJsonResponse(false, 'ID inválido.', 422);
}

try {
    $filesToRemove = [];
    if (notificacaoAnexosTableExists($conn)) {
        $stmtFiles = $conn->prepare('SELECT caminho FROM notificacoes_anexos WHERE notificacao_id = ?');
        if ($stmtFiles) {
            $stmtFiles->bind_param('i', $id);
            $stmtFiles->execute();
            $resultFiles = $stmtFiles->get_result();
            while ($resultFiles && ($file = $resultFiles->fetch_assoc())) $filesToRemove[] = $file['caminho'];
            $stmtFiles->close();
        }
    }
    $stmtLegacy = $conn->prepare('SELECT arquivo_path FROM notificacoes WHERE id = ?');
    if ($stmtLegacy) {
        $stmtLegacy->bind_param('i', $id);
        $stmtLegacy->execute();
        $legacyResult = $stmtLegacy->get_result();
        if ($legacyResult && ($legacy = $legacyResult->fetch_assoc()) && !empty($legacy['arquivo_path'])) $filesToRemove[] = $legacy['arquivo_path'];
        $stmtLegacy->close();
    }

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
    notificacaoRemoveFiles($filesToRemove);
} catch (Throwable $e) {
    notificacaoJsonResponse(false, 'Erro ao excluir a notificação.', 500);
}

notificacaoJsonResponse(true, 'Notificação excluída.', 200, ['redirect' => 'index.php']);

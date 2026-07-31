<?php

require_once __DIR__ . '/../_common.php';

notificacaoRequirePermission('notification.approve');
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) notificacaoJsonResponse(false, 'ID inválido.', 422);

$sql = 'SELECT n.*, m.codigo AS modulo_codigo, m.nome AS modulo_nome, m.url AS modulo_url, m.icone AS modulo_icone
        FROM notificacoes n
        LEFT JOIN notificacoes_modulos m ON m.id = n.modulo_id
        WHERE n.id = ?';
$stmt = $conn->prepare($sql);
if (!$stmt) notificacaoJsonResponse(false, 'Não foi possível carregar a prévia.', 500);
$stmt->bind_param('i', $id);
$stmt->execute();
$notificacao = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$notificacao) notificacaoJsonResponse(false, 'Notificação não encontrada.', 404);

$notificacao['anexos'] = [];
if (notificacaoAnexosTableExists($conn)) {
    $stmtA = $conn->prepare('SELECT id, nome_original, caminho AS url, mime_type, tamanho FROM notificacoes_anexos WHERE notificacao_id = ? ORDER BY ordem, id');
    if ($stmtA) {
        $stmtA->bind_param('i', $id);
        $stmtA->execute();
        $resA = $stmtA->get_result();
        while ($resA && ($row = $resA->fetch_assoc())) $notificacao['anexos'][] = $row;
        $stmtA->close();
    }
}
if (!empty($notificacao['arquivo_path'])) {
    $notificacao['anexos'][] = ['nome_original' => $notificacao['arquivo_nome'] ?: 'Arquivo', 'url' => $notificacao['arquivo_path'], 'mime_type' => '', 'tamanho' => null, 'legado' => true];
}

notificacaoJsonResponse(true, 'Prévia carregada.', 200, ['data' => $notificacao]);

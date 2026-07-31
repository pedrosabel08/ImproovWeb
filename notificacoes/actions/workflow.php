<?php

require_once __DIR__ . '/../_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    notificacaoJsonResponse(false, 'Método não permitido.', 405);
}

if (!notificacaoWorkflowReady($conn)) {
    notificacaoJsonResponse(false, 'A migration do fluxo de aprovação ainda não foi aplicada.', 422);
}

$id = (int)($_POST['id'] ?? 0);
$acao = strtolower(trim((string)($_POST['acao'] ?? '')));
$motivo = trim((string)($_POST['motivo_rejeicao'] ?? '')) ?: null;
if ($id <= 0 || !in_array($acao, ['enviar_aprovacao', 'aprovar', 'rejeitar', 'publicar', 'encerrar'], true)) {
    notificacaoJsonResponse(false, 'Solicitação inválida.', 422);
}

$permissions = [
    'enviar_aprovacao' => 'notification.edit',
    'aprovar' => 'notification.approve',
    'rejeitar' => 'notification.approve',
    'publicar' => 'notification.publish',
    'encerrar' => 'notification.close',
];
notificacaoRequirePermission($permissions[$acao]);

$rules = [
    'enviar_aprovacao' => ['from' => ['RASCUNHO', 'REJEITADA'], 'to' => 'AGUARDANDO_APROVACAO', 'field' => 'enviado_para_aprovacao'],
    'aprovar' => ['from' => ['AGUARDANDO_APROVACAO'], 'to' => 'APROVADA', 'field' => 'aprovado'],
    'rejeitar' => ['from' => ['AGUARDANDO_APROVACAO'], 'to' => 'REJEITADA', 'field' => 'rejeitado'],
    'publicar' => ['from' => ['APROVADA'], 'to' => 'PUBLICADA', 'field' => 'publicado'],
    'encerrar' => ['from' => ['PUBLICADA'], 'to' => 'ENCERRADA', 'field' => null],
];
$rule = $rules[$acao];
if ($acao === 'rejeitar' && $motivo === null) {
    notificacaoJsonResponse(false, 'Informe o motivo da rejeição ou devolução para ajustes.', 422);
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('SELECT * FROM notificacoes WHERE id = ? FOR UPDATE');
    if (!$stmt) throw new RuntimeException('Não foi possível localizar a notificação.');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $notificacao = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$notificacao) throw new RuntimeException('Notificação não encontrada.');

    $statusAnterior = notificacaoStatusEfetivo($notificacao);
    if (!in_array($statusAnterior, $rule['from'], true)) {
        throw new RuntimeException('Ação indisponível para o estado atual: ' . notificacaoStatusLabel($statusAnterior) . '.');
    }

    $usuarioId = (int)($_SESSION['idusuario'] ?? 0) ?: null;
    $ativa = $rule['to'] === 'PUBLICADA' ? 1 : 0;
    if ($rule['to'] === 'ENCERRADA') $ativa = 0;
    $setAudit = '';
    if ($rule['field']) {
        $setAudit = ', ' . $rule['field'] . '_por = ?, ' . $rule['field'] . '_em = NOW()';
    }
    $sql = 'UPDATE notificacoes SET status_publicacao = ?, ativa = ?, motivo_rejeicao = ?' . $setAudit . ' WHERE id = ?';
    $stmtUpdate = $conn->prepare($sql);
    if (!$stmtUpdate) throw new RuntimeException('Não foi possível atualizar o fluxo da notificação.');
    if ($rule['field']) {
        $stmtUpdate->bind_param('sisii', $rule['to'], $ativa, $motivo, $usuarioId, $id);
    } else {
        $stmtUpdate->bind_param('sisi', $rule['to'], $ativa, $motivo, $id);
    }
    if (!$stmtUpdate->execute()) throw new RuntimeException('Não foi possível atualizar o fluxo da notificação.');
    $stmtUpdate->close();

    if ($acao === 'publicar') {
        notificacaoPublishRecipients($conn, $id, (string)$notificacao['segmentacao_tipo']);
    }
    notificacaoRegistrarHistorico($conn, $id, strtoupper($acao), $statusAnterior, $rule['to'], $motivo);
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    notificacaoJsonResponse(false, $e->getMessage() ?: 'Não foi possível concluir a ação.', 422);
}

notificacaoJsonResponse(true, 'Notificação ' . strtolower(notificacaoStatusLabel($rule['to'])) . '.', 200, ['redirect' => 'index.php']);

<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
$data = json_decode(file_get_contents("php://input"));
$id = (int) ($data->id ?? 0);

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/ws_notify.php';

$realtimeContext = flowReviewResolveRealtimeContext($conn, [
    'comentario_id' => $id,
]);

// Exclui menções em respostas deste comentário
$stmtMencoesResp = $conn->prepare("DELETE m FROM mencoes m INNER JOIN respostas_comentario rc ON rc.id = m.resposta_id WHERE rc.comentario_id = ?");
$stmtMencoesResp->bind_param('i', $id);
$stmtMencoesResp->execute();

// Exclui menções do próprio comentário
$stmtMencoes = $conn->prepare("DELETE FROM mencoes WHERE comentario_id = ?");
$stmtMencoes->bind_param('i', $id);
$stmtMencoes->execute();

$sql = "DELETE FROM comentarios_imagem WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    notifyFlowReviewUpdate($conn, 'comment.deleted', array_merge(
        $realtimeContext,
        ['comentario_id' => $id]
    ));
    echo json_encode(['sucesso' => true]);
} else {
    echo json_encode(['sucesso' => false]);
}

$stmt->close();
$conn->close();

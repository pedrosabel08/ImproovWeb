<?php

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/ws_notify.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $realtimeContext = flowReviewResolveRealtimeContext($conn, [
        'historico_id' => $id,
    ]);


    $sql = "DELETE FROM historico_aprovacoes_imagens WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        notifyFlowReviewUpdate($conn, 'media.deleted', array_merge(
            $realtimeContext,
            ['historico_id' => $id]
        ));
        echo "Imagem excluída com sucesso.";
    } else {
        echo "Erro ao excluir imagem.";
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Requisição inválida.";
}

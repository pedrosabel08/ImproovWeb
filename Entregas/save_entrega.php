<?php
require_once '../conexao.php';

$obra_id = $_POST['obra_id'] ?? null;
$status_id = $_POST['status_id'] ?? null;
$imagem_ids = $_POST['imagem_ids'] ?? [];
$prazo = $_POST['prazo'] ?? null;
$observacoes = $_POST['observacoes'] ?? null;

if (!$obra_id || !$status_id || empty($imagem_ids) || !$prazo) {
    echo json_encode(['success' => false, 'msg' => 'Preencha todos os campos e selecione pelo menos uma imagem.']);
    exit;
}

$conn->begin_transaction();

try {
    // Inserir entrega
    $stmt = $conn->prepare("INSERT INTO entregas (obra_id, status_id, data_prevista, observacoes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $obra_id, $status_id, $prazo, $observacoes);
    $stmt->execute();
    $entrega_id = $stmt->insert_id;
    $stmt->close();

    // Inserir itens
    $stmtItem = $conn->prepare("INSERT INTO entregas_itens (entrega_id, imagem_id, data_prevista) VALUES (?, ?, ?)");
    foreach ($imagem_ids as $imagem_id) {
        $stmtItem->bind_param("iis", $entrega_id, $imagem_id, $prazo);
        $stmtItem->execute();
    }
    $stmtItem->close();

    $conn->commit();
    echo json_encode(['success' => true, 'entrega_id' => $entrega_id]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
}

<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../conexao.php';

$data = $_POST;

$obra_id = isset($data['obra_id']) ? intval($data['obra_id']) : null;
$data_prevista = isset($data['data_prevista']) ? $data['data_prevista'] : null;
$tipo = isset($data['tipo']) ? $data['tipo'] : null;
$observacoes = isset($data['observacoes']) ? $data['observacoes'] : null;
$imagens = isset($data['imagens']) ? $data['imagens'] : [];

if (!$obra_id || !$data_prevista || empty($imagens)) {
    http_response_code(400);
    echo json_encode(['error' => 'Campos obrigatórios faltando: obra_id, data_prevista e pelo menos 1 imagem.']);
    exit;
}

// mapear tipo -> status_id
$etapas = ['R00' => 1, 'R01' => 2, 'R02' => 3, 'EF' => 4];
$status_id = isset($etapas[$tipo]) ? $etapas[$tipo] : 1;
$status = 'Pendente';

$conn->begin_transaction();

try {
    $sql = "INSERT INTO entregas (status_id, obra_id, data_prevista, status, observacoes, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $status_id, $obra_id, $data_prevista, $status, $observacoes);
    if (!$stmt->execute()) throw new Exception('Erro ao inserir entrega: ' . $stmt->error);
    $entrega_id = $conn->insert_id;

    $sql2 = "INSERT INTO entregas_itens (entrega_id, imagem_id, data_prevista, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())";
    $stmt2 = $conn->prepare($sql2);

    foreach ($imagens as $imgId) {
        $imgId = intval($imgId);
        $stmt2->bind_param("iiss", $entrega_id, $imgId, $data_prevista, $status);
        if (!$stmt2->execute()) throw new Exception('Erro ao inserir item: ' . $stmt2->error);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'entrega_id' => $entrega_id]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

<?php
header('Content-Type: application/json');
include '../conexao.php';

// Obtém a nova ordem enviada pelo cliente
$data = json_decode(file_get_contents('php://input'), true);
$ordem = $data['ordem'];

if (!$ordem || !is_array($ordem)) {
    echo json_encode(["success" => false, "message" => "Ordem inválida."]);
    exit;
}

// Atualiza a ordem no banco de dados
$conn->begin_transaction();
try {
    foreach ($ordem as $posicao => $id) {
        $stmt = $conn->prepare("UPDATE observacao_obra SET ordem = ? WHERE id = ?");
        $stmt->bind_param("ii", $posicao, $id);
        $stmt->execute();
        $stmt->close();
    }
    $conn->commit();
    echo json_encode(["success" => true, "message" => "Ordem atualizada com sucesso."]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => "Erro ao atualizar ordem: " . $e->getMessage()]);
}

$conn->close();

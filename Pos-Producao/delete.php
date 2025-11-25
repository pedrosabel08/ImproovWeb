<?php
header("Content-Type: application/json");

// Conectar ao banco de dados central
include_once __DIR__ . '/../conexao.php';

// Receber o JSON do JavaScript
$data = json_decode(file_get_contents('php://input'), true);

// Verificar se o ID foi enviado
if (isset($data['id_pos'])) {
    $idPos = $data['id_pos'];

    // SQL para deletar a linha com o ID
    $sql = "DELETE FROM pos_producao WHERE idpos_producao = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idPos);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao deletar o item']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
}

$conn->close();

<?php
header("Content-Type: application/json");

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Falha na conexão com o banco de dados']);
    exit;
}

$conn->set_charset('utf8mb4');

// Receber o JSON do JavaScript
$data = json_decode(file_get_contents('php://input'), true);

// Verificar se pelo menos um ID foi enviado
if (!empty($data['id_anima'])) {
    $conn->begin_transaction(); // Iniciar uma transação

    try {
        // Deletar da tabela cena, se o ID for fornecido
        if (!empty($data['id_anima'])) {
            $stmt = $conn->prepare("DELETE FROM cena WHERE animacao_id = ?");
            $stmt->bind_param("i", $data['id_anima']);
            $stmt->execute();
            $stmt->close();
        }

        // Deletar da tabela render, se o ID for fornecido
        if (!empty($data['id_anima'])) {
            $stmt = $conn->prepare("DELETE FROM render WHERE animacao_id = ?");
            $stmt->bind_param("i", $data['id_anima']);
            $stmt->execute();
            $stmt->close();
        }

        // Deletar da tabela pos, se o ID for fornecido
        if (!empty($data['id_anima'])) {
            $stmt = $conn->prepare("DELETE FROM pos WHERE animacao_id = ?");
            $stmt->bind_param("i", $data['id_anima']);
            $stmt->execute();
            $stmt->close();
        }

        // Deletar da tabela animacao, se o ID for fornecido
        if (!empty($data['id_anima'])) {
            $stmt = $conn->prepare("DELETE FROM animacao WHERE idanimacao = ?");
            $stmt->bind_param("i", $data['id_anima']);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit(); // Confirmar a transação
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback(); // Reverter a transação em caso de erro
        echo json_encode(['success' => false, 'message' => 'Erro ao deletar os itens: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Nenhum ID fornecido para deletar']);
}

$conn->close();

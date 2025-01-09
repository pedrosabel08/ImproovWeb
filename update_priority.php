<?php
include 'conexao.php';

// Verifica se os dados foram enviados corretamente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lê o corpo da requisição JSON
    $data = json_decode(file_get_contents('php://input'), true);

    if (!empty($data['id']) && isset($data['prioridade'])) {
        $id = (int) $data['id']; // Certifica-se de que é inteiro
        $prioridade = (int) $data['prioridade']; // Certifica-se de que é inteiro

        try {
            // Prepara a declaração SQL para atualizar a prioridade
            $stmt = $conn->prepare("UPDATE prioridade_funcao SET prioridade = ? WHERE funcao_imagem_id = ?");
            $stmt->bind_param("ii", $prioridade, $id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Prioridade atualizada com sucesso.']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erro ao atualizar a prioridade.']);
            }

            $stmt->close();
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao processar a requisição.', 'error' => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Dados inválidos ou incompletos.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
}

$conn->close();

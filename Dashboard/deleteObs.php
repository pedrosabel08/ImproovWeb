<?php
header('Content-Type: application/json');


include '../conexao.php';

// Verifica se o ID foi passado via POST
if (isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Prepara a declaração SQL para deletar a observação
    $sql = "DELETE FROM observacao_obra WHERE id = ?";

    // Prepara a declaração
    if ($stmt = $conn->prepare($sql)) {
        // Vincula o parâmetro
        $stmt->bind_param("i", $id);

        // Executa a declaração
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Observação deletada com sucesso."]);
        } else {
            echo json_encode(["success" => false, "message" => "Erro ao deletar a observação: " . $stmt->error]);
        }

        // Fecha a declaração
        $stmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao preparar a declaração: " . $conn->error]);
    }
} else {
    echo json_encode(["success" => false, "message" => "ID da observação não fornecido."]);
}

// Fecha a conexão
$conn->close();

<?php
// Conectar ao banco de dados (ajuste as credenciais conforme necessário)
include 'conexao.php';

// Lê os dados enviados via POST (JSON)
$data = json_decode(file_get_contents("php://input"), true);

// Verifica se os dados existem
if ($data && isset($data['imagem_id'])) {
    $imagem_id = $data['imagem_id'];

    // Prepara a consulta para inserir os dados na tabela
    $stmt = $conn->prepare("INSERT INTO render_alta (imagem_id) VALUES (?)");
    $stmt->bind_param("i", $imagem_id);

    // Executa a consulta
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'sucesso',
            'message' => "Render com status '$render' e imagem_id '$imagem_id' inserido com sucesso."
        ]);
    } else {
        echo json_encode([
            'status' => 'erro',
            'message' => 'Erro ao inserir os dados: ' . $stmt->error
        ]);
    }

    // Fecha a declaração e a conexão
    $stmt->close();
} else {
    echo json_encode([
        'status' => 'erro',
        'message' => 'Dados incompletos ou inválidos.'
    ]);
}

$conn->close();

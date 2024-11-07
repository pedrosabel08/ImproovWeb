<?php
header('Content-Type: application/json');

// Conectar ao banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

// Verificar a conexão
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Falha na conexão com o banco de dados']);
    exit();
}
$conn->set_charset('utf8mb4');

// Receber os dados JSON enviados
$data = json_decode(file_get_contents('php://input'), true);

// Verificar se os dados obrigatórios foram enviados
if (!isset($data['obraAcomp']) || !isset($data['colab_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Dados insuficientes']);
    exit();
}

// Selecionar todas as imagens relacionadas à obra
$obra_id = $data['obraAcomp'];
$colab_id = $data['colab_id'];

$sql_imagens = "SELECT idimagens_cliente_obra FROM imagens_cliente_obra WHERE obra_id = ?";
$stmt_imagens = $conn->prepare($sql_imagens);
$stmt_imagens->bind_param('i', $obra_id);
$stmt_imagens->execute();
$result_imagens = $stmt_imagens->get_result();

if ($result_imagens->num_rows > 0) {
    // Preparar o statement para inserir cada imagem na tabela acompanhamento
    $stmt_acomp = $conn->prepare("INSERT INTO acompanhamento (obra_id, colaborador_id, imagem_id) VALUES (?, ?, ?)");

    // Loop para inserir cada imagem encontrada
    while ($row = $result_imagens->fetch_assoc()) {
        $imagem_id = $row['idimagens_cliente_obra'];

        // Vincular parâmetros e executar inserção para cada imagem
        $stmt_acomp->bind_param('iii', $obra_id, $colab_id, $imagem_id);

        if (!$stmt_acomp->execute()) {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao inserir acompanhamento: ' . $stmt_acomp->error]);
            $stmt_acomp->close();
            $conn->close();
            exit();
        }
    }

    $stmt_acomp->close();
    echo json_encode(['status' => 'success', 'message' => 'Acompanhamento inserido com sucesso para todas as imagens da obra']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nenhuma imagem encontrada para a obra especificada']);
}

$stmt_imagens->close();
$conn->close();

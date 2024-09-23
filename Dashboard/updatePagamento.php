<?php
header('Content-Type: application/json');

// Conexão com o banco de dados
$conn = new mysqli('mysql.improov.com.br', 'improov', 'Impr00v', 'improov');

if ($conn->connect_error) {
    die(json_encode(["error" => "Falha na conexão: " . $conn->connect_error]));
}

$conn->set_charset('utf8mb4');

// Capturando o ID e o novo status de pagamento
$data = json_decode(file_get_contents('php://input'), true);
$id = intval($_GET['idfuncao_imagem']);
$pagamento = intval($data['pagamento']);

// Preparando e executando o UPDATE
$sql = "UPDATE funcao_imagem SET pagamento = ? WHERE idfuncao_imagem = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $pagamento, $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["error" => "Erro ao atualizar pagamento"]);
}

// Fechando a conexão
$stmt->close();
$conn->close();

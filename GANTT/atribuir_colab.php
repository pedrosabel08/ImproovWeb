<?php
// Conexão com o banco de dados

include '../conexao.php';

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["erro" => "Erro na conexão com o banco de dados."]);
    exit;
}

// Define o tipo de retorno como JSON
header("Content-Type: application/json");

// Recebe os dados JSON do fetch
$input = json_decode(file_get_contents("php://input"), true);

// Validação básica
$colaborador_id = intval($input["colaborador_id"] ?? 0);
$imagem_id = intval($input["imagem_id"] ?? 0);
$funcao_id = intval($input["funcao_id"] ?? 0);

if ($colaborador_id === 0 || $imagem_id === 0 || $funcao_id === 0) {
    http_response_code(400);
    echo json_encode(["erro" => "Dados inválidos."]);
    exit;
}

// Prepara e executa o INSERT
$stmt = $conn->prepare("INSERT INTO funcao_imagem (colaborador_id, imagem_id, funcao_id)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE colaborador_id = VALUES(colaborador_id)
");
$stmt->bind_param("iii", $colaborador_id, $imagem_id, $funcao_id);

if ($stmt->execute()) {
    echo json_encode(["sucesso" => true]);
} else {
    http_response_code(500);
    echo json_encode(["erro" => "Erro ao inserir no banco de dados."]);
}

// Encerra
$stmt->close();
$conn->close();

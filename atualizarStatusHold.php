<?php
header("Content-Type: application/json");
require_once "conexao.php"; // Certifique-se de ter a conexão com o banco

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["status_hold"], $data["imagem_id"])) {
    echo json_encode(["success" => false, "message" => "Dados incompletos"]);
    exit;
}

$statusHold = $data["status_hold"]; // Array de valores selecionados
$imagemId = intval($data["imagem_id"]); // Garantir que seja um número inteiro
$obra_id = intval($data["obra_id"]); // Garantir que seja um número inteiro

// 1️⃣ Buscar os valores já cadastrados
$sqlExistentes = "SELECT descricao FROM status_hold WHERE imagem_id = ?";
$stmt = $conn->prepare($sqlExistentes);
$stmt->bind_param("i", $imagemId);
$stmt->execute();
$result = $stmt->get_result();

$valoresExistentes = [];
while ($row = $result->fetch_assoc()) {
    $valoresExistentes[] = $row["descricao"];
}
$stmt->close();

// 2️⃣ Filtrar apenas os novos valores
$novosValores = array_diff($statusHold, $valoresExistentes);

if (!empty($novosValores)) {
    // 3️⃣ Inserir apenas os novos valores
    $sqlInsert = "INSERT INTO status_hold (descricao, obra_id, imagem_id) VALUES (?, ?, ?)";
    $stmtInsert = $conn->prepare($sqlInsert);

    foreach ($novosValores as $valor) {
        $stmtInsert->bind_param("sii", $valor, $imagemId, $obra_id);
        $stmtInsert->execute();
    }
    $stmtInsert->close();
}

echo json_encode(["success" => true]);

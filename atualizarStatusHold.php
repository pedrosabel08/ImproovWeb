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
$sqlExistentes = "SELECT justificativa FROM status_hold WHERE imagem_id = ?";
$stmt = $conn->prepare($sqlExistentes);
$stmt->bind_param("i", $imagemId);
$stmt->execute();
$result = $stmt->get_result();

$valoresExistentes = [];
while ($row = $result->fetch_assoc()) {
    $valoresExistentes[] = $row["justificativa"];
}
$stmt->close();

// 2️⃣ Filtrar apenas os novos valores
$novosValores = array_diff($statusHold, $valoresExistentes);

if (!empty($novosValores)) {
    $sqlInsert = "INSERT INTO status_hold (justificativa, obra_id, imagem_id) VALUES (?, ?, ?)";
    $stmtInsert = $conn->prepare($sqlInsert);

    if (!$stmtInsert) {
        error_log("Erro ao preparar a consulta: " . $conn->error);
        echo json_encode(["success" => false, "message" => "Erro ao preparar a consulta"]);
        exit;
    }

    foreach ($novosValores as $valor) {
        $stmtInsert->bind_param("sii", $valor, $obra_id, $imagemId);
        if (!$stmtInsert->execute()) {
            error_log("Erro ao executar a consulta: " . $stmtInsert->error);
        }
    }
    $stmtInsert->close();
}

echo json_encode(["success" => true]);

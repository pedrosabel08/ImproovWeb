<?php
include '../../conexao.php';
header('Content-Type: application/json');

$response = [];

// ===============================
// 🔹 2. Média de tempo de conclusão
// ===============================
$sql = "SELECT * FROM feed_atualizacoes";

$result = $conn->query($sql);
$response['atualizacoes'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];



$conn->close();

// 🧩 Retorna tudo como JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE);

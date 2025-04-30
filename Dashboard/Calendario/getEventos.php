<?php
include '../../conexao.php'; // Caminho relativo para o arquivo de conexão
$obraId = $_GET['obraId'] ?? 0;

$sql = "SELECT id, descricao, data_evento AS start, tipo_evento FROM eventos_obra WHERE obra_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $obraId);
$stmt->execute();
$result = $stmt->get_result();

$eventos = [];
while ($row = $result->fetch_assoc()) {
    $eventos[] = $row; // FullCalendar espera id, title e start
}

header('Content-Type: application/json');
echo json_encode($eventos);

<?php
include 'conexao.php';

if (!isset($_POST['idcolaborador'])) {
    echo json_encode(['error' => 'ID do colaborador não fornecido.']);
    exit;
}

$idcolaborador = (int)$_POST['idcolaborador'];
$dataAtual = date('Y-m-d'); // Obtém a data atual no formato YYYY-MM-DD

// Consulta para verificar se há respostas do colaborador para o dia atual
$sql = "SELECT COUNT(*) as total FROM respostas_diarias WHERE colaborador_id = ? AND DATE(data) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $idcolaborador, $dataAtual);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();
$conn->close();

// Retorna se há ou não respostas
if ($total > 0) {
    echo json_encode(['hasResponses' => true]);
} else {
    echo json_encode(['hasResponses' => false]);
}

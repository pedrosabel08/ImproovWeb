<?php
require_once __DIR__ . '/config/session_bootstrap.php';
include 'conexao.php';
session_start(); // Certifique-se de iniciar a sessão

// Retorna JSON
header('Content-Type: application/json');

// Define timezone para garantir data/hora corretos (Brasil - São Paulo)
date_default_timezone_set('America/Sao_Paulo');

if (!isset($_POST['idcolaborador'])) {
    echo json_encode(['error' => 'ID do colaborador não fornecido.']);
    exit;
}

// Verifica se o usuário é o ID 3
if (isset($_SESSION['idusuario']) && $_SESSION['idusuario'] == 3) {
    echo json_encode(['hasResponses' => true]);
    exit;
}

$idcolaborador = (int)$_POST['idcolaborador'];
$dt = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$dataAtual = $dt->format('Y-m-d'); // Obtém a data atual no formato YYYY-MM-DD

// Consulta para verificar se há respostas do colaborador para o dia atual
$sql = "SELECT COUNT(*) as total FROM respostas_diarias WHERE colaborador_id = ? AND DATE(data) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $idcolaborador, $dataAtual);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();
$conn->close();

// Retorna se há ou não respostas (único JSON)
if ($total > 0) {
    echo json_encode(['hasResponses' => true]);
} else {
    echo json_encode(['hasResponses' => false]);
}

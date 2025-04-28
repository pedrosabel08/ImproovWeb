<?php
include("conexao.php");

$id = $_POST['idcolaborador'] ?? 0;

$sql = "SELECT COUNT(*) AS total FROM render_alta WHERE responsavel_id = ? AND status <> 'Arquivado'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode(['total' => $data['total'] ?? 0]);

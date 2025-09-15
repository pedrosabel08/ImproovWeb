<?php
header("Access-Control-Allow-Origin: *"); // ou especificar o domínio
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


include("conexao.php");

$id = $_POST['idcolaborador'] ?? 0;

$sql = "SELECT COUNT(*) AS total FROM render_alta WHERE responsavel_id = ? AND status NOT IN ('Finalizado', 'Aprovado')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode(['total' => $data['total'] ?? 0]);

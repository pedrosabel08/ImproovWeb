<?php
header('Content-Type: application/json');
include '../conexao.php';

$idObra = isset($_GET['idobra']) ? intval($_GET['idobra']) : 0;

$sql = "SELECT assunto, data FROM acompanhamento_email WHERE obra_id = ? order by data desc";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idObra);
$stmt->execute();
$result = $stmt->get_result();

$acompanhamentos = [];
while ($row = $result->fetch_assoc()) {
    $acompanhamentos[] = $row;
}

echo json_encode($acompanhamentos);

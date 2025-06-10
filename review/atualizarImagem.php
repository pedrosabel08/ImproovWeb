<?php
include '../conexao.php';
$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data['id']);
$acao = $data['acao'];

if ($acao === 'lock') {
    $sql = "UPDATE review_uploads SET `lock` = NOT `lock` WHERE id = ?";
} elseif ($acao === 'hide') {
    $sql = "UPDATE review_uploads SET hide = NOT hide WHERE id = ?";
} else {
    http_response_code(400);
    echo json_encode(["error" => "Ação inválida."]);
    exit;
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(["success" => true]);

<?php
require_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $novaOrdem = intval($_POST['ordem']);

    if ($id && $novaOrdem) {
        $sql = "UPDATE acompanhamento_email SET ordem = ? WHERE idacompanhamento_email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $novaOrdem, $id);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }

        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Dados inválidos"]);
    }
}
$conn->close();

<?php
session_start();
include_once '../conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['idusuario']; // ID do usuário logado
    $data = json_decode(file_get_contents('php://input'), true);
    $notificacao_id = $data['id'];

    $sql = "UPDATE notificacoes_usuarios SET lida = 1 WHERE usuario_id = ? AND notificacao_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $usuario_id, $notificacao_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }

    $stmt->close();
    $conn->close();
}

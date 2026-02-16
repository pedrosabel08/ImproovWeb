<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE)
    session_start();

if (!isset($_SESSION['idusuario'])) {
    echo json_encode(['error' => 'not_logged']);
    exit;
}

// tenta conexão
include __DIR__ . '/../conexao.php';


$usuario_id = intval($_SESSION['idusuario']);

$sql = "UPDATE logs_usuarios SET last_panel_shown_date = CURDATE() WHERE usuario_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $usuario_id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok)
        echo json_encode(['ok' => true]);
    else
        echo json_encode(['ok' => false]);
} else {
    echo json_encode(['ok' => false]);
}

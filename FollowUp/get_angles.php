<?php
header('Content-Type: application/json; charset=utf-8');
include __DIR__ . '/../conexao.php';

// Pode receber ?imagem_id= or ?render_id=
$imagem_id = isset($_GET['imagem_id']) ? intval($_GET['imagem_id']) : null;
$render_id = isset($_GET['render_id']) ? intval($_GET['render_id']) : null;

if (!$imagem_id && !$render_id) {
    echo json_encode(['error' => 'imagem_id ou render_id requerido']);
    exit;
}

if ($imagem_id) {
    $stmt = $conn->prepare("SELECT id, render_id, imagem_id, filename, uploaded_at, status FROM followup_angles WHERE imagem_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $imagem_id);
} else {
    $stmt = $conn->prepare("SELECT id, render_id, imagem_id, filename, uploaded_at, status FROM followup_angles WHERE render_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $render_id);
}

$stmt->execute();
$res = $stmt->get_result();
$angles = [];
while ($row = $res->fetch_assoc()) {
    $angles[] = $row;
}

echo json_encode(['angles' => $angles], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();

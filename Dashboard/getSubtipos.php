<?php
header('Content-Type: application/json');
include '../conexao.php';

// POST: criar novo subtipo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $nome = isset($body['nome']) ? trim($body['nome']) : '';
    if ($nome === '') {
        echo json_encode(['error' => 'Nome vazio']);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO subtipo_imagem (nome) VALUES (?)");
    $stmt->bind_param('s', $nome);
    if ($stmt->execute()) {
        $id = $conn->insert_id;
        echo json_encode(['id' => $id, 'nome' => $nome]);
    } else {
        echo json_encode(['error' => $conn->error]);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// GET: listar subtipos
$result = $conn->query("SELECT id, nome FROM subtipo_imagem ORDER BY nome ASC");
if (!$result) {
    echo json_encode([]);
    exit;
}

echo json_encode($result->fetch_all(MYSQLI_ASSOC));
$conn->close();

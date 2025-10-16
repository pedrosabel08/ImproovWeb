<?php
include '../conexao.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$imagem_id = $input['imagem_id'] ?? null;
$colaborador_id = $input['colaborador_id'] ?? null;
$funcao_id = 4; // função de finalização (ajuste conforme necessário)

if (!$imagem_id || !$colaborador_id) {
    echo json_encode(['error' => 'imagem_id and colaborador_id required']);
    exit;
}

// Verifica se já existe uma funcao_imagem para essa imagem e funcao
$stmt = $conn->prepare('SELECT idfuncao_imagem FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = ? LIMIT 1');
$stmt->bind_param('ii', $imagem_id, $funcao_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    // atualiza colaborador
    $id = $row['idfuncao_imagem'];
    $u = $conn->prepare('UPDATE funcao_imagem SET colaborador_id = ? WHERE idfuncao_imagem = ?');
    $u->bind_param('ii', $colaborador_id, $id);
    $u->execute();
    echo json_encode(['ok' => true, 'updated' => true]);
} else {
    // insere nova funcao_imagem
    $i = $conn->prepare('INSERT INTO funcao_imagem (imagem_id, funcao_id, colaborador_id) VALUES (?, ?, ?)');
    $i->bind_param('iii', $imagem_id, $funcao_id, $colaborador_id);
    $i->execute();
    echo json_encode(['ok' => true, 'inserted' => true]);
}

$conn->close();

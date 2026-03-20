<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
include '../conexao.php';

$data = json_decode(file_get_contents('php://input'), true);
$funcaoImagemId = isset($data['funcao_imagem_id']) ? intval($data['funcao_imagem_id']) : 0;

if (!$funcaoImagemId) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'funcao_imagem_id inválido']);
    exit;
}

$mencionadoId = $_SESSION['idcolaborador'];

$stmt = $conn->prepare("
    UPDATE mencoes m
    INNER JOIN comentarios_imagem c ON c.id = m.comentario_id
    INNER JOIN historico_aprovacoes_imagens hai ON hai.id = c.ap_imagem_id
    SET m.visto = 1
    WHERE hai.funcao_imagem_id = ?
      AND m.mencionado_id = ?
      AND m.visto = 0
");
$stmt->bind_param("ii", $funcaoImagemId, $mencionadoId);
$stmt->execute();

// Marca também menções em respostas (via respostas → comentário → tarefa)
$stmt2 = $conn->prepare("
    UPDATE mencoes m
    INNER JOIN respostas_comentario rc ON rc.id = m.resposta_id
    INNER JOIN comentarios_imagem c ON c.id = rc.comentario_id
    INNER JOIN historico_aprovacoes_imagens hai ON hai.id = c.ap_imagem_id
    SET m.visto = 1
    WHERE hai.funcao_imagem_id = ?
      AND m.mencionado_id = ?
      AND m.visto = 0
");
$stmt2->bind_param("ii", $funcaoImagemId, $mencionadoId);
$stmt2->execute();

echo json_encode(['sucesso' => true, 'atualizadas' => $stmt->affected_rows + $stmt2->affected_rows]);

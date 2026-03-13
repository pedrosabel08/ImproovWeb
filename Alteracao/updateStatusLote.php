<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
include '../conexao.php';
header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload || !isset($payload['funcao_ids']) || !is_array($payload['funcao_ids']) || !isset($payload['status'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$funcaoIds = array_values(array_unique(array_map('intval', $payload['funcao_ids'])));
$statusDestino = trim((string)$payload['status']);
$atribuirLogado = !empty($payload['atribuir_logado']);
$usuarioLogadoId = $_SESSION['idcolaborador'] ?? null;

if (empty($funcaoIds) || $statusDestino === '') {
    echo json_encode(['success' => false, 'message' => 'ID(s) ou status inválido(s).']);
    exit;
}

$conn->begin_transaction();

try {
    $stmtAtualizar = $conn->prepare('UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?');
    $stmtAtualizarComColab = $conn->prepare('UPDATE funcao_imagem SET status = ?, colaborador_id = ? WHERE idfuncao_imagem = ?');

    foreach ($funcaoIds as $funcaoId) {
        if ($atribuirLogado && mb_strtolower($statusDestino, 'UTF-8') === 'em andamento' && $usuarioLogadoId) {
            $stmtAtualizarComColab->bind_param('sii', $statusDestino, $usuarioLogadoId, $funcaoId);
            $stmtAtualizarComColab->execute();
        } else {
            $stmtAtualizar->bind_param('si', $statusDestino, $funcaoId);
            $stmtAtualizar->execute();
        }
    }

    $stmtAtualizar->close();
    $stmtAtualizarComColab->close();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();

<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/motor_requisitos_helper.php';
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
$blockedEvaluation = null;

if (empty($funcaoIds) || $statusDestino === '') {
    echo json_encode(['success' => false, 'message' => 'ID(s) ou status inválido(s).']);
    exit;
}

$conn->begin_transaction();

try {
    $stmtAtualizar = $conn->prepare('UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?');
    $stmtAtualizarComColab = $conn->prepare('UPDATE funcao_imagem SET status = ?, colaborador_id = ? WHERE idfuncao_imagem = ?');

    foreach ($funcaoIds as $funcaoId) {
        if (mb_strtolower($statusDestino, 'UTF-8') === 'em andamento') {
            $stmtAtual = $conn->prepare('SELECT status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1');
            $stmtAtual->bind_param('i', $funcaoId);
            $stmtAtual->execute();
            $atual = $stmtAtual->get_result()->fetch_assoc();
            $stmtAtual->close();
            if ($atual && strcasecmp((string) $atual['status'], 'Não iniciado') === 0) {
                $blockedEvaluation = motor_requisitos_avaliar_funcao_imagem($conn, $funcaoId);
                if (!$blockedEvaluation['elegivel']) {
                    throw new DomainException('A tarefa possui requisitos pendentes para iniciar.');
                }
            }
        }
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
    if ($e instanceof DomainException) {
        http_response_code(422);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();

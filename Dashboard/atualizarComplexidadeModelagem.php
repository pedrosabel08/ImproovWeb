<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$obraId = (int) ($data['obra_id'] ?? 0);
$complexidadeId = (int) ($data['complexidade_modelagem_id'] ?? 0);

if ($obraId <= 0 || $complexidadeId <= 0) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Informe a obra e a complexidade.'], JSON_UNESCAPED_UNICODE);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $stmtComplexidade = $conn->prepare(
        'SELECT id FROM complexidade_modelagem WHERE id = ? AND ativo = 1 LIMIT 1'
    );
    $stmtComplexidade->bind_param('i', $complexidadeId);
    $stmtComplexidade->execute();
    $complexidadeValida = $stmtComplexidade->get_result()->num_rows === 1;
    $stmtComplexidade->close();

    if (!$complexidadeValida) {
        throw new RuntimeException('A complexidade informada não está disponível.');
    }

    $obra = $conn->prepare('SELECT idobra FROM obra WHERE idobra = ? FOR UPDATE');
    if (!$obra) {
        throw new RuntimeException($conn->error);
    }
    $obra->bind_param('i', $obraId);
    $obra->execute();
    if ($obra->get_result()->num_rows !== 1) {
        $obra->close();
        throw new RuntimeException('Obra não encontrada.');
    }
    $obra->close();

    if (!improov_usuario_pode_acessar_obra($conn, $obraId)) {
        throw new RuntimeException('Sem acesso a esta obra.');
    }

    $stmtAtualizar = $conn->prepare(
        'UPDATE obra SET complexidade_modelagem_id = ? WHERE idobra = ?'
    );
    $stmtAtualizar->bind_param('ii', $complexidadeId, $obraId);
    if (!$stmtAtualizar->execute()) {
        throw new RuntimeException($stmtAtualizar->error);
    }
    $stmtAtualizar->close();

    mysqli_commit($conn);
    echo json_encode([
        'sucesso' => true,
        'obra_id' => $obraId,
        'complexidade_modelagem_id' => $complexidadeId,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();

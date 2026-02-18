<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
include '../conexao.php';
header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload || !isset($payload['imagem_ids']) || !is_array($payload['imagem_ids']) || !isset($payload['status'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$imagemIds = array_values(array_unique(array_map('intval', $payload['imagem_ids'])));
$statusDestino = trim((string)$payload['status']);
$atribuirLogado = !empty($payload['atribuir_logado']);
$usuarioLogadoId = $_SESSION['idcolaborador'] ?? null;

if (empty($imagemIds) || $statusDestino === '') {
    echo json_encode(['success' => false, 'message' => 'Imagem(s) ou status inválido(s).']);
    exit;
}

$conn->begin_transaction();

try {
    $stmtBuscar = $conn->prepare('SELECT idfuncao_imagem FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = 6');
    $stmtCriar = $conn->prepare('INSERT INTO funcao_imagem (imagem_id, colaborador_id, funcao_id, status) VALUES (?, NULL, 6, ?)');
    $stmtAtualizar = $conn->prepare('UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?');
    $stmtAtualizarComColab = $conn->prepare('UPDATE funcao_imagem SET status = ?, colaborador_id = ? WHERE idfuncao_imagem = ?');

    foreach ($imagemIds as $imagemId) {
        $stmtBuscar->bind_param('i', $imagemId);
        $stmtBuscar->execute();
        $stmtBuscar->bind_result($funcaoIdExistente);
        $temRegistro = $stmtBuscar->fetch();
        $stmtBuscar->free_result();

        if ($temRegistro && $funcaoIdExistente) {
            $funcaoId = (int)$funcaoIdExistente;
        } else {
            $stmtCriar->bind_param('is', $imagemId, $statusDestino);
            $stmtCriar->execute();
            $funcaoId = (int)$conn->insert_id;
        }

        if ($atribuirLogado && mb_strtolower($statusDestino, 'UTF-8') === 'em andamento' && $usuarioLogadoId) {
            $stmtAtualizarComColab->bind_param('sii', $statusDestino, $usuarioLogadoId, $funcaoId);
            $stmtAtualizarComColab->execute();
        } else {
            $stmtAtualizar->bind_param('si', $statusDestino, $funcaoId);
            $stmtAtualizar->execute();
        }
    }

    $stmtBuscar->close();
    $stmtCriar->close();
    $stmtAtualizar->close();
    $stmtAtualizarComColab->close();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();

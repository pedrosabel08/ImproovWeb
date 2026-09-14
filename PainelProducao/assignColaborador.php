<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/inicio_operacional_helper.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['logado'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sessao invalida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$imagem_id = $input['imagem_id'] ?? null;
$colaborador_id = $input['colaborador_id'] ?? null;
$funcao_id = 4; // função de finalização (ajuste conforme necessário)

if (!$imagem_id || !$colaborador_id) {
    echo json_encode(['error' => 'imagem_id and colaborador_id required']);
    exit;
}

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare('SELECT idfuncao_imagem, colaborador_id, status FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = ? LIMIT 1 FOR UPDATE');
    $stmt->bind_param('ii', $imagem_id, $funcao_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $id = (int) $row['idfuncao_imagem'];
        $transferencia = null;
        if ((int) $row['colaborador_id'] !== (int) $colaborador_id && flow_wip_status_ativo((string) $row['status']) && flow_janela_schema_disponivel($conn)) {
            $transferencia = flow_inicio_operacional_transferir($conn, [
                'funcao_imagem_id' => $id,
                'novo_responsavel_id' => (int) $colaborador_id,
                'nova_previsao' => $input['nova_previsao'] ?? null,
                'motivo_transferencia' => $input['motivo_transferencia'] ?? 'Transferencia pelo Painel de Producao.',
                'ator_colaborador_id' => (int) ($_SESSION['idcolaborador'] ?? 0) ?: null,
                'ator_usuario_id' => (int) ($_SESSION['idusuario'] ?? 0) ?: null,
            ]);
        } else {
            $u = $conn->prepare('UPDATE funcao_imagem SET colaborador_id = ? WHERE idfuncao_imagem = ?');
            $u->bind_param('ii', $colaborador_id, $id);
            $u->execute();
            $u->close();
        }
        $resultado = ['ok' => true, 'updated' => true, 'transfer' => $transferencia];
    } else {
        $i = $conn->prepare('INSERT INTO funcao_imagem (imagem_id, funcao_id, colaborador_id) VALUES (?, ?, ?)');
        $i->bind_param('iii', $imagem_id, $funcao_id, $colaborador_id);
        $i->execute();
        $i->close();
        $resultado = ['ok' => true, 'inserted' => true];
    }
    $conn->commit();
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code($error instanceof DomainException || $error instanceof FlowWipException ? 422 : 500);
    echo json_encode(['ok' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();

<?php
header('Content-Type: application/json');
// Flow Radar - Atribuir tarefa a colaborador
require_once __DIR__ . '/config/session_bootstrap.php';
include 'conexao.php';
require_once __DIR__ . '/helpers/motor_requisitos_helper.php';
require_once __DIR__ . '/helpers/unidade_trabalho_helper.php';

$conn = conectarBanco();

$obras_inativas = obterObras($conn, 1);

$data = json_decode(file_get_contents('php://input'), true);
$colaborador_id = isset($data['colaborador_id']) ? (int)$data['colaborador_id'] : 0;
$funcao_imagem_id = isset($data['funcao_imagem_id']) ? (int)$data['funcao_imagem_id'] : 0;
$confirmarPendencias = !empty($data['confirmar_pendencias']);

if (!$colaborador_id || !$funcao_imagem_id) {
    echo json_encode(['error' => 'Parâmetros obrigatórios: colaborador_id e funcao_imagem_id']);
    exit;
}

try {
    if (empty($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'code' => 'UNAUTHENTICATED', 'message' => 'Sessão inválida.']);
        exit;
    }
    $conn->begin_transaction();
    // Proteção: só atribuir se tarefa estiver sem colaborador
    $sql_check = "SELECT idfuncao_imagem, imagem_id, funcao_id, colaborador_id, status FROM funcao_imagem WHERE idfuncao_imagem = ? FOR UPDATE";
    $stmt = $conn->prepare($sql_check);
    $stmt->bind_param('i', $funcao_imagem_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res && !empty($res['colaborador_id']) && $res['colaborador_id'] != 0) {
        throw new DomainException('Tarefa já está atribuída a outro colaborador.');
    }
    // O Radar aloca, mas nao inicia: o primeiro inicio exige que o proprio
    // fluxo atomico receba a previsao e fotografe a janela operacional.
    $sql_update = "UPDATE funcao_imagem SET colaborador_id = ? WHERE idfuncao_imagem = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param('ii', $colaborador_id, $funcao_imagem_id);
    if ($stmt->execute()) {
        // Notificação simples (se existir tabela notificacoes)
        if ($conn->query("SHOW TABLES LIKE 'notificacoes'")->num_rows > 0) {
            $msg = 'Tarefa atribuída via Flow Radar';
            $ins = $conn->prepare("insert into notificacoes_gerais (colaborador_id, mensagem, data, lida, funcao_imagem_id) VALUES (?, ?, NOW(), 0, ?)");
            if ($ins) {
                $ins->bind_param('isi', $colaborador_id, $msg, $funcao_imagem_id);
                $ins->execute();
                $ins->close();
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Tarefa atribuída. O colaborador deverá informar a previsão ao iniciar.']);
    } else {
        $conn->rollback();
        echo json_encode(['error' => 'Falha ao atribuir tarefa', 'db_error' => $stmt->error]);
    }
    $stmt->close();
    $conn->close();
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    if ($e instanceof FlowWipException) {
        http_response_code(409);
        echo json_encode(flow_wip_exception_payload($e), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    http_response_code($e instanceof DomainException ? 422 : 500);
    echo json_encode([
        'error' => 'Erro ao atribuir tarefa',
        'message' => $e->getMessage(),
        'avaliacao' => $evaluation ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

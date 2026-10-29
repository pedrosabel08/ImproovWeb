<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../helpers/motor_requisitos_helper.php';
require_once __DIR__ . '/../helpers/unidade_trabalho_helper.php';
require_once __DIR__ . '/../helpers/inicio_operacional_helper.php';
header('Content-Type: application/json');

if (empty($_SESSION['idcolaborador'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessao expirada. Faca login novamente.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);

if (!$payload || !isset($payload['funcao_ids']) || !is_array($payload['funcao_ids']) || !isset($payload['status'])) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$funcaoIds = array_values(array_unique(array_map('intval', $payload['funcao_ids'])));
$statusDestino = trim((string)$payload['status']);
$atribuirLogado = !empty($payload['atribuir_logado']);
$confirmarPendencias = !empty($payload['confirmar_pendencias']);
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
        $contextoPlanejamento = flow_planejamento_alteracao_contexto_operacional($conn, $funcaoId, true);
        if ($contextoPlanejamento) {
            $iniciar = mb_strtolower($statusDestino, 'UTF-8') === 'em andamento';
            flow_planejamento_alteracao_validar_operacao(
                $contextoPlanejamento,
                (int) $usuarioLogadoId,
                $atribuirLogado && $iniciar ? (int) $usuarioLogadoId : null,
                $iniciar
            );
            $atorUsuarioId = !empty($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : null;
            if ($iniciar) {
                if (strcasecmp((string) $contextoPlanejamento['tarefa_status'], 'Em andamento') !== 0) {
                    $ciclo = flow_janela_ciclo_ativo_por_tarefa($conn, $funcaoId, true);
                    if ($ciclo && $ciclo['situacao'] === 'PAUSADO') {
                        flow_janela_retomar($conn, $funcaoId, (int) $usuarioLogadoId, $atorUsuarioId, $contextoPlanejamento['fim_planejado']);
                        $stmtAtualizar->bind_param('si', $statusDestino, $funcaoId);
                        $stmtAtualizar->execute();
                    } else {
                        flow_inicio_operacional_iniciar($conn, [
                            'funcao_imagem_id' => $funcaoId,
                            'previsao' => $contextoPlanejamento['fim_planejado'],
                            'ator_colaborador_id' => (int) $usuarioLogadoId,
                            'ator_usuario_id' => $atorUsuarioId,
                            'nivel_acesso' => (int) ($_SESSION['nivel_acesso'] ?? 0),
                            'confirmar_pendencias' => $confirmarPendencias,
                            'origem_acionamento' => 'ALTERACOES_LOTE',
                        ]);
                    }
                }
                continue; // Never execute the legacy self-assignment below.
            }
            if ($statusDestino === 'Em aprovação') {
                flow_inicio_operacional_enviar_aprovacao($conn, [
                    'funcao_imagem_id' => $funcaoId,
                    'previsao' => $contextoPlanejamento['fim_planejado'],
                    'ator_colaborador_id' => (int) $usuarioLogadoId,
                    'ator_usuario_id' => $atorUsuarioId,
                    'nivel_acesso' => (int) ($_SESSION['nivel_acesso'] ?? 0),
                ]);
                continue;
            }
        }
        if (mb_strtolower($statusDestino, 'UTF-8') === 'em andamento') {
            $stmtAtual = $conn->prepare('SELECT idfuncao_imagem, imagem_id, funcao_id, colaborador_id, status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1 FOR UPDATE');
            $stmtAtual->bind_param('i', $funcaoId);
            $stmtAtual->execute();
            $atual = $stmtAtual->get_result()->fetch_assoc();
            $stmtAtual->close();
            if ($atual && strcasecmp((string) $atual['status'], 'Não iniciado') === 0) {
                $inicioColaboradorId = ($atribuirLogado && $usuarioLogadoId)
                    ? (int) $usuarioLogadoId
                    : (int) ($atual['colaborador_id'] ?? 0);
                $atual['colaborador_id'] = $inicioColaboradorId;
                flow_wip_assert_novo_inicio($conn, $inicioColaboradorId, $atual);
                $blockedEvaluation = motor_requisitos_avaliar_funcao_imagem($conn, $funcaoId);
                if (motor_requisitos_tem_bloqueio_producao($blockedEvaluation)) {
                    throw new DomainException('Conclua todas as pendências de Produção antes de iniciar a tarefa.');
                }
                if (!$blockedEvaluation['elegivel'] && !$confirmarPendencias) {
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
        if ($contextoPlanejamento) {
            if ($statusDestino === 'HOLD') {
                flow_janela_pausar($conn, $funcaoId, (int) $usuarioLogadoId, $atorUsuarioId);
            } else {
                flow_janela_encerrar_se_unidade_finalizada($conn, $funcaoId, (int) $usuarioLogadoId, $atorUsuarioId);
            }
        }
    }

    $stmtAtualizar->close();
    $stmtAtualizarComColab->close();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    $conn->rollback();
    if ($e instanceof FlowWipException) {
        http_response_code(409);
        echo json_encode(flow_wip_exception_payload($e), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $conn->close();
        exit;
    }
    if ($e instanceof DomainException) {
        http_response_code(422);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'avaliacao' => $blockedEvaluation]);
}

$conn->close();

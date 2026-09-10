<?php
require_once __DIR__ . '/../config/session_bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclua a conexão com o banco de dados
include('conexao.php');
require_once __DIR__ . '/../helpers/motor_requisitos_helper.php';
require_once __DIR__ . '/../helpers/funcao_imagem_prazo_helper.php';
require_once __DIR__ . '/../helpers/unidade_trabalho_helper.php';

function same_caderno_date($left, $right)
{
    $normalize = static function ($value) {
        if ($value === null || $value === '') {
            return null;
        }

        return explode(' ', trim((string) $value))[0];
    };

    return $normalize($left) === $normalize($right);
}

// Verifique se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coletando dados do formulário
    $status = $_POST['status'];
    $prazo = $_POST['prazo'];
    $idfuncao_imagem = (int) $_POST['idfuncao_imagem'];
    $actorColaboradorId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;
    $actorUsuarioId = isset($_SESSION['idusuario']) ? (int) $_SESSION['idusuario'] : null;
    $confirmarPendencias = !empty($_POST['confirmar_pendencias']);

    $conn->begin_transaction();

    try {
        $stmtCurrentPrazo = $conn->prepare("SELECT idfuncao_imagem, imagem_id, funcao_id, colaborador_id, prazo, status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1 FOR UPDATE");
        $stmtCurrentPrazo->bind_param('i', $idfuncao_imagem);
        $stmtCurrentPrazo->execute();
        $rowAtual = $stmtCurrentPrazo->get_result()->fetch_assoc();
        $stmtCurrentPrazo->close();
        if (!$rowAtual) {
            throw new DomainException('Tarefa não encontrada.');
        }
        $prazoAnterior = $rowAtual['prazo'] ?? null;
        $statusAnterior = $rowAtual['status'] ?? null;
        if (strcasecmp((string) $statusAnterior, 'Não iniciado') === 0 && strcasecmp((string) $status, 'Em andamento') === 0) {
            flow_wip_assert_novo_inicio($conn, (int) ($rowAtual['colaborador_id'] ?? 0), $rowAtual);
            $evaluation = motor_requisitos_avaliar_funcao_imagem($conn, $idfuncao_imagem);
            if (motor_requisitos_tem_bloqueio_producao($evaluation)) {
                throw new DomainException('Conclua todas as pendências de Produção antes de iniciar a tarefa.');
            }
            if (!$evaluation['elegivel'] && !$confirmarPendencias) {
                throw new DomainException('A tarefa possui requisitos pendentes para iniciar.');
            }
        }
        $prazoResult = funcao_imagem_prazo_atualizar(
            $conn,
            $idfuncao_imagem,
            $prazo,
            [
                'origem' => 'arquitetura_caderno',
                'alterado_por_colaborador_id' => $actorColaboradorId,
                'alterado_por_usuario_id' => $actorUsuarioId,
                'status_novo' => $status,
            ]
        );

        if (!$prazoResult['alterado']) {
            $stmtStatus = $conn->prepare('UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?');
            if (!$stmtStatus) {
                throw new RuntimeException('Erro de preparação da atualização de status: ' . $conn->error);
            }
            $stmtStatus->bind_param('si', $status, $idfuncao_imagem);
            if (!$stmtStatus->execute()) {
                $error = $stmtStatus->error;
                $stmtStatus->close();
                throw new RuntimeException('Erro ao atualizar status: ' . $error);
            }
            $stmtStatus->close();
        }

        $conn->commit();
        echo "Atualização feita com sucesso!";
    } catch (Throwable $e) {
        $conn->rollback();
        if ($e instanceof FlowWipException) {
            http_response_code(409);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(flow_wip_exception_payload($e), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $conn->close();
            exit;
        }
        echo "Erro ao atualizar: " . $e->getMessage();
    }
}

// Fecha a conexão
$conn->close();

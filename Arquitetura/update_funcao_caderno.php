<?php
require_once __DIR__ . '/../config/session_bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclua a conexão com o banco de dados
include('conexao.php');
require_once __DIR__ . '/../helpers/motor_requisitos_helper.php';
require_once __DIR__ . '/../helpers/funcao_imagem_prazo_helper.php';

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

    $prazoAnterior  = null;
    $statusAnterior = null;
    $stmtCurrentPrazo = $conn->prepare("SELECT prazo, status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1");
    $stmtCurrentPrazo->bind_param('i', $idfuncao_imagem);
    $stmtCurrentPrazo->execute();
    $rowAtual = $stmtCurrentPrazo->get_result()->fetch_assoc();
    $stmtCurrentPrazo->close();

    if ($rowAtual) {
        $prazoAnterior  = $rowAtual['prazo']   ?? null;
        $statusAnterior = $rowAtual['status']  ?? null;
    }
    if (
        strcasecmp((string) $statusAnterior, 'Não iniciado') === 0
        && strcasecmp((string) $status, 'Em andamento') === 0
    ) {
        $evaluation = motor_requisitos_avaliar_funcao_imagem($conn, $idfuncao_imagem);
        if (!$evaluation['elegivel'] && !$confirmarPendencias) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'A tarefa possui requisitos pendentes para iniciar.',
                'avaliacao' => $evaluation,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $conn->begin_transaction();

    try {
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
        echo "Erro ao atualizar: " . $e->getMessage();
    }
}

// Fecha a conexão
$conn->close();

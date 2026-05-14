<?php
require_once __DIR__ . '/config/session_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/conexao.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function normalize_input_date($value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    $hasErrors = is_array($errors)
        ? (!empty($errors['warning_count']) || !empty($errors['error_count']))
        : false;

    if (!$date || $hasErrors) {
        return null;
    }

    return $date->format('Y-m-d');
}

function is_manager_id(int $colaboradorId): bool
{
    return in_array($colaboradorId, [9, 21], true);
}

if (!isset($_SESSION['idcolaborador'])) {
    json_response(401, [
        'success' => false,
        'message' => 'Usuário não autenticado.',
    ]);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    json_response(400, [
        'success' => false,
        'message' => 'Payload inválido.',
    ]);
}

$items = $payload['items'] ?? null;
if (!is_array($items) || count($items) === 0) {
    json_response(422, [
        'success' => false,
        'message' => 'Nenhuma tarefa foi enviada.',
    ]);
}

$actorColaboradorId = (int) ($_SESSION['idcolaborador'] ?? 0);
$actorUsuarioId = (int) ($_SESSION['idusuario'] ?? 0);
$today = (new DateTimeImmutable('today'))->format('Y-m-d');

try {
    $conn->begin_transaction();

    $selectStmt = $conn->prepare(
        'SELECT idfuncao_imagem, colaborador_id, status, prazo, observacao
         FROM funcao_imagem
         WHERE idfuncao_imagem = ?
         LIMIT 1'
    );

    $updateHoldStmt = $conn->prepare(
        "UPDATE funcao_imagem
         SET status = 'HOLD', observacao = ?
         WHERE idfuncao_imagem = ?"
    );

    $updateContinuePrazoStmt = $conn->prepare(
        "UPDATE funcao_imagem
         SET status = 'Em andamento', prazo = ?
         WHERE idfuncao_imagem = ?"
    );

    $updateContinueStatusStmt = $conn->prepare(
        "UPDATE funcao_imagem
         SET status = 'Em andamento'
         WHERE idfuncao_imagem = ?"
    );

    $insertHistoryStmt = $conn->prepare(
        'INSERT INTO funcao_imagem_prazo_historico (
            funcao_imagem_id,
            prazo_anterior,
            prazo_novo,
            alterado_por_colaborador_id,
            alterado_por_usuario_id,
            origem,
            motivo,
            status_anterior,
            status_novo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $updatedIds = [];
    $historyCount = 0;

    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Item inválido na posição ' . ($index + 1) . '.');
        }

        $idFuncaoImagem = isset($item['idfuncao_imagem']) ? (int) $item['idfuncao_imagem'] : 0;
        $status = strtolower(trim((string) ($item['status'] ?? '')));
        $observacao = trim((string) ($item['obs'] ?? $item['observacao'] ?? ''));
        $novoPrazo = normalize_input_date($item['prazo_novo'] ?? null);
        $motivo = trim((string) ($item['motivo'] ?? ''));
        $motivo = $motivo !== '' ? $motivo : null;

        if ($idFuncaoImagem <= 0) {
            throw new InvalidArgumentException('Tarefa inválida na posição ' . ($index + 1) . '.');
        }

        if (!in_array($status, ['continuar', 'hold'], true)) {
            throw new InvalidArgumentException('Status inválido para a tarefa ' . $idFuncaoImagem . '.');
        }

        $selectStmt->bind_param('i', $idFuncaoImagem);
        $selectStmt->execute();
        $current = $selectStmt->get_result()->fetch_assoc();

        if (!$current) {
            throw new InvalidArgumentException('Tarefa não encontrada: ' . $idFuncaoImagem . '.');
        }

        $ownerColaboradorId = (int) ($current['colaborador_id'] ?? 0);
        if ($ownerColaboradorId !== $actorColaboradorId && !is_manager_id($actorColaboradorId)) {
            throw new RuntimeException('Você não tem permissão para atualizar a tarefa ' . $idFuncaoImagem . '.');
        }

        $prazoAtual = normalize_input_date($current['prazo'] ?? null);
        $prazoEmAtrasoOuAusente = !$prazoAtual || $prazoAtual < $today;

        if ($status === 'hold') {
            if ($observacao === '') {
                throw new InvalidArgumentException('Informe a observação para a tarefa em HOLD ' . $idFuncaoImagem . '.');
            }

            $updateHoldStmt->bind_param('si', $observacao, $idFuncaoImagem);
            $updateHoldStmt->execute();
            $updatedIds[] = $idFuncaoImagem;
            continue;
        }

        if ($prazoEmAtrasoOuAusente && !$novoPrazo) {
            throw new InvalidArgumentException('Informe o novo prazo para a tarefa ' . $idFuncaoImagem . '.');
        }

        if ($novoPrazo && $novoPrazo < $today) {
            throw new InvalidArgumentException('O novo prazo da tarefa ' . $idFuncaoImagem . ' deve ser hoje ou uma data futura.');
        }

        if ($novoPrazo) {
            $updateContinuePrazoStmt->bind_param('si', $novoPrazo, $idFuncaoImagem);
            $updateContinuePrazoStmt->execute();

            if ($prazoAtual !== $novoPrazo) {
                $origem = 'primeiro_acesso';
                $statusAnteriorLoop = $current['status'];
                $statusNovoLoop     = 'Em andamento';
                $insertHistoryStmt->bind_param(
                    'issiissss',
                    $idFuncaoImagem,
                    $prazoAtual,
                    $novoPrazo,
                    $actorColaboradorId,
                    $actorUsuarioId,
                    $origem,
                    $motivo,
                    $statusAnteriorLoop,
                    $statusNovoLoop
                );
                $insertHistoryStmt->execute();
                $historyCount++;
            }
        } else {
            $updateContinueStatusStmt->bind_param('i', $idFuncaoImagem);
            $updateContinueStatusStmt->execute();
        }

        $updatedIds[] = $idFuncaoImagem;
    }

    $conn->commit();

    json_response(200, [
        'success' => true,
        'updated_ids' => $updatedIds,
        'history_count' => $historyCount,
    ]);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    json_response(422, [
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

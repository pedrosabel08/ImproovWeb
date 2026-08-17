<?php

const DEADLINE_TENTATIVA_AGUARDANDO_JOB = 'AGUARDANDO_JOB';
const DEADLINE_TENTATIVA_VINCULADA = 'VINCULADA';
const DEADLINE_TENTATIVA_EM_ANDAMENTO = 'EM_ANDAMENTO';
const DEADLINE_TENTATIVA_EM_APROVACAO = 'EM_APROVACAO';
const DEADLINE_TENTATIVA_ERRO = 'ERRO';
const DEADLINE_TENTATIVA_APROVADA = 'APROVADA';
const DEADLINE_TENTATIVA_REPROVADA = 'REPROVADA';
const DEADLINE_TENTATIVA_REFAZENDO = 'REFAZENDO';
const DEADLINE_TENTATIVA_EXCLUSAO_PENDENTE = 'EXCLUSAO_PENDENTE';
const DEADLINE_TENTATIVA_ENCERRADA = 'ENCERRADA';
const DEADLINE_TENTATIVA_CANCELADA = 'CANCELADA';
const DEADLINE_TENTATIVA_CONCLUIDA_MANUALMENTE = 'CONCLUIDA_MANUALMENTE';
const DEADLINE_COMANDO_DELETE_JOB = 'DELETE_JOB';
const DEADLINE_COMANDO_PENDENTE = 'PENDENTE';

function deadline_flow_schema_ready(mysqli $conn): bool
{
    $sql = "SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN (
                  'render_tentativas', 'deadline_comandos',
                  'deadline_workers', 'render_tentativa_eventos'
              )";
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    if (!$row || (int) $row['total'] !== 4) {
        return false;
    }
    $columnResult = $conn->query(
        "SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'render_alta'
           AND COLUMN_NAME = 'excluido_em'"
    );
    $columnRow = $columnResult ? $columnResult->fetch_assoc() : null;
    return $columnRow && (int) $columnRow['total'] === 1;
}

function deadline_flow_require_schema(mysqli $conn): void
{
    if (!deadline_flow_schema_ready($conn)) {
        throw new RuntimeException(
            'A migration sql/2026-07-13_deadline_continuous_worker.sql ainda nao foi aplicada.'
        );
    }
}

function deadline_flow_valid_job_id(?string $jobId): ?string
{
    $jobId = trim((string) $jobId);
    return preg_match('/^[a-f0-9]{24}$/i', $jobId) ? $jobId : null;
}

function deadline_flow_lock_render(mysqli $conn, int $renderId): array
{
    $stmt = $conn->prepare(
        "SELECT idrender_alta, imagem_id, status_id, status, responsavel_id, deadline_job_id
         FROM render_alta
         WHERE idrender_alta = ?
         FOR UPDATE"
    );
    $stmt->bind_param('i', $renderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        throw new RuntimeException('Render nao encontrado.');
    }
    return $row;
}

function deadline_flow_lock_active_attempt(mysqli $conn, int $renderId): ?array
{
    $stmt = $conn->prepare(
        "SELECT *
         FROM render_tentativas
         WHERE render_id = ? AND ativa = 1
         ORDER BY numero_tentativa DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bind_param('i', $renderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function deadline_flow_lock_latest_attempt(mysqli $conn, int $renderId): ?array
{
    $stmt = $conn->prepare(
        "SELECT *
         FROM render_tentativas
         WHERE render_id = ?
         ORDER BY numero_tentativa DESC
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bind_param('i', $renderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function deadline_flow_next_attempt_number(mysqli $conn, int $renderId): int
{
    $stmt = $conn->prepare(
        'SELECT COALESCE(MAX(numero_tentativa), 0) AS numero
         FROM render_tentativas
         WHERE render_id = ?'
    );
    $stmt->bind_param('i', $renderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ((int) ($row['numero'] ?? 0)) + 1;
}

function deadline_flow_create_attempt(
    mysqli $conn,
    array $render,
    int $numero,
    string $status = DEADLINE_TENTATIVA_AGUARDANDO_JOB,
    bool $ativa = true,
    ?string $jobId = null,
    ?string $motivo = null
): int {
    $jobId = deadline_flow_valid_job_id($jobId);
    $ativaInt = $ativa ? 1 : 0;
    $stmt = $conn->prepare(
        "INSERT INTO render_tentativas
            (render_id, imagem_id, status_id, numero_tentativa, deadline_job_id, status, ativa,
             motivo_encerramento, vinculado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, IF(? IS NULL, NULL, NOW()))"
    );
    $renderId = (int) $render['idrender_alta'];
    $imagemId = (int) $render['imagem_id'];
    $statusId = (int) $render['status_id'];
    $stmt->bind_param(
        'iiiississ',
        $renderId,
        $imagemId,
        $statusId,
        $numero,
        $jobId,
        $status,
        $ativaInt,
        $motivo,
        $jobId
    );
    $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

function deadline_flow_ensure_initial_attempt(mysqli $conn, int $renderId): int
{
    deadline_flow_require_schema($conn);
    $render = deadline_flow_lock_render($conn, $renderId);

    // Tentativas encerradas pertencem a ciclos anteriores e nao podem ser
    // reutilizadas como tentativa operacional atual.
    $activeAttempt = deadline_flow_lock_active_attempt($conn, $renderId);
    if ($activeAttempt) {
        return (int) $activeAttempt['id'];
    }

    return deadline_flow_create_attempt(
        $conn,
        $render,
        deadline_flow_next_attempt_number($conn, $renderId)
    );
}

function deadline_flow_reactivate_archived_locked(
    mysqli $conn,
    int $renderId,
    int $responsavelId
): int {
    deadline_flow_require_schema($conn);
    $render = deadline_flow_lock_render($conn, $renderId);
    if ((string) $render['status'] !== 'Arquivado') {
        throw new RuntimeException('O render existente nao esta arquivado.');
    }
    if (deadline_flow_lock_active_attempt($conn, $renderId)) {
        throw new RuntimeException('Render arquivado ainda possui tentativa ativa.');
    }

    $newAttemptId = deadline_flow_create_attempt(
        $conn,
        $render,
        deadline_flow_next_attempt_number($conn, $renderId)
    );
    $status = 'Não iniciado';
    $stmt = $conn->prepare(
        "UPDATE render_alta
         SET status = ?, responsavel_id = ?, excluido_em = NULL, data = NOW()
         WHERE idrender_alta = ?"
    );
    $stmt->bind_param('sii', $status, $responsavelId, $renderId);
    $stmt->execute();
    $stmt->close();
    return $newAttemptId;
}

function deadline_flow_enqueue_delete(
    mysqli $conn,
    array $render,
    int $attemptId,
    string $jobId,
    int $priority = 50
): array {
    $jobId = deadline_flow_valid_job_id($jobId);
    if ($jobId === null) {
        return ['created' => false, 'id' => null, 'status' => null];
    }

    $stmt = $conn->prepare(
        "INSERT INTO deadline_comandos
            (tipo, render_id, tentativa_id, imagem_id, deadline_job_id, status, prioridade)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            atualizado_em = CURRENT_TIMESTAMP,
            id = LAST_INSERT_ID(id)"
    );
    $tipo = DEADLINE_COMANDO_DELETE_JOB;
    $status = DEADLINE_COMANDO_PENDENTE;
    $renderId = (int) $render['idrender_alta'];
    $imagemId = (int) $render['imagem_id'];
    $stmt->bind_param('siiissi', $tipo, $renderId, $attemptId, $imagemId, $jobId, $status, $priority);
    $stmt->execute();
    $created = $stmt->affected_rows === 1;
    $commandId = (int) $conn->insert_id;
    $stmt->close();
    $stmt = $conn->prepare('SELECT status FROM deadline_comandos WHERE id = ?');
    $stmt->bind_param('i', $commandId);
    $stmt->execute();
    $commandRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return [
        'created' => $created,
        'id' => $commandId,
        'status' => $commandRow['status'] ?? $status,
    ];
}

function deadline_flow_manual_eligible_status(string $status): bool
{
    $notStarted = 'N' . "\xC3\xA3" . 'o iniciado';
    return in_array($status, [$notStarted, 'Nao iniciado', 'Em andamento', 'Erro', 'Reprovado', 'Refazendo'], true);
}

function deadline_flow_approval_status(): string
{
    return "Em aprova\xC3\xA7\xC3\xA3o";
}

/**
 * Ends the current Deadline attempt because the output was produced outside
 * Deadline. The render itself remains awaiting business approval.
 *
 * The caller owns the surrounding transaction and persists the audit record.
 */
function deadline_flow_complete_manually_locked(
    mysqli $conn,
    int $renderId,
    int $actorId,
    bool $actorIsManager,
    string $reason
): array {
    deadline_flow_require_schema($conn);

    $render = deadline_flow_lock_render($conn, $renderId);
    if (!deadline_flow_manual_eligible_status((string) $render['status'])) {
        throw new RuntimeException('Este render nao pode ser concluido manualmente no status atual.');
    }
    if (!$actorIsManager && $actorId !== (int) ($render['responsavel_id'] ?? 0)) {
        throw new RuntimeException('Apenas o responsavel pelo render ou um gestor pode concluir manualmente.');
    }

    $attempt = deadline_flow_lock_active_attempt($conn, $renderId);
    if (!$attempt) {
        deadline_flow_ensure_initial_attempt($conn, $renderId);
        $attempt = deadline_flow_lock_active_attempt($conn, $renderId);
    }
    if (!$attempt) {
        throw new RuntimeException('Tentativa ativa nao encontrada para o render.');
    }

    $attemptId = (int) $attempt['id'];
    $jobId = deadline_flow_valid_job_id($attempt['deadline_job_id'] ?? null);
    $previousStatus = (string) $render['status'];
    $closeReason = 'CONCLUIDA_MANUALMENTE';
    $stmt = $conn->prepare(
        "UPDATE render_tentativas
         SET status = ?, ativa = 0,
             concluido_em = COALESCE(concluido_em, NOW()),
             encerrado_em = COALESCE(encerrado_em, NOW()),
             motivo_encerramento = ?
         WHERE id = ? AND ativa = 1"
    );
    $manualStatus = DEADLINE_TENTATIVA_CONCLUIDA_MANUALMENTE;
    $stmt->bind_param('ssi', $manualStatus, $closeReason, $attemptId);
    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        $stmt->close();
        throw new RuntimeException('A tentativa do render mudou antes da conclusao manual.');
    }
    $stmt->close();

    $command = ['created' => false, 'id' => null, 'status' => null];
    if ($jobId !== null) {
        $command = deadline_flow_enqueue_delete($conn, $render, $attemptId, $jobId, 80);
    }

    $stmt = $conn->prepare(
        'UPDATE render_alta SET status = ?, data = NOW() WHERE idrender_alta = ?'
    );
    $approvalStatus = deadline_flow_approval_status();
    $stmt->bind_param('si', $approvalStatus, $renderId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Nao foi possivel enviar o render para aprovacao: ' . $error);
    }
    $stmt->close();

    $eventData = json_encode([
        'colaborador_id' => $actorId,
        'status_anterior' => $previousStatus,
        'justificativa' => $reason,
        'deadline_job_id' => $jobId,
    ], JSON_UNESCAPED_UNICODE);
    $stmt = $conn->prepare(
        "INSERT INTO render_tentativa_eventos (tentativa_id, tipo, chave, dados_json)
         VALUES (?, 'CONCLUSAO_MANUAL', 'CONFIRMADA', ?)"
    );
    $stmt->bind_param('is', $attemptId, $eventData);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Nao foi possivel registrar o evento de conclusao manual: ' . $error);
    }
    $stmt->close();

    return [
        'render' => $render,
        'tentativa_id' => $attemptId,
        'status_anterior' => $previousStatus,
        'deadline_job_id' => $jobId,
        'command' => $command,
    ];
}

function deadline_flow_rework_locked(mysqli $conn, int $renderId, string $flowStatus): array
{
    deadline_flow_require_schema($conn);
    $render = deadline_flow_lock_render($conn, $renderId);
    $attempt = deadline_flow_lock_active_attempt($conn, $renderId);
    $attemptWasActive = $attempt !== null;
    if (!$attempt) {
        // Backfill de renders terminais gera tentativa inativa. Ao solicitar
        // refacao, reutilize essa tentativa historica em vez de duplicar o Job ID.
        $attempt = deadline_flow_lock_latest_attempt($conn, $renderId);
        if (!$attempt) {
            $initialId = deadline_flow_create_attempt(
                $conn,
                $render,
                deadline_flow_next_attempt_number($conn, $renderId),
                DEADLINE_TENTATIVA_VINCULADA,
                true,
                $render['deadline_job_id'] ?? null,
                'RECUPERADA_NO_REWORK'
            );
            $attempt = deadline_flow_lock_active_attempt($conn, $renderId);
            if (!$attempt || (int) $attempt['id'] !== $initialId) {
                throw new RuntimeException('Nao foi possivel preparar a tentativa atual.');
            }
            $attemptWasActive = true;
        }
    }

    // A tentativa ativa e a fonte de verdade. O cache do render pode ainda
    // apontar para uma tentativa anterior aguardando exclusao.
    $jobId = deadline_flow_valid_job_id($attempt['deadline_job_id'] ?? null);
    $attemptId = (int) $attempt['id'];
    $attemptAlreadyClosed = in_array(
        (string) $attempt['status'],
        [DEADLINE_TENTATIVA_ENCERRADA, DEADLINE_TENTATIVA_CANCELADA],
        true
    );
    $terminalStatus = strtolower($flowStatus) === 'refazendo'
        ? DEADLINE_TENTATIVA_REFAZENDO
        : DEADLINE_TENTATIVA_REPROVADA;

    if ($jobId !== null && !$attemptAlreadyClosed) {
        $stmt = $conn->prepare(
            "UPDATE render_tentativas
             SET status = ?, ativa = 0, motivo_encerramento = ?, reprovado_em = NOW()
             WHERE id = ?"
        );
        $pending = DEADLINE_TENTATIVA_EXCLUSAO_PENDENTE;
        $motivo = $terminalStatus . '_NO_FLOW';
        $stmt->bind_param('ssi', $pending, $motivo, $attemptId);
        $stmt->execute();
        $stmt->close();
        $command = deadline_flow_enqueue_delete($conn, $render, $attemptId, $jobId);
    } elseif ($attemptWasActive) {
        $stmt = $conn->prepare(
            "UPDATE render_tentativas
             SET status = ?, ativa = 0, motivo_encerramento = ?,
                 reprovado_em = NOW(), encerrado_em = NOW()
             WHERE id = ?"
        );
        $motivo = $terminalStatus . '_SEM_JOB';
        $stmt->bind_param('ssi', $terminalStatus, $motivo, $attemptId);
        $stmt->execute();
        $stmt->close();
        $command = ['created' => false, 'id' => null, 'status' => null];
    } else {
        // Tentativa historica ja terminal e sem job operacional: preserve-a.
        $command = ['created' => false, 'id' => null, 'status' => null];
        $jobId = null;
    }

    $nextNumber = deadline_flow_next_attempt_number($conn, $renderId);
    $newAttemptId = deadline_flow_create_attempt($conn, $render, $nextNumber);

    $stmt = $conn->prepare('UPDATE render_alta SET status = ?, data = NOW() WHERE idrender_alta = ?');
    $stmt->bind_param('si', $flowStatus, $renderId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('UPDATE pos_producao SET status_pos = 1 WHERE render_id = ?');
    $stmt->bind_param('i', $renderId);
    $stmt->execute();
    $stmt->close();

    return [
        'render_id' => $renderId,
        'tentativa_encerrada_id' => $attemptId,
        'nova_tentativa_id' => $newAttemptId,
        'deadline_job_id' => $jobId,
        'deadline_command_created' => $command['created'],
        'deadline_command_id' => $command['id'],
        'deadline_command_status' => $command['status'],
    ];
}

function deadline_flow_approve_locked(mysqli $conn, int $renderId): array
{
    deadline_flow_require_schema($conn);

    $render = deadline_flow_lock_render($conn, $renderId);

    if (!$render) {
        throw new RuntimeException(
            "Render {$renderId} não encontrado ou não pôde ser bloqueado."
        );
    }

    $attempt = deadline_flow_lock_active_attempt($conn, $renderId);

    if (!$attempt) {
        $latestAttempt = deadline_flow_lock_latest_attempt($conn, $renderId);
        if ($latestAttempt && (string) $latestAttempt['status'] === DEADLINE_TENTATIVA_CONCLUIDA_MANUALMENTE) {
            $attempt = $latestAttempt;
        } else {
            deadline_flow_ensure_initial_attempt($conn, $renderId);
            $attempt = deadline_flow_lock_active_attempt($conn, $renderId);
        }
    }

    if (!$attempt) {
        throw new RuntimeException(
            "Tentativa ativa não encontrada para o render {$renderId}."
        );
    }

    $attemptId = (int) ($attempt['id'] ?? 0);

    if ($attemptId <= 0) {
        throw new RuntimeException(
            'A tentativa ativa retornou um ID inválido.'
        );
    }

    $jobId = deadline_flow_valid_job_id(
        $attempt['deadline_job_id'] ?? null
    );

    if ((string) $attempt['status'] === DEADLINE_TENTATIVA_CONCLUIDA_MANUALMENTE) {
        $stmt = $conn->prepare(
            "UPDATE render_tentativas
             SET status = ?, ativa = 0,
                 concluido_em = COALESCE(concluido_em, NOW()),
                 encerrado_em = COALESCE(encerrado_em, NOW()),
                 motivo_encerramento = COALESCE(motivo_encerramento, 'CONCLUIDA_MANUALMENTE')
             WHERE id = ?"
        );
        if (!$stmt) {
            throw new RuntimeException('Erro ao preparar aprovacao da tentativa manual: ' . $conn->error);
        }
        $approved = DEADLINE_TENTATIVA_APROVADA;
        $stmt->bind_param('si', $approved, $attemptId);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Erro ao aprovar a tentativa manual: ' . $error);
        }
        $stmt->close();

        // A exclusao pode ainda estar em fila. A chave unica torna esta garantia idempotente.
        $command = $jobId !== null
            ? deadline_flow_enqueue_delete($conn, $render, $attemptId, $jobId, 80)
            : ['created' => false, 'id' => null, 'status' => null];

        return [
            'tentativa_id' => $attemptId,
            'deadline_job_id' => $jobId,
            'command' => $command,
        ];
    }

    if ($jobId !== null) {
        $stmt = $conn->prepare(
            "UPDATE render_tentativas
             SET status = ?,
                 ativa = 0,
                 concluido_em = COALESCE(concluido_em, NOW()),
                 motivo_encerramento = 'APROVADA_NO_FLOW'
             WHERE id = ?"
        );

        if (!$stmt) {
            throw new RuntimeException(
                'Erro ao preparar encerramento da tentativa: ' .
                    $conn->error
            );
        }

        $pending = DEADLINE_TENTATIVA_EXCLUSAO_PENDENTE;
        $stmt->bind_param('si', $pending, $attemptId);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'Erro ao encerrar tentativa vinculada ao Deadline: ' .
                    $error
            );
        }

        if ($stmt->affected_rows === 0) {
            $stmt->close();

            throw new RuntimeException(
                "Nenhuma tentativa foi atualizada para o ID {$attemptId}."
            );
        }

        $stmt->close();

        $command = deadline_flow_enqueue_delete(
            $conn,
            $render,
            $attemptId,
            $jobId,
            80
        );
    } else {
        $stmt = $conn->prepare(
            "UPDATE render_tentativas
             SET status = ?,
                 ativa = 0,
                 concluido_em = COALESCE(concluido_em, NOW()),
                 encerrado_em = NOW(),
                 motivo_encerramento = 'APROVADA_SEM_JOB'
             WHERE id = ?"
        );

        if (!$stmt) {
            throw new RuntimeException(
                'Erro ao preparar aprovação sem job: ' .
                    $conn->error
            );
        }

        $approved = DEADLINE_TENTATIVA_APROVADA;
        $stmt->bind_param('si', $approved, $attemptId);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'Erro ao aprovar tentativa sem job: ' . $error
            );
        }

        if ($stmt->affected_rows === 0) {
            $stmt->close();

            throw new RuntimeException(
                "Nenhuma tentativa foi aprovada para o ID {$attemptId}."
            );
        }

        $stmt->close();

        $command = [
            'created' => false,
            'id' => null,
            'status' => null,
        ];
    }

    return [
        'tentativa_id' => $attemptId,
        'deadline_job_id' => $jobId,
        'command' => $command,
    ];
}

function deadline_flow_archive_locked(mysqli $conn, int $renderId): array
{
    deadline_flow_require_schema($conn);
    $render = deadline_flow_lock_render($conn, $renderId);
    $stmt = $conn->prepare(
        "SELECT * FROM render_tentativas
         WHERE render_id = ? AND status <> 'ENCERRADA'
         ORDER BY numero_tentativa
         FOR UPDATE"
    );
    $stmt->bind_param('i', $renderId);
    $stmt->execute();
    $attempts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $commands = 0;
    foreach ($attempts as $attempt) {
        $attemptId = (int) $attempt['id'];
        $jobId = deadline_flow_valid_job_id($attempt['deadline_job_id']);
        if ($jobId !== null) {
            $stmt = $conn->prepare(
                "UPDATE render_tentativas
                 SET status = ?, ativa = 0, motivo_encerramento = 'RENDER_ARQUIVADO'
                 WHERE id = ?"
            );
            $pending = DEADLINE_TENTATIVA_EXCLUSAO_PENDENTE;
            $stmt->bind_param('si', $pending, $attemptId);
            $stmt->execute();
            $stmt->close();
            $queued = deadline_flow_enqueue_delete($conn, $render, $attemptId, $jobId, 40);
            if ($queued['created']) {
                $commands++;
            }
        } else {
            $stmt = $conn->prepare(
                "UPDATE render_tentativas
                 SET status = ?, ativa = 0, motivo_encerramento = 'RENDER_ARQUIVADO', encerrado_em = NOW()
                 WHERE id = ?"
            );
            $cancelled = DEADLINE_TENTATIVA_CANCELADA;
            $stmt->bind_param('si', $cancelled, $attemptId);
            $stmt->execute();
            $stmt->close();
        }
    }

    $stmt = $conn->prepare(
        "UPDATE render_alta SET status = 'Arquivado', excluido_em = NOW(), data = NOW()
         WHERE idrender_alta = ?"
    );
    $stmt->bind_param('i', $renderId);
    $stmt->execute();
    $stmt->close();
    return ['render_id' => $renderId, 'deadline_commands_created' => $commands];
}

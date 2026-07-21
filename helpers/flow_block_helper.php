<?php
/**
 * Shared domain rules for Flow Block.
 *
 * An Issue is intentionally scoped to one funcao_imagem.  HOLD is a derived
 * task state, never an independently editable workflow in this module.
 */

if (!function_exists('flow_block_active_statuses')) {
    function flow_block_active_statuses(): array
    {
        return ['ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA'];
    }
}

if (!function_exists('flow_block_is_manager')) {
    function flow_block_is_manager(): bool
    {
        $id = (int) ($_SESSION['idcolaborador'] ?? 0);
        $nivel = (int) ($_SESSION['nivel_acesso'] ?? 0);
        return $nivel === 1 || in_array($id, [1, 9, 21], true);
    }
}

if (!function_exists('flow_block_actor_id')) {
    function flow_block_actor_id(): int
    {
        return (int) ($_SESSION['idcolaborador'] ?? 0);
    }
}

if (!function_exists('flow_block_ensure_authenticated')) {
    function flow_block_ensure_authenticated(): void
    {
        if (empty($_SESSION['logado']) || !flow_block_actor_id()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'Usuário não autenticado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

if (!function_exists('flow_block_json_response')) {
    function flow_block_json_response(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('flow_block_read_json')) {
    function flow_block_read_json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $value = json_decode($raw, true);
        if (!is_array($value)) {
            flow_block_json_response(['ok' => false, 'message' => 'Dados inválidos.'], 422);
        }
        return $value;
    }
}

if (!function_exists('flow_block_has_tables')) {
    function flow_block_has_tables(mysqli $conn): bool
    {
        $result = $conn->query("SHOW TABLES LIKE 'flow_issue'");
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('flow_block_task')) {
    function flow_block_task(mysqli $conn, int $taskId): ?array
    {
        $stmt = $conn->prepare(
            'SELECT fi.idfuncao_imagem, fi.colaborador_id, fi.status, fi.prazo, fi.observacao,
                    fi.funcao_id, f.nome_funcao, ico.imagem_nome, ico.obra_id,
                    o.nomenclatura, o.nome_obra
             FROM funcao_imagem fi
             JOIN funcao f ON f.idfuncao = fi.funcao_id
             JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
             JOIN obra o ON o.idobra = ico.obra_id
             WHERE fi.idfuncao_imagem = ? LIMIT 1'
        );
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $task;
    }
}

if (!function_exists('flow_block_can_access_task')) {
    function flow_block_can_access_task(array $task): bool
    {
        return flow_block_is_manager() || (int) ($task['colaborador_id'] ?? 0) === flow_block_actor_id();
    }
}

if (!function_exists('flow_block_can_resolve_issue')) {
    function flow_block_can_resolve_issue(array $issue): bool
    {
        return flow_block_is_manager()
            || (int) ($issue['responsavel_colaborador_id'] ?? 0) === flow_block_actor_id();
    }
}

if (!function_exists('flow_block_can_confirm_resolution')) {
    function flow_block_can_confirm_resolution(array $issue): bool
    {
        return flow_block_is_manager()
            || (int) ($issue['tarefa_colaborador_id'] ?? 0) === flow_block_actor_id();
    }
}

if (!function_exists('flow_block_actor_was_mentioned')) {
    function flow_block_actor_was_mentioned(mysqli $conn, int $issueId): bool
    {
        $actorId = flow_block_actor_id();
        if ($actorId <= 0 || $issueId <= 0) {
            return false;
        }
        $stmt = $conn->prepare(
            'SELECT 1
             FROM flow_issue_mencao
             WHERE issue_id = ? AND colaborador_id = ?
             LIMIT 1'
        );
        $stmt->bind_param('ii', $issueId, $actorId);
        $stmt->execute();
        $allowed = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $allowed;
    }
}

if (!function_exists('flow_block_add_activity')) {
    function flow_block_add_activity(mysqli $conn, int $issueId, string $type, ?string $content = null, array $meta = [], ?int $parentActivityId = null): void
    {
        $actor = flow_block_actor_id();
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $conn->prepare(
            'INSERT INTO flow_issue_atividade (issue_id, tipo, conteudo, metadados, criado_por_colaborador_id, atividade_pai_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssii', $issueId, $type, $content, $metaJson, $actor, $parentActivityId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('flow_block_notify')) {
    function flow_block_notify(mysqli $conn, int $recipientId, int $taskId, string $message): void
    {
        if ($recipientId <= 0 || $recipientId === flow_block_actor_id()) {
            return;
        }
        $stmt = $conn->prepare(
            'INSERT INTO notificacoes_gerais (colaborador_id, mensagem, data, lida, funcao_imagem_id)
             VALUES (?, ?, NOW(), 0, ?)'
        );
        $stmt->bind_param('isi', $recipientId, $message, $taskId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('flow_block_redis_url')) {
    function flow_block_redis_url(): string
    {
        $envLoader = __DIR__ . '/../config/secure_env.php';
        if (is_file($envLoader)) {
            require_once $envLoader;
            if (function_exists('improov_load_env_once')) {
                improov_load_env_once();
            }
        }
        return getenv('REDIS_URL') ?: 'tcp://127.0.0.1:6379';
    }
}

if (!function_exists('flow_block_publish')) {
    function flow_block_publish(int $issueId, int $taskId, string $event): void
    {
        // Redis is optional in local development.  The browser remains correct
        // through the API refresh even if the realtime process is unavailable.
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!is_file($autoload)) {
            return;
        }
        try {
            require_once $autoload;
            if (!class_exists('Predis\\Client')) {
                return;
            }
            $redisUrl = flow_block_redis_url();
            $redis = new Predis\Client($redisUrl, ['timeout' => 0.2, 'read_write_timeout' => 0.2]);
            $payload = json_encode([
                'event' => $event,
                'issue_id' => $issueId,
                'funcao_imagem_id' => $taskId,
                'at' => date(DATE_ATOM),
            ], JSON_UNESCAPED_UNICODE);
            $redis->publish('flow_block:issue', $payload);
            $redis->publish('funcao_atualizada:updated', $payload);
            $redis->disconnect();
        } catch (Throwable $ignored) {
            // Realtime must not make a business action fail.
        }
    }
}

if (!function_exists('flow_block_publish_mention')) {
    /**
     * The WebSocket payload intentionally contains identifiers only.  The
     * recipient fetches the toast data through the authenticated API, keeping
     * comment content out of the broadcast channel.
     */
    function flow_block_publish_mention(array $mention): void
    {
        $mentionId = (int) ($mention['id'] ?? 0);
        $recipientId = (int) ($mention['colaborador_id'] ?? 0);
        if ($mentionId <= 0 || $recipientId <= 0) {
            return;
        }
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!is_file($autoload)) {
            return;
        }
        try {
            require_once $autoload;
            if (!class_exists('Predis\\Client')) {
                return;
            }
            $payload = json_encode([
                'event' => 'flow_block.mention.created',
                'event_id' => 'flow_block_mention_' . $mentionId,
                'mention_id' => $mentionId,
                'recipient_id' => $recipientId,
                'issue_id' => (int) ($mention['issue_id'] ?? 0),
                'activity_id' => (int) ($mention['atividade_id'] ?? 0),
                'at' => date(DATE_ATOM),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                return;
            }
            $redisUrl = flow_block_redis_url();
            $redis = new Predis\Client($redisUrl, ['timeout' => 0.2, 'read_write_timeout' => 0.2]);
            $redis->publish('flow_block:mention', $payload);
            $redis->disconnect();
        } catch (Throwable $ignored) {
            // Realtime must not make comment creation fail.
        }
    }
}

if (!function_exists('flow_block_has_blocking_issues')) {
    function flow_block_has_blocking_issues(mysqli $conn, int $taskId): bool
    {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total FROM flow_issue
             WHERE funcao_imagem_id = ? AND bloqueante = 1
               AND (
                    status IN ('ABERTA', 'AGUARDANDO_ACAO', 'PAUSADA')
                    OR (status = 'RESOLVIDA' AND confirmada_em IS NULL)
               )"
        );
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $total = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
        $stmt->close();

        return $total > 0;
    }
}

if (!function_exists('flow_block_task_ready_to_continue')) {
    function flow_block_task_ready_to_continue(mysqli $conn, int $taskId): bool
    {
        if (flow_block_has_blocking_issues($conn, $taskId)) {
            return false;
        }

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total FROM flow_issue
             WHERE funcao_imagem_id = ? AND bloqueante = 1
               AND (status = 'CANCELADA' OR (status = 'RESOLVIDA' AND confirmada_em IS NOT NULL))"
        );
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $total = (int) (($stmt->get_result()->fetch_assoc()['total'] ?? 0));
        $stmt->close();

        return $total > 0;
    }
}

if (!function_exists('flow_block_refresh_task_status')) {
    /**
     * Uma Issue bloqueante sempre força HOLD. A liberação é deliberada e só
     * ocorre em continue_task, após a reprogramação obrigatória da tarefa.
     */
    function flow_block_refresh_task_status(mysqli $conn, int $taskId): bool
    {
        $hasBlockingIssues = flow_block_has_blocking_issues($conn, $taskId);

        if ($hasBlockingIssues) {
            $update = $conn->prepare("UPDATE funcao_imagem SET status = 'HOLD' WHERE idfuncao_imagem = ?");
            $update->bind_param('i', $taskId);
            $update->execute();
            $update->close();
            return false;
        }

        return false;
    }
}

if (!function_exists('flow_block_duration_label')) {
    function flow_block_duration_label(?string $startedAt, ?string $endedAt = null): string
    {
        if (!$startedAt) {
            return '—';
        }
        try {
            $start = new DateTimeImmutable($startedAt);
            $end = $endedAt ? new DateTimeImmutable($endedAt) : new DateTimeImmutable('now');
            $minutes = max(0, (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60));
            $days = intdiv($minutes, 1440);
            $hours = intdiv($minutes % 1440, 60);
            $mins = $minutes % 60;
            if ($days > 0) return $days . 'd ' . $hours . 'h';
            if ($hours > 0) return $hours . 'h ' . $mins . 'min';
            return $mins . 'min';
        } catch (Throwable $e) {
            return '—';
        }
    }
}

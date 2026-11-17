<?php

require_once __DIR__ . '/render_ef_helpers.php';

function render_recovery_ready(mysqli $conn): bool
{
    return (bool) render_ef_rows($conn, "SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='deadline_recovery_requests'");
}

function render_recovery_task_summary(mysqli $conn, int $render, string $job): ?array
{
    try {
        if (!render_recovery_ready($conn)) {
            return null;
        }
        $row = render_ef_rows($conn, "SELECT finished_at,
                JSON_EXTRACT(result_json,'$.state.tasks') AS tasks
            FROM deadline_recovery_requests WHERE job_id=? AND render_id=?
                AND action='inspect' AND status='succeeded'
            ORDER BY id DESC LIMIT 1", 'si', [$job, $render])[0] ?? null;
        if (!$row) {
            return null;
        }
        $tasks = json_decode($row['tasks'] ?? '', true);
        if (!is_array($tasks)) {
            return null;
        }
        $counts = array_count_values(array_column($tasks, 'status'));
        return ['running' => $counts['rendering'] ?? 0, 'failed' => $counts['failed'] ?? 0,
            'observed_at' => $row['finished_at']];
    } catch (Throwable $error) {
        error_log('[Render] Falha ao consultar o resumo de tarefas: ' . $error->getMessage());
        return null;
    }
}

function render_recovery_api(mysqli $conn): void
{
    $reply = static function (array $body, int $code = 200): void {
        http_response_code($code);
        header('Cache-Control: no-store');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    };
    try {
        $method = $_SERVER['REQUEST_METHOD'];
        if (!in_array($method, ['GET', 'POST'], true)) {
            $reply(['message' => 'Método inválido.'], 405);
            return;
        }
        $user = (int) ($_SESSION['idusuario'] ?? 0);
        $actor = (int) ($_SESSION['idcolaborador'] ?? 0);
        $roles = array_column(render_ef_rows($conn, 'SELECT c.nome FROM usuario_cargo uc
            JOIN cargo c ON c.id=uc.cargo_id WHERE uc.usuario_id=?', 'i', [$user]), 'nome');
        if (!$user || !$actor || !render_ef_manager($_SESSION, $roles)) {
            $reply(['message' => 'A recuperação é restrita a gestores de Render.'], 403);
            return;
        }
        $input = $method === 'POST' ? json_decode(file_get_contents('php://input', false, null, 0, 262144), true) : $_GET;
        if ($method === 'POST' && (empty($_SESSION['render_ef_csrf']) ||
            !hash_equals($_SESSION['render_ef_csrf'], $_SERVER['HTTP_X_RENDER_CSRF'] ?? ''))) {
            $reply(['message' => 'Validação da sessão inválida. Reabra a página.'], 403);
            return;
        }
        session_write_close();
        $render = filter_var($input['render_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $job = is_string($input['job_id'] ?? null) ? deadline_flow_valid_job_id(strtolower($input['job_id'])) : null;
        if (!$render || !$job) {
            $reply(['message' => 'Selecione um job válido.'], 422);
            return;
        }
        if (!render_ef_rows(
            $conn,
            'SELECT 1 FROM render_alta r WHERE r.idrender_alta=?
            AND r.excluido_em IS NULL AND (LOWER(r.deadline_job_id)=? OR EXISTS (
                SELECT 1 FROM render_tentativas t WHERE t.render_id=r.idrender_alta AND LOWER(t.deadline_job_id)=?))',
            'iss',
            [$render, $job, $job]
        )) {
            $reply(['message' => 'O job não pertence ao render selecionado.'], 404);
            return;
        }
        if (!render_recovery_ready($conn)) {
            $reply(['message' => 'A recuperação ainda não foi habilitada neste ambiente.'], 503);
            return;
        }
        if ($method === 'GET') {
            $id = filter_var($input['request'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $request = $id ? (render_ef_rows($conn, 'SELECT id,status,result_json,finished_at FROM deadline_recovery_requests
                WHERE id=? AND render_id=? AND job_id=? AND user_id=?', 'iisi', [$id, $render, $job, $user])[0] ?? null)
                : (render_ef_rows($conn, "SELECT id,status,result_json,finished_at FROM deadline_recovery_requests
                    WHERE render_id=? AND job_id=? AND user_id=? AND status IN ('queued','running')
                    LIMIT 1", 'isi', [$render, $job, $user])[0] ?? null);
            if ($id && !$request) {
                $reply(['message' => 'Solicitação não encontrada.'], 404);
                return;
            }
            if ($request) {
                $result = json_decode($request['result_json'] ?? '', true) ?: [];
                $request['result'] = array_intersect_key($result, array_flip(['state', 'message', 'incompatible_ram', 'new_job_id', 'attempt']));
                unset($request['result_json']);
                if (in_array($request['status'], ['queued', 'running'], true)) {
                    $reply(['request' => $request]);
                    return;
                }
            }
            $history = render_ef_rows($conn, "SELECT r.id,r.user_id,r.collaborator_id,c.nome_colaborador AS actor_name,
                r.action,r.status,r.params_json,r.created_at,r.finished_at,
                JSON_UNQUOTE(JSON_EXTRACT(r.result_json,'$.message')) AS message,
                JSON_UNQUOTE(JSON_EXTRACT(r.result_json,'$.new_job_id')) AS new_job_id
                FROM deadline_recovery_requests r LEFT JOIN colaborador c ON c.idcolaborador=r.collaborator_id
                WHERE r.job_id=? AND r.render_id=? AND r.action<>'inspect'
                ORDER BY r.id DESC LIMIT 50", 'si', [$job, $render]);
            $evidence = [];
            require_once __DIR__ . '/deadline_diagnostic_flow.php';
            if (render_diagnostic_ready($conn)) {
                $evidence = render_ef_rows($conn, 'SELECT report_id,worker,task_id,occurred_at,evidence_json,diagnosis_json
                    FROM deadline_diagnostic_occurrences WHERE job_id=?
                    ORDER BY occurred_at DESC,id DESC LIMIT 50', 's', [$job]);
            }
            $reply(['request' => $request, 'history' => $history, 'evidence' => $evidence]);
            return;
        }
        $action = $input['operation'] ?? null;
        $params = $input['params'] ?? [];
        $source = null;
        if (!in_array($action, ['inspect', 'deny', 'retry', 'suspend', 'resume', 'route', 'resubmit'], true) || !is_array($params)) {
            $reply(['message' => 'Ação de recuperação inválida.'], 422);
            return;
        }
        if ($action !== 'inspect') {
            $source = filter_var($input['source'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (($input['confirmed'] ?? false) !== true || !$source ||
                !render_ef_rows($conn, "SELECT 1 FROM deadline_recovery_requests WHERE id=? AND job_id=?
                    AND render_id=? AND user_id=? AND action='inspect' AND status='succeeded'
                    AND finished_at >= UTC_TIMESTAMP() - INTERVAL 5 MINUTE", 'isii', [$source, $job, $render, $user])) {
                $reply(['message' => 'Atualize a consulta e confirme a ação.'], 409);
                return;
            }
        }
        $name = static fn ($value) => is_string($value) && strlen($value) <= 160 && !preg_match('/[\x00-\x1f\x7f]/', $value);
        $valid = match ($action) {
            'deny' => $name($params['worker'] ?? null) && preg_match('/^[a-z0-9_][a-z0-9_.-]*$/iD', $params['worker'])
                && is_bool($params['blocked'] ?? null) && count($params) === 2,
            'retry' => count($params) === 1 && is_array($params['tasks'] ?? null) && array_is_list($params['tasks'])
                && count($params['tasks']) > 0 && count($params['tasks']) <= 10000
                && !array_filter($params['tasks'], static fn ($id) => !is_int($id) || $id < 0 || $id > 2147483647),
            'route' => count($params) === 2 && $name($params['pool'] ?? null) && $name($params['group'] ?? null),
            'resubmit' => count($params) === 4 && $name($params['name'] ?? null) && trim($params['name']) !== ''
                && is_string($params['frames'] ?? null) && strlen($params['frames']) <= 1000
                && preg_match('/^-?\d+(?:\s*-\s*-?\d+(?:[xX]\d+)?)?(?:\s*,\s*-?\d+(?:\s*-\s*-?\d+(?:[xX]\d+)?)?)*$/D', trim($params['frames']))
                && is_int($params['chunk'] ?? null) && $params['chunk'] > 0 && $params['chunk'] <= 2147483647
                && is_bool($params['suspended'] ?? null),
            default => !$params,
        };
        if (!$valid) {
            $reply(['message' => 'Parâmetros inválidos para esta ação.'], 422);
            return;
        }
        render_ef_exec(
            $conn,
            "INSERT INTO deadline_recovery_requests
            (render_id,job_id,user_id,collaborator_id,action,source_id,params_json,created_at)
            VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP())",
            'isiisis',
            [$render, $job, $user, $actor, $action, $source, json_encode($params, JSON_THROW_ON_ERROR)]
        );
        $reply(['id' => $conn->insert_id], 202);
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            $reply(['message' => 'Já existe uma solicitação para este job ou esta confirmação já foi usada.'], 409);
            return;
        }
        error_log('Render recovery: ' . $e->getMessage());
        $reply(['message' => 'A recuperação está indisponível.'], 503);
    } catch (Throwable $e) {
        error_log('Render recovery: ' . $e->getMessage());
        $reply(['message' => 'Não foi possível processar esta solicitação.'], 500);
    }
}

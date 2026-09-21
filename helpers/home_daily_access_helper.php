<?php

/**
 * Marca a primeira entrada diária sem depender do navegador e publica o
 * mesmo tipo de entrega Slack utilizado pela Daily histórica.
 */
function home_daily_register_first_access(mysqli $conn, int $userId, int $collaboratorId, string $userName): array
{
    if ($userId <= 0) {
        return ['first_access' => false, 'notification_queued' => false];
    }

    $firstAccess = false;
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'SELECT id FROM logs_usuarios
              WHERE usuario_id = ?
                AND last_panel_shown_date IS NOT NULL
                AND DATE(last_panel_shown_date) = CURDATE()
              LIMIT 1 FOR UPDATE'
        );
        if (!$stmt) {
            throw new RuntimeException('Não foi possível consultar o acesso diário.');
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $alreadySeen = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$alreadySeen) {
            $stmt = $conn->prepare('UPDATE logs_usuarios SET last_panel_shown_date = CURDATE() WHERE usuario_id = ?');
            if (!$stmt) {
                throw new RuntimeException('Não foi possível registrar o acesso diário.');
            }
            $stmt->bind_param('i', $userId);
            if (!$stmt->execute()) {
                throw new RuntimeException('Não foi possível registrar o acesso diário.');
            }
            $stmt->close();
            $firstAccess = true;
        }
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }

    if (!$firstAccess) {
        return ['first_access' => false, 'notification_queued' => false];
    }

    $queued = false;
    try {
        require_once __DIR__ . '/../config/secure_env.php';
        require_once __DIR__ . '/../FlowConnect/bootstrap.php';
        $webhook = improov_env('SLACK_WEBHOOK_DAILY_URL', null);
        if ($webhook) {
            $message = '*' . ($userName !== '' ? $userName : 'Colaborador') . '* entrou no Flow pela primeira vez hoje.';
            $logs = [];
            $eventId = flow_connect_publish_legacy_immediate(
                $conn,
                'respostas_diarias',
                'resposta_diaria.primeiro_acesso',
                'acesso_diario',
                $userId . ':' . date('Y-m-d'),
                $message,
                $collaboratorId ?: null,
                'SLACK_WEBHOOK_DAILY_URL',
                'home:first-access:' . $userId . ':' . date('Y-m-d'),
                $logs
            );
            if (!flow_connect_legacy_should_bypass('respostas_diarias', $eventId) && function_exists('curl_init')) {
                $request = curl_init($webhook);
                curl_setopt_array($request, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode(['text' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ]);
                curl_exec($request);
                if (curl_errno($request)) {
                    error_log('Home first access Slack: ' . curl_error($request));
                }
                curl_close($request);
            }
            $queued = true;
        }
    } catch (Throwable $error) {
        // O acesso diário já foi registrado. Falhas no Slack não podem impedir
        // o uso do Flow nem provocar uma segunda notificação no mesmo dia.
        error_log('Home first access Slack: ' . $error->getMessage());
    }

    return ['first_access' => true, 'notification_queued' => $queued];
}

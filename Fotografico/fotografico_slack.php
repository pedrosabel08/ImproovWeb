<?php

declare(strict_types=1);

/** Envio best-effort de DMs do Fotográfico. Nunca deve desfazer uma transação. */
function fotografico_slack_log(string $message): void
{
    error_log('[Fotografico/Slack] ' . $message);
}

function fotografico_slack_token(): string
{
    $token = trim((string) (getenv('SLACK_TOKEN') ?: ''));
    if ($token !== '') {
        return $token;
    }

    // As rotas HTTP locais nem sempre recebem as variaveis do agendador.
    // Reaproveita o arquivo de ambiente usado pelos scripts legados, sem
    // sobrescrever uma variavel definida no processo.
    $envPath = __DIR__ . '/../scripts/.env';
    if (!is_file($envPath)) {
        return '';
    }
    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ((array) $lines as $line) {
        if (preg_match('/^\s*SLACK_TOKEN\s*=\s*(.*?)\s*$/', (string) $line, $matches) !== 1) {
            continue;
        }
        $token = trim((string) $matches[1], " \t\r\n\"'");
        if ($token !== '') {
            putenv('SLACK_TOKEN=' . $token);
            return $token;
        }
    }
    return '';
}

function fotografico_slack_user_id(mysqli $conn, int $colaboradorId, string $token): ?string
{
    static $resolved = [];
    if (array_key_exists($colaboradorId, $resolved)) {
        return $resolved[$colaboradorId];
    }
    $stmt = $conn->prepare('SELECT nome_slack FROM usuario WHERE idcolaborador = ? AND ativo = 1 LIMIT 1');
    if (!$stmt) {
        return $resolved[$colaboradorId] = null;
    }
    $stmt->bind_param('i', $colaboradorId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $identifier = trim((string) ($row['nome_slack'] ?? ''));
    if ($identifier === '') {
        fotografico_slack_log("colaborador={$colaboradorId} sem nome_slack configurado");
        return $resolved[$colaboradorId] = null;
    }
    if (preg_match('/^[UW][A-Z0-9]+$/', $identifier) === 1) {
        return $resolved[$colaboradorId] = $identifier;
    }
    if (!function_exists('curl_init')) {
        fotografico_slack_log('curl indisponível para resolver destinatário');
        return $resolved[$colaboradorId] = null;
    }
    $request = curl_init('https://slack.com/api/users.list?limit=200');
    curl_setopt_array($request, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => 800,
        CURLOPT_TIMEOUT_MS => 2500,
    ]);
    $raw = curl_exec($request);
    $http = (int) curl_getinfo($request, CURLINFO_HTTP_CODE);
    curl_close($request);
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if ($http >= 400 || empty($payload['ok'])) {
        fotografico_slack_log("não foi possível resolver Slack de colaborador={$colaboradorId}");
        return $resolved[$colaboradorId] = null;
    }
    foreach ((array) ($payload['members'] ?? []) as $member) {
        $display = (string) ($member['profile']['display_name'] ?? '');
        $real = (string) ($member['profile']['real_name'] ?? '');
        if (mb_strtolower($identifier) === mb_strtolower($display) || mb_strtolower($identifier) === mb_strtolower($real)) {
            return $resolved[$colaboradorId] = ((string) ($member['id'] ?? '') ?: null);
        }
    }
    fotografico_slack_log("nome_slack não resolvido para colaborador={$colaboradorId}");
    return $resolved[$colaboradorId] = null;
}

function fotografico_slack_enviar_dm(mysqli $conn, int $colaboradorId, string $message): bool
{
    $token = fotografico_slack_token();
    if ($token === '') {
        fotografico_slack_log('SLACK_TOKEN ausente; DM não enviada');
        return false;
    }
    $slackUserId = fotografico_slack_user_id($conn, $colaboradorId, $token);
    if ($slackUserId === null || !function_exists('curl_init')) {
        return false;
    }
    $request = curl_init('https://slack.com/api/chat.postMessage');
    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['channel' => $slackUserId, 'text' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => 800,
        CURLOPT_TIMEOUT_MS => 2500,
    ]);
    $raw = curl_exec($request);
    $http = (int) curl_getinfo($request, CURLINFO_HTTP_CODE);
    $error = curl_error($request);
    curl_close($request);
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if ($http >= 400 || empty($payload['ok'])) {
        fotografico_slack_log("falha ao enviar DM para colaborador={$colaboradorId}: http={$http} erro={$error}");
        return false;
    }
    fotografico_slack_log("DM enviada para colaborador={$colaboradorId}");
    return true;
}

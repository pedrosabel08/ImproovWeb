<?php

/**
 * Best-effort publication of Render domain events. The database transaction
 * must already be committed before this helper is called.
 */
function notifyRenderUpdate(string $event, array $payload = []): void
{
    $channel = 'render:updated';
    $configuredRedisUrl = getenv('REDIS_URL') ?: '(não definida; Predis usará a conexão padrão)';
    try {
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }
        if (!class_exists('\\Predis\\Client')) {
            error_log('[WS-DIAG][Render][PHP] Predis\\Client indisponível; publish não executado. REDIS_URL=' . $configuredRedisUrl);
            return;
        }

        $message = array_merge([
            'version' => 1,
            'event' => $event,
            'ts' => time(),
        ], $payload);
        $encodedMessage = json_encode($message);
        error_log('[WS-DIAG][Render][PHP] publish.in REDIS_URL=' . $configuredRedisUrl . ' host=127.0.0.1 porta=6379 canal=' . $channel . ' payload=' . $encodedMessage);

        // Mantém a mesma construção existente: sem parâmetros, Predis usa 127.0.0.1:6379.
        $redis = new \Predis\Client();
        $publishResult = $redis->publish($channel, $encodedMessage);
        error_log('[WS-DIAG][Render][PHP] publish.out canal=' . $channel . ' retorno=' . var_export($publishResult, true));
    } catch (Throwable $e) {
        error_log('[WS-DIAG][Render][PHP] publish.error REDIS_URL=' . $configuredRedisUrl . ' canal=' . $channel . ' exception=' . $e->getMessage() . ' trace=' . $e->getTraceAsString());
        // Redis is an interface acceleration only; never fail a committed action.
    }
}

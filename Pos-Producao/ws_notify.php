<?php
/**
 * Notifica todos os clientes WebSocket conectados que a tabela
 * pos_producao foi alterada, via Redis pub/sub.
 */
function notifyPosProducaoUpdate(string $event = 'updated', array $payload = []): void
{
    $channel = 'pos_producao:updated';
    $configuredRedisUrl = getenv('REDIS_URL') ?: '(não definida; Predis usará a conexão padrão)';
    try {
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }
        if (!class_exists('\Predis\Client')) {
            error_log('[WS-DIAG][Pós][PHP] Predis\\Client indisponível; publish não executado. REDIS_URL=' . $configuredRedisUrl);
            return;
        }

        $message = array_merge([
            'version' => 1,
            'event' => $event,
            'ts' => time(),
        ], $payload);
        $encodedMessage = json_encode($message);
        error_log('[WS-DIAG][Pós][PHP] publish.in REDIS_URL=' . $configuredRedisUrl . ' host=127.0.0.1 porta=6379 canal=' . $channel . ' payload=' . $encodedMessage);

        $redis = new \Predis\Client();
        $publishResult = $redis->publish($channel, $encodedMessage);
        error_log('[WS-DIAG][Pós][PHP] publish.out canal=' . $channel . ' retorno=' . var_export($publishResult, true));
    } catch (\Throwable $e) {
        error_log('[WS-DIAG][Pós][PHP] publish.error REDIS_URL=' . $configuredRedisUrl . ' canal=' . $channel . ' exception=' . $e->getMessage() . ' trace=' . $e->getTraceAsString());
        // Silencioso — não bloquear a resposta por falha no Redis
    }
}

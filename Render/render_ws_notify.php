<?php

/**
 * Best-effort publication of Render domain events. The database transaction
 * must already be committed before this helper is called.
 */
function notifyRenderUpdate(string $event, array $payload = []): void
{
    $channel = 'render:updated';

    try {
        $autoload = __DIR__ . '/../vendor/autoload.php';

        if (file_exists($autoload)) {
            require_once $autoload;
        }

        if (!class_exists(\Predis\Client::class)) {
            error_log(
                '[WS-DIAG][Render][PHP] Predis\\Client indisponível.'
            );
            return;
        }

        $message = array_merge([
            'version' => 1,
            'event' => $event,
            'ts' => time(),
        ], $payload);

        $encodedMessage = json_encode(
            $message,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($encodedMessage === false) {
            throw new RuntimeException(
                'Falha ao serializar evento: ' . json_last_error_msg()
            );
        }

        $redisUrl = getenv('REDIS_URL') ?: 'tcp://127.0.0.1:6379';

        error_log(
            '[WS-DIAG][Render][PHP] publish.in' .
            ' redis=' . $redisUrl .
            ' canal=' . $channel .
            ' payload=' . $encodedMessage
        );

        $redis = new \Predis\Client($redisUrl, [
            'timeout' => 2,
            'read_write_timeout' => 2,
        ]);

        $publishResult = $redis->publish($channel, $encodedMessage);

        error_log(
            '[WS-DIAG][Render][PHP] publish.out' .
            ' canal=' . $channel .
            ' subscribers=' . var_export($publishResult, true)
        );
    } catch (Throwable $e) {
        error_log(
            '[WS-DIAG][Render][PHP] publish.error' .
            ' canal=' . $channel .
            ' exception=' . $e->getMessage()
        );

        // O WebSocket não deve invalidar uma alteração já salva no banco.
    }
}
<?php

declare(strict_types=1);

/** Publica uma alteração já confirmada sem interferir na transação principal. */
function fotografico_notify_update(string $event, array $payload = []): void
{
    try {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
        if (!class_exists(\Predis\Client::class)) {
            return;
        }
        $message = array_merge([
            'version' => 1,
            'event_id' => bin2hex(random_bytes(12)),
            'event' => $event,
            'source' => 'fotografico',
            'occurred_at' => date(DATE_ATOM),
            'actor_id' => fotografico_actor_id(),
        ], $payload);
        $configuredTimeout = (float) (getenv('FOTOGRAFICO_REDIS_TIMEOUT') ?: 0.05);
        $timeout = max(0.01, min(0.10, $configuredTimeout));
        $redis = new \Predis\Client(getenv('REDIS_URL') ?: 'tcp://127.0.0.1:6379', [
            // O canal e best-effort: a requisicao ja foi confirmada no banco.
            'timeout' => $timeout,
            'read_write_timeout' => $timeout,
        ]);
        $redis->publish('fotografico:updated', json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } catch (Throwable $e) {
        error_log('[Fotografico][WS] ' . $e->getMessage());
    }
}

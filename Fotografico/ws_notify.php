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
        $redis = new \Predis\Client(getenv('REDIS_URL') ?: 'tcp://127.0.0.1:6379', [
            'timeout' => 1,
            'read_write_timeout' => 1,
        ]);
        $redis->publish('fotografico:updated', json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } catch (Throwable $e) {
        error_log('[Fotografico][WS] ' . $e->getMessage());
    }
}

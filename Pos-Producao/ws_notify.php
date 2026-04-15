<?php
/**
 * Notifica todos os clientes WebSocket conectados que a tabela
 * pos_producao foi alterada, via Redis pub/sub.
 */
function notifyPosProducaoUpdate(): void
{
    try {
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }
        if (!class_exists('\Predis\Client')) return;
        $redis = new \Predis\Client();
        $redis->publish('pos_producao:updated', json_encode(['ts' => time()]));
    } catch (\Exception $e) {
        // Silencioso — não bloquear a resposta por falha no Redis
    }
}

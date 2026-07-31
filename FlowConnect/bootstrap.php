<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/secure_env.php';
improov_load_env_once();
require_once __DIR__ . '/config/feature_flags.php';

if (!defined('FLOW_CONNECT_BOOTSTRAPPED')) {
    define('FLOW_CONNECT_BOOTSTRAPPED', true);
    spl_autoload_register(static function (string $class): void {
        $prefix = 'FlowConnect\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        $segments = explode('/', str_replace('\\', '/', $relative));
        $segments[0] = strtolower($segments[0]);
        $path = __DIR__ . '/' . implode('/', $segments) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
}

if (!function_exists('flow_connect_config')) {
    function flow_connect_config(): array
    {
        static $config;
        if ($config === null) {
            $config = require __DIR__ . '/config/flow_connect.php';
        }
        return $config;
    }
}

if (!function_exists('flow_connect_request_correlation_id')) {
    function flow_connect_request_correlation_id(): string
    {
        static $correlationId;
        if ($correlationId === null) {
            $candidate = $_SERVER['HTTP_X_CORRELATION_ID'] ?? null;
            $correlationId = \FlowConnect\Contracts\EventEnvelope::validUuid($candidate)
                ? $candidate
                : \FlowConnect\Contracts\EventEnvelope::uuidV4();
        }
        return $correlationId;
    }
}

if (!function_exists('flow_connect_publish_in_transaction')) {
    function flow_connect_publish_in_transaction(mysqli $conn, array $event): int
    {
        return (new \FlowConnect\Application\EventPublisher())->publishInTransaction($conn, $event);
    }
}

if (!function_exists('flow_connect_publish_if_enabled')) {
    function flow_connect_publish_if_enabled(mysqli $conn, string $family, array $event, array &$logs = []): int
    {
        $mode = flow_connect_review_mode($family);
        if ($mode === 'off') {
            return 0;
        }
        $event['metadata'] = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        $event['metadata']['flow_connect_mode'] = $mode;
        try {
            return flow_connect_publish_in_transaction($conn, $event);
        } catch (Throwable $e) {
            $logs[] = 'flow_connect_publish_failed:' . preg_replace('/[^a-z0-9_.:-]/i', '_', substr($e->getMessage(), 0, 120));
            return 0;
        }
    }
}

if (!function_exists('flow_connect_safe_error')) {
    function flow_connect_safe_error(string $message, string $fallback = 'operation_failed'): string
    {
        $message = trim(preg_replace('/\s+/u', ' ', $message) ?? '');
        $message = preg_replace('~(?:[A-Za-z]:)?[/\\\\][^\s]+~', '[path]', $message) ?? $message;
        $message = preg_replace('/(?:token|password|senha|pass)\s*[=:]\s*\S+/i', '$1=[redacted]', $message) ?? $message;
        $message = mb_substr($message, 0, 240, 'UTF-8');
        return $message !== '' ? $message : $fallback;
    }
}

if (!function_exists('flow_connect_publish_legacy_immediate')) {
    function flow_connect_publish_legacy_immediate(mysqli $conn, string $module, string $eventType, string $entityType, string|int $entityId, string $message, ?int $recipientId, ?string $webhookEnv, string $idempotencyKey, array &$logs = []): int
    {
        $mode = flow_connect_normalize_mode(getenv('FLOW_CONNECT_' . strtoupper(str_replace('-', '_', $module)) . '_MODE') ?: 'off');
        if ($mode === 'off') return 0;
        $event = \FlowConnect\Application\LegacyImmediateEventFactory::make($eventType, $entityType, $entityId, ['message' => $message], $recipientId, $webhookEnv, $idempotencyKey, $module);
        $event['metadata']['flow_connect_mode'] = $mode;
        try {
            return flow_connect_publish_in_transaction($conn, $event);
        } catch (Throwable $e) {
            $logs[] = 'flow_connect_publish_failed:' . flow_connect_safe_error($e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('flow_connect_legacy_should_bypass')) {
    function flow_connect_legacy_should_bypass(string $module, int $publishedEventId): bool
    {
        $mode = flow_connect_normalize_mode(getenv('FLOW_CONNECT_' . strtoupper(str_replace('-', '_', $module)) . '_MODE') ?: 'off');
        return $publishedEventId > 0 && $mode === 'active';
    }
}

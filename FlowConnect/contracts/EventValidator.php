<?php

declare(strict_types=1);

namespace FlowConnect\Contracts;

use InvalidArgumentException;

final class EventValidator
{
    private const REQUIRED = [
        'event_type', 'event_version', 'source_module', 'entity_type', 'entity_id',
        'actor_id', 'occurred_at', 'event_uuid', 'correlation_id',
        'causation_event_uuid', 'idempotency_key', 'payload', 'metadata',
    ];

    private const PRODUCER_FORBIDDEN = [
        'text', 'blocks', 'attachments', 'webhook', 'channel_id', 'slack_user_id', 'token',
    ];

    public static function validate(array $event): void
    {
        foreach (self::REQUIRED as $field) {
            if (!array_key_exists($field, $event)) {
                throw new InvalidArgumentException("Campo obrigatório ausente no evento: {$field}");
            }
        }

        if (!preg_match('/^[a-z0-9_]+\.[a-z0-9_]+\.[a-z0-9_]+$/', (string) $event['event_type'])) {
            throw new InvalidArgumentException('event_type fora do padrão modulo.entidade_ou_processo.acao.');
        }
        if (!preg_match('/^[a-z0-9_-]+$/', (string) $event['source_module'])) {
            throw new InvalidArgumentException('O núcleo inicial aceita somente source_module=flow_review.');
        }
        if ((int) $event['event_version'] < 1 || trim((string) $event['entity_type']) === '' || trim((string) $event['entity_id']) === '') {
            throw new InvalidArgumentException('Versão e entidade do evento são obrigatórias.');
        }
        if (!EventEnvelope::validUuid($event['event_uuid']) || !EventEnvelope::validUuid($event['correlation_id'])) {
            throw new InvalidArgumentException('event_uuid e correlation_id devem ser UUIDs válidos.');
        }
        if ($event['causation_event_uuid'] !== null && !EventEnvelope::validUuid($event['causation_event_uuid'])) {
            throw new InvalidArgumentException('causation_event_uuid inválido.');
        }
        if (strlen((string) $event['idempotency_key']) < 8 || strlen((string) $event['idempotency_key']) > 255) {
            throw new InvalidArgumentException('idempotency_key deve ter entre 8 e 255 caracteres.');
        }
        if (!is_array($event['payload']) || !is_array($event['metadata'])) {
            throw new InvalidArgumentException('payload e metadata devem ser objetos estruturados.');
        }
        self::assertNoForbiddenFields($event['payload']);
        self::assertNoForbiddenFields($event['metadata']);

        $definitions = array_merge(require dirname(__DIR__) . '/config/events/flow_review.php', require dirname(__DIR__) . '/config/events/immediate_legacy.php');
        $definition = $definitions[$event['event_type']] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException('Evento FlowReview não catalogado: ' . $event['event_type']);
        }
        foreach ($definition['required_payload'] ?? [] as $field) {
            if (!array_key_exists($field, $event['payload'])) {
                throw new InvalidArgumentException("Payload de {$event['event_type']} sem {$field}.");
            }
        }

        $payloadJson = json_encode($event['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($payloadJson) > 65535) {
            throw new InvalidArgumentException('Payload excede o limite seguro de 64 KiB.');
        }
    }

    private static function assertNoForbiddenFields(array $data): void
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::PRODUCER_FORBIDDEN, true)) {
                throw new InvalidArgumentException("Campo de canal proibido no produtor: {$key}");
            }
            if (is_array($value)) {
                self::assertNoForbiddenFields($value);
            }
        }
    }
}

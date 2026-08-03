<?php

declare(strict_types=1);

namespace FlowConnect\Application;

use mysqli;

/** Persistência idempotente do ciclo; a regra de domínio continua no produtor/provider. */
final class OperationalCycleRepository
{
    public static function recordLifecycle(mysqli $conn, string $module, string $policy, string $action, string $entityType, string|int $entityId, array $payload): void
    {
        $table = $conn->query("SHOW TABLES LIKE 'flow_connect_pending_cycles'");
        if (!$table || $table->num_rows === 0) return;
        $cycleId = trim((string) ($payload['cycle_id'] ?? ''));
        if ($cycleId === '') return;
        $status = match ($action) { 'resolvida' => 'RESOLVED', 'cancelada' => 'CANCELLED', 'pausada' => 'PAUSED', default => 'ACTIVE' };
        $responsible = (int) ($payload['responsavel_id'] ?? 0) ?: null;
        $collector = (int) ($payload['responsavel_cobranca_id'] ?? 0) ?: null;
        // Dates supplied by the business modules are local São Paulo dates.  The
        // queue stores one canonical representation (UTC) so that a host with a
        // different PHP/MySQL timezone cannot advance an SLA prematurely.
        $businessTimezone = (string) ($payload['business_timezone'] ?? 'America/Sao_Paulo');
        $startedAt = self::dateOrNow((string) ($payload['started_at'] ?? ''), $businessTimezone);
        $dueAt = self::dateOrNull((string) ($payload['due_at'] ?? ''), $businessTimezone);
        $context = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $stmt = $conn->prepare("INSERT INTO flow_connect_pending_cycles
            (module_key,policy_key,entity_type,entity_id,cycle_id,status,responsavel_id,responsavel_cobranca_id,started_at,due_at,paused_at,resolved_at,cancelled_at,context_json)
            VALUES (?,?,?,?,?,?,?,?,?,?,IF(?='PAUSED',UTC_TIMESTAMP(6),NULL),IF(?='RESOLVED',UTC_TIMESTAMP(6),NULL),IF(?='CANCELLED',UTC_TIMESTAMP(6),NULL),?)
            ON DUPLICATE KEY UPDATE
              status=VALUES(status), responsavel_id=VALUES(responsavel_id), responsavel_cobranca_id=VALUES(responsavel_cobranca_id),
              due_at=IF(VALUES(status)='ACTIVE' AND paused_at IS NOT NULL,
                        DATE_ADD(COALESCE(VALUES(due_at),due_at), INTERVAL TIMESTAMPDIFF(SECOND,paused_at,UTC_TIMESTAMP()) SECOND),
                        COALESCE(VALUES(due_at),due_at)),
              paused_seconds=IF(VALUES(status)='ACTIVE' AND paused_at IS NOT NULL,
                                paused_seconds + GREATEST(0,TIMESTAMPDIFF(SECOND,paused_at,UTC_TIMESTAMP())), paused_seconds),
              context_json=VALUES(context_json), last_observed_at=UTC_TIMESTAMP(6),
              paused_at=IF(VALUES(status)='PAUSED',COALESCE(paused_at,UTC_TIMESTAMP(6)),IF(VALUES(status)='ACTIVE',NULL,paused_at)),
              resolved_at=IF(VALUES(status)='RESOLVED',COALESCE(resolved_at,UTC_TIMESTAMP(6)),resolved_at),
              cancelled_at=IF(VALUES(status)='CANCELLED',COALESCE(cancelled_at,UTC_TIMESTAMP(6)),cancelled_at)");
        $stmt->bind_param('ssssssiissssss', $module, $policy, $entityType, $entityId, $cycleId, $status, $responsible, $collector, $startedAt, $dueAt, $status, $status, $status, $context);
        $stmt->execute(); $stmt->close();
    }

    public static function closeFromProvider(mysqli $conn, int $id, string $status): void
    {
        $column = $status === 'CANCELLED' ? 'cancelled_at' : 'resolved_at';
        $stmt = $conn->prepare("UPDATE flow_connect_pending_cycles SET status=?, {$column}=COALESCE({$column},UTC_TIMESTAMP(6)), paused_at=NULL, updated_at=UTC_TIMESTAMP(6) WHERE id=? AND status IN ('ACTIVE','PAUSED')");
        $stmt->bind_param('si', $status, $id); $stmt->execute(); $stmt->close();
    }

    private static function dateOrNow(string $value, string $timezone): string { return self::dateOrNull($value, $timezone) ?? gmdate('Y-m-d H:i:s'); }
    private static function dateOrNull(string $value, string $timezone): ?string
    {
        if (trim($value) === '') return null;
        try {
            $zone = new \DateTimeZone($timezone);
            return (new \DateTimeImmutable($value, $zone))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) { return null; }
    }
}

<?php

declare(strict_types=1);

namespace FlowConnect\Infrastructure;

use mysqli;
use RuntimeException;

final class EventRepository
{
    public function __construct(private mysqli $conn, private int $claimTtlSeconds = 300) {}

    public function claimPending(int $limit, string $workerId): array
    {
        $limit = max(1, min(500, $limit));
        $this->conn->begin_transaction();
        try {
            $this->conn->query("UPDATE flow_connect_events SET status='PENDING', claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL WHERE status='PROCESSING' AND claim_expires_at < UTC_TIMESTAMP(6)");
            $result = $this->conn->query("SELECT * FROM flow_connect_events WHERE status='PENDING' ORDER BY id ASC LIMIT {$limit} FOR UPDATE SKIP LOCKED");
            if (!$result) {
                throw new RuntimeException('flow_connect_event_claim_select_failed');
            }
            $events = [];
            $ids = [];
            while ($row = $result->fetch_assoc()) {
                $events[] = $this->hydrate($row);
                $ids[] = (int) $row['id'];
            }
            if ($ids !== []) {
                $idList = implode(',', $ids);
                $workerSafe = $this->conn->real_escape_string($workerId);
                $ttl = max(30, $this->claimTtlSeconds);
                if (!$this->conn->query("UPDATE flow_connect_events SET status='PROCESSING', claimed_by='{$workerSafe}', claimed_at=UTC_TIMESTAMP(6), claim_expires_at=DATE_ADD(UTC_TIMESTAMP(6), INTERVAL {$ttl} SECOND), processing_started_at=COALESCE(processing_started_at, UTC_TIMESTAMP(6)) WHERE id IN ({$idList})")) {
                    throw new RuntimeException('flow_connect_event_claim_update_failed');
                }
            }
            $this->conn->commit();
            return $events;
        } catch (\Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /** Explicit diagnostic/test claim; keeps the normal FIFO queue untouched. */
    public function claimPendingById(int $eventId, string $workerId): array
    {
        if ($eventId <= 0) return [];
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("SELECT * FROM flow_connect_events WHERE id=? AND status='PENDING' FOR UPDATE SKIP LOCKED");
            $stmt->bind_param('i', $eventId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                $this->conn->commit();
                return [];
            }
            $ttl = max(30, $this->claimTtlSeconds);
            $safeWorker = $this->conn->real_escape_string($workerId);
            $update = $this->conn->prepare("UPDATE flow_connect_events SET status='PROCESSING', claimed_by=?, claimed_at=UTC_TIMESTAMP(6), claim_expires_at=DATE_ADD(UTC_TIMESTAMP(6), INTERVAL {$ttl} SECOND), processing_started_at=COALESCE(processing_started_at,UTC_TIMESTAMP(6)) WHERE id=? AND status='PENDING'");
            $update->bind_param('si', $safeWorker, $eventId);
            $update->execute();
            $claimed = $update->affected_rows === 1;
            $update->close();
            $this->conn->commit();
            return $claimed ? [$this->hydrate($row)] : [];
        } catch (\Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function markProcessed(int $eventId): void
    {
        $stmt = $this->conn->prepare("UPDATE flow_connect_events SET status='PROCESSED', processed_at=UTC_TIMESTAMP(6), claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL, last_error_code=NULL, last_error_safe=NULL WHERE id=?");
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $stmt->close();
    }

    public function markFailed(int $eventId, string $code, string $safeError, bool $dead): void
    {
        $status = $dead ? 'DEAD' : 'PENDING';
        $stmt = $this->conn->prepare("UPDATE flow_connect_events SET status=?, failure_count=failure_count+1, last_error_code=?, last_error_safe=?, claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL WHERE id=?");
        $stmt->bind_param('sssi', $status, $code, $safeError, $eventId);
        $stmt->execute();
        $stmt->close();
    }

    private function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['event_version'] = (int) $row['event_version'];
        $row['actor_id'] = $row['actor_id'] !== null ? (int) $row['actor_id'] : null;
        $row['payload'] = json_decode((string) $row['payload_json'], true) ?: [];
        $row['metadata'] = json_decode((string) ($row['metadata_json'] ?? ''), true) ?: [];
        return $row;
    }
}

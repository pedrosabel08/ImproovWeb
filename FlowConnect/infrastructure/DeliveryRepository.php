<?php

declare(strict_types=1);

namespace FlowConnect\Infrastructure;

use mysqli;
use RuntimeException;

final class DeliveryRepository
{
    public function __construct(private mysqli $conn, private int $claimTtlSeconds = 300)
    {
    }

    public function create(array $delivery): int
    {
        $blocks = isset($delivery['rendered_blocks']) ? json_encode($delivery['rendered_blocks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null;
        $collaboratorId = $delivery['collaborator_id'] ?? null;
        $stmt = $this->conn->prepare("INSERT INTO flow_connect_deliveries
            (notification_id, channel, destination_kind, destination_key, collaborator_id, slack_user_id, rendered_text, rendered_blocks_json, status, next_attempt_at)
            VALUES (?, 'slack', ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(6))
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        if (!$stmt) {
            throw new RuntimeException('flow_connect_delivery_prepare_failed');
        }
        $stmt->bind_param('ississss', $delivery['notification_id'], $delivery['destination_kind'], $delivery['destination_key'], $collaboratorId, $delivery['slack_user_id'], $delivery['rendered_text'], $blocks, $delivery['status']);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('flow_connect_delivery_insert_failed');
        }
        $id = (int) $this->conn->insert_id;
        $stmt->close();
        return $id;
    }

    public function claimEligible(int $limit, string $workerId): array
    {
        $limit = max(1, min(500, $limit));
        $this->conn->begin_transaction();
        try {
            $this->conn->query("UPDATE flow_connect_deliveries SET status=IF(attempt_count>0,'RETRY_WAIT','PENDING'), claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL WHERE status='SENDING' AND claim_expires_at < UTC_TIMESTAMP(6)");
            $result = $this->conn->query("SELECT d.*
                FROM flow_connect_deliveries d
                INNER JOIN flow_connect_notifications n ON n.id=d.notification_id
                WHERE d.status IN ('PENDING','RETRY_WAIT')
                  AND n.delivery_mode <> 'SHADOW'
                  AND (d.next_attempt_at IS NULL OR d.next_attempt_at <= UTC_TIMESTAMP(6))
                ORDER BY d.id ASC LIMIT {$limit} FOR UPDATE SKIP LOCKED");
            if (!$result) {
                throw new RuntimeException('flow_connect_delivery_claim_select_failed');
            }
            $rows = [];
            $ids = [];
            while ($row = $result->fetch_assoc()) {
                $row['id'] = (int) $row['id'];
                $row['attempt_count'] = (int) $row['attempt_count'];
                $row['rendered_blocks'] = json_decode((string) ($row['rendered_blocks_json'] ?? ''), true);
                $rows[] = $row;
                $ids[] = (int) $row['id'];
            }
            if ($ids !== []) {
                $safeWorker = $this->conn->real_escape_string($workerId);
                $ttl = max(30, $this->claimTtlSeconds);
                $idList = implode(',', $ids);
                $this->conn->query("UPDATE flow_connect_deliveries SET status='SENDING', claimed_by='{$safeWorker}', claimed_at=UTC_TIMESTAMP(6), claim_expires_at=DATE_ADD(UTC_TIMESTAMP(6), INTERVAL {$ttl} SECOND) WHERE id IN ({$idList})");
            }
            $this->conn->commit();
            return $rows;
        } catch (\Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    public function completeAttempt(array $delivery, array $result, array $decision): void
    {
        $this->conn->begin_transaction();
        try {
            $attemptNo = (int) $delivery['attempt_count'] + 1;
            $stmt = $this->conn->prepare("INSERT INTO flow_connect_delivery_attempts
                (delivery_id, attempt_no, started_at, finished_at, http_status, provider_message_id, provider_error_code, error_safe, request_fingerprint)
                VALUES (?, ?, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), ?, ?, ?, ?, ?)");
            $fingerprint = hash('sha256', (string) $delivery['destination_key'] . '|' . (string) $delivery['rendered_text']);
            $httpStatus = $result['http_status'] ?? null;
            $providerId = $result['provider_message_id'] ?? null;
            $errorCode = $result['error_code'] ?? null;
            $safeError = $result['safe_error'] ?? null;
            $stmt->bind_param('iiissss', $delivery['id'], $attemptNo, $httpStatus, $providerId, $errorCode, $safeError, $fingerprint);
            $stmt->execute();
            $stmt->close();

            $status = $decision['status'];
            $nextAt = $decision['next_attempt_at'] ?? null;
            $sentAtSql = $status === 'SENT' ? 'UTC_TIMESTAMP(6)' : 'sent_at';
            $up = $this->conn->prepare("UPDATE flow_connect_deliveries SET status=?, attempt_count=?, next_attempt_at=?, sent_at={$sentAtSql}, last_error_code=?, last_error_safe=?, claimed_by=NULL, claimed_at=NULL, claim_expires_at=NULL, updated_at=UTC_TIMESTAMP(6) WHERE id=?");
            $up->bind_param('sisssi', $status, $attemptNo, $nextAt, $errorCode, $safeError, $delivery['id']);
            $up->execute();
            $up->close();

            $notificationId = (int) $delivery['notification_id'];
            $this->conn->query("UPDATE flow_connect_notifications n
                SET n.status = CASE
                    WHEN EXISTS (SELECT 1 FROM flow_connect_deliveries d WHERE d.notification_id=n.id AND d.status IN ('PENDING','SENDING','RETRY_WAIT')) THEN 'READY'
                    WHEN EXISTS (SELECT 1 FROM flow_connect_deliveries d WHERE d.notification_id=n.id AND d.status IN ('DEAD','UNRESOLVED')) THEN 'ERROR'
                    ELSE 'COMPLETED' END,
                    n.completed_at = CASE
                        WHEN EXISTS (SELECT 1 FROM flow_connect_deliveries d WHERE d.notification_id=n.id AND d.status IN ('PENDING','SENDING','RETRY_WAIT')) THEN NULL
                        ELSE UTC_TIMESTAMP(6) END
                WHERE n.id={$notificationId}");
            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
}

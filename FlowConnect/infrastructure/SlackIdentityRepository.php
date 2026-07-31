<?php

declare(strict_types=1);

namespace FlowConnect\Infrastructure;

use mysqli;

final class SlackIdentityRepository
{
    public function __construct(private mysqli $conn)
    {
    }

    public function findActiveByCollaborator(int $collaboratorId): ?array
    {
        $stmt = $this->conn->prepare("SELECT colaborador_id, slack_user_id, slack_display_name, slack_real_name, status FROM flow_connect_slack_identities WHERE colaborador_id=? AND status='ACTIVE' AND slack_user_id IS NOT NULL AND TRIM(slack_user_id) <> '' LIMIT 1");
        $stmt->bind_param('i', $collaboratorId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public function upsert(int $collaboratorId, ?string $slackUserId, ?string $displayName, ?string $realName, string $status, string $source): void
    {
        $stmt = $this->conn->prepare("INSERT INTO flow_connect_slack_identities (colaborador_id, slack_user_id, slack_display_name, slack_real_name, status, source, last_synced_at)
            VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(6))
            ON DUPLICATE KEY UPDATE slack_user_id=VALUES(slack_user_id), slack_display_name=VALUES(slack_display_name), slack_real_name=VALUES(slack_real_name), status=VALUES(status), source=VALUES(source), last_synced_at=VALUES(last_synced_at)");
        $stmt->bind_param('isssss', $collaboratorId, $slackUserId, $displayName, $realName, $status, $source);
        $stmt->execute();
        $stmt->close();
    }
}

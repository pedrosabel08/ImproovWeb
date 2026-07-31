<?php

declare(strict_types=1);

namespace FlowConnect\Application;

final class RetryPolicy
{
    public function decide(array $result, int $attemptCount): array
    {
        if (!empty($result['ok'])) {
            return ['status' => 'SENT', 'next_attempt_at' => null];
        }
        $attemptNo = $attemptCount + 1;
        $permanent = !empty($result['permanent']);
        if ($permanent || $attemptNo >= 6) {
            return ['status' => 'DEAD', 'next_attempt_at' => null];
        }
        $retryAfter = (int) ($result['retry_after'] ?? 0);
        $delay = $retryAfter > 0 ? $retryAfter : min(3600, (2 ** max(0, $attemptNo - 1)) * 30);
        return ['status' => 'RETRY_WAIT', 'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + $delay)];
    }
}

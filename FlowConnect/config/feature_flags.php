<?php

declare(strict_types=1);

if (!function_exists('flow_connect_normalize_mode')) {
    function flow_connect_normalize_mode($mode): string
    {
        $mode = strtolower(trim((string) $mode));
        return in_array($mode, ['off', 'shadow', 'active'], true) ? $mode : 'off';
    }
}

if (!function_exists('flow_connect_review_mode')) {
    function flow_connect_review_mode(string $family): string
    {
        $specific = [
            'mention' => 'FLOW_CONNECT_REVIEW_MENTION_MODE',
            'angle' => 'FLOW_CONNECT_REVIEW_ANGLE_MODE',
            'task' => 'FLOW_CONNECT_REVIEW_TASK_MODE',
            'direction' => 'FLOW_CONNECT_REVIEW_DIRECTION_MODE',
            'sftp' => 'FLOW_CONNECT_REVIEW_SFTP_MODE',
            'sla' => 'FLOW_CONNECT_REVIEW_SLA_MODE',
        ];

        $specificKey = $specific[$family] ?? null;
        if ($specificKey !== null) {
            $specificValue = getenv($specificKey);
            if ($specificValue !== false && trim((string) $specificValue) !== '') {
                return flow_connect_normalize_mode($specificValue);
            }
        }

        $general = getenv('FLOW_CONNECT_FLOW_REVIEW_MODE');
        if ($general !== false && trim((string) $general) !== '') {
            return flow_connect_normalize_mode($general);
        }

        return 'off';
    }
}

if (!function_exists('flow_connect_should_bypass_legacy')) {
    function flow_connect_should_bypass_legacy(string $family, int $publishedEventId = 0): bool
    {
        return $publishedEventId > 0 && flow_connect_review_mode($family) === 'active';
    }
}

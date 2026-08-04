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

if (!function_exists('flow_connect_pending_summary_mode')) {
    function flow_connect_pending_summary_mode(): string
    {
        $specific = getenv('FLOW_CONNECT_PENDING_SUMMARY_MODE');
        if ($specific !== false && trim((string) $specific) !== '') {
            return flow_connect_normalize_mode($specific);
        }
        foreach (['FLOW_CONNECT_MODE', 'FLOW_CONNECT_OPERATIONAL_MODE'] as $generalKey) {
            $general = getenv($generalKey);
            if ($general !== false && trim((string) $general) !== '') {
                return flow_connect_normalize_mode($general);
            }
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

/** Policies operacionais novas permanecem desligadas sem configuracao explicita. */
if (!function_exists('flow_connect_operational_mode')) {
    function flow_connect_operational_mode(string $moduleKey, string $policyKey): string
    {
        $normalize = static fn(string $value): string => strtoupper(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($value)) ?? '');
        $specific = getenv('FLOW_CONNECT_POLICY_' . $normalize($moduleKey) . '_' . $normalize($policyKey) . '_MODE');
        if ($specific !== false && trim((string) $specific) !== '') return flow_connect_normalize_mode($specific);
        $module = getenv('FLOW_CONNECT_' . $normalize($moduleKey) . '_MODE');
        return $module !== false && trim((string) $module) !== '' ? flow_connect_normalize_mode($module) : 'off';
    }
}

if (!function_exists('flow_connect_operational_should_bypass')) {
    function flow_connect_operational_should_bypass(string $moduleKey, string $policyKey, int $publishedEventId): bool
    {
        return $publishedEventId > 0 && flow_connect_operational_mode($moduleKey, $policyKey) === 'active';
    }
}

<?php

declare(strict_types=1);

namespace FlowConnect\Application;

final class SlaSchedulePolicy
{
    public function isDue(string $status, int $hoursInApproval, int $limitHours, bool $silenced = false, bool $resolved = false, bool $cancelled = false): bool
    {
        return $status === 'Em aprovação'
            && $hoursInApproval >= $limitHours
            && !$silenced
            && !$resolved
            && !$cancelled;
    }
}

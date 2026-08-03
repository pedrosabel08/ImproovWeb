<?php

declare(strict_types=1);

namespace FlowConnect\Application;

final class OperationalMilestonePolicy
{
    public const ORDER = ['WARNING_90', 'EXPIRED', 'OVERDUE_100', 'OVERDUE_200'];

    public function dueMilestones(\DateTimeImmutable $startedAt, \DateTimeImmutable $dueAt, \DateTimeImmutable $now): array
    {
        $start = $startedAt->getTimestamp(); $due = $dueAt->getTimestamp(); $current = $now->getTimestamp();
        if ($due <= $start) return $current >= $due ? ['EXPIRED', 'OVERDUE_100', 'OVERDUE_200'] : [];
        $duration = $due - $start;
        $thresholds = ['WARNING_90' => $start + (int) floor($duration * .9), 'EXPIRED' => $due, 'OVERDUE_100' => $due + $duration, 'OVERDUE_200' => $due + ($duration * 2)];
        return array_keys(array_filter($thresholds, static fn (int $at): bool => $current >= $at));
    }
}

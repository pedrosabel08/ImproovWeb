<?php

declare(strict_types=1);

namespace FlowConnect\Application\PendingSummary;

use FlowConnect\Contracts\PendingSummaryProviderInterface;
use mysqli;

final class PendingSummaryCollector
{
    /** @param list<PendingSummaryProviderInterface> $providers */
    public function __construct(private array $providers) {}

    /** @return array{summaries:array,providers_success:list<string>,providers_failed:list<string>} */
    public function collect(mysqli $conn): array
    {
        $grouped = [];
        $success = [];
        $failed = [];
        foreach ($this->providers as $provider) {
            $name = (new \ReflectionClass($provider))->getShortName();
            try {
                foreach ($provider->collect($conn) as $module) {
                    $id = (int) ($module['collaborator_id'] ?? 0);
                    if ($id <= 0 || (int) ($module['total'] ?? 0) <= 0) continue;
                    $grouped[$id][] = $module;
                }
                $success[] = $name;
            } catch (\Throwable) {
                $failed[] = $name;
            }
        }
        return ['summaries' => $grouped, 'providers_success' => $success, 'providers_failed' => $failed];
    }
}

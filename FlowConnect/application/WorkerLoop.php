<?php

declare(strict_types=1);

namespace FlowConnect\Application;

final class WorkerLoop
{
    /** @param callable():int $cycle @param callable():bool $keepRunning @param callable():void $idleWait */
    public function run(bool $daemon, callable $cycle, callable $keepRunning, callable $idleWait): void
    {
        while ($keepRunning()) {
            $claimed = $cycle();
            if (!$daemon || !$keepRunning()) return;
            if ($claimed === 0) $idleWait();
        }
    }
}

<?php

declare(strict_types=1);

namespace FlowConnect\Contracts;

use mysqli;

/** A provider owns its domain query and returns normalized per-collaborator module summaries. */
interface PendingSummaryProviderInterface
{
    /**
     * @return list<array{module_key:string,module_name:string,collaborator_id:int,total:int,oldest_created_at:?string,oldest_due_at:?string,highest_priority:string,preview_items:list<array>,origin_url:string}>
     */
    public function collect(mysqli $conn): array;
}

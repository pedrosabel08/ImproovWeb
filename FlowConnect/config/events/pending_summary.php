<?php

declare(strict_types=1);

return [
    'pending.summary.ready' => [
        'severity' => 'INFO',
        'category' => 'SUMMARY',
        'delivery_mode' => 'DM',
        'template' => 'pending_summary',
        'recipient_strategy' => 'summary_owner',
        'required_payload' => [
            'collaborator_id', 'generated_at', 'window_key', 'total_pending',
            'total_modules', 'priority_level', 'modules', 'origin_url',
        ],
    ],
];

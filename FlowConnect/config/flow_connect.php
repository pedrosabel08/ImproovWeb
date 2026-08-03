<?php

declare(strict_types=1);

if (!function_exists('flow_connect_env_list')) {
    function flow_connect_env_list(string $key, array $default = []): array
    {
        $raw = getenv($key);
        if ($raw === false || trim((string) $raw) === '') {
            return $default;
        }

        $values = array_map('trim', explode(',', (string) $raw));
        return array_values(array_unique(array_filter(array_map('intval', $values), static fn(int $id): bool => $id > 0)));
    }
}

return [
    'environment' => getenv('APP_ENV') ?: 'local',
    'claim_ttl_seconds' => max(30, (int) (getenv('FLOW_CONNECT_CLAIM_TTL_SECONDS') ?: 300)),
    'slack' => [
        'token_env' => 'SLACK_TOKEN',
        'api_base_url' => 'https://slack.com/api',
        'timeout_seconds' => max(3, (int) (getenv('FLOW_CONNECT_SLACK_TIMEOUT_SECONDS') ?: 10)),
    ],
    'flow_review' => [
        'url' => getenv('FLOW_CONNECT_FLOW_REVIEW_URL') ?: 'https://improov.com.br/flow/ImproovWeb/FlowReview/index.php',
        'approval_delivery_mode' => strtoupper((string) (getenv('FLOW_CONNECT_REVIEW_APPROVAL_DELIVERY_MODE') ?: 'HISTORY_ONLY')),
        'sla_repeat_hours' => max(1, (int) (getenv('FLOW_CONNECT_REVIEW_SLA_REPEAT_HOURS') ?: 24)),
        'roles' => [
            // Provisórios para preservar o roteamento atual. Sobrescrever por ambiente antes de active.
            'direction_group' => flow_connect_env_list('FLOW_CONNECT_REVIEW_DIRECTION_COLLABORATOR_IDS'),
            'flow_review_managers' => flow_connect_env_list('FLOW_CONNECT_REVIEW_MANAGER_COLLABORATOR_IDS'),
            'technical_admins' => flow_connect_env_list('FLOW_CONNECT_TECHNICAL_ADMIN_COLLABORATOR_IDS'),
        ],
        'review_channel_id' => trim((string) (getenv('FLOW_CONNECT_REVIEW_CHANNEL_ID') ?: '')),
    ],
    'operational' => [
        'business_timezone' => getenv('FLOW_CONNECT_BUSINESS_TIMEZONE') ?: 'America/Sao_Paulo',
        'render_approval_sla_seconds' => max(60, (int) (getenv('FLOW_CONNECT_RENDER_APPROVAL_SLA_SECONDS') ?: 3600)),
        'scheduler_idle_seconds' => max(1, min(60, (int) (getenv('FLOW_CONNECT_OPERATIONAL_SCHEDULER_IDLE_SECONDS') ?: 1))),
        'manager_roles' => [
            'flow_review' => flow_connect_env_list('FLOW_CONNECT_REVIEW_MANAGER_COLLABORATOR_IDS'),
            'pre_alteracao' => flow_connect_env_list('FLOW_CONNECT_PRE_ALTERACAO_MANAGER_COLLABORATOR_IDS'),
            'render' => flow_connect_env_list('FLOW_CONNECT_RENDER_MANAGER_COLLABORATOR_IDS'),
            'projeto' => flow_connect_env_list('FLOW_CONNECT_PROJETO_MANAGER_COLLABORATOR_IDS'),
            'imagem' => flow_connect_env_list('FLOW_CONNECT_IMAGEM_MANAGER_COLLABORATOR_IDS'),
            'flow_block' => flow_connect_env_list('FLOW_CONNECT_FLOW_BLOCK_MANAGER_COLLABORATOR_IDS'),
            'links' => flow_connect_env_list('FLOW_CONNECT_LINKS_MANAGER_COLLABORATOR_IDS'),
            'cobranca_cliente' => flow_connect_env_list('FLOW_CONNECT_COBRANCA_CLIENTE_MANAGER_COLLABORATOR_IDS'),
            'fotografico' => flow_connect_env_list('FLOW_CONNECT_FOTOGRAFICO_MANAGER_COLLABORATOR_IDS'),
            'arquivo' => flow_connect_env_list('FLOW_CONNECT_ARQUIVO_MANAGER_COLLABORATOR_IDS'),
        ],
        'overdue_webhook_env' => 'FLOW_CONNECT_SLA_OVERDUE_WEBHOOK_URL',
        'upload_summary_times' => array_values(array_filter(array_map('trim', explode(',', (string) (getenv('FLOW_CONNECT_UPLOAD_SUMMARY_TIMES') ?: '09:00,13:00,17:00'))))),
    ],
];

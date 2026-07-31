<?php

declare(strict_types=1);

if (!function_exists('flow_connect_test_config')) {
    /**
     * Configuração exclusiva da bateria local. Ela não é carregada pelos
     * produtores nem pelos workers de produção e só é aceita em local/test.
     */
    function flow_connect_test_config(): array
    {
        $environment = strtolower(trim((string) (getenv('APP_ENV') ?: 'local')));
        $allowedEnvironment = in_array($environment, ['local', 'test'], true);
        $enabled = filter_var(getenv('FLOW_CONNECT_TEST_MODE') ?: false, FILTER_VALIDATE_BOOLEAN);
        return [
            'enabled' => $allowedEnvironment && $enabled,
            'environment' => $environment,
            'collaborator_id' => max(0, (int) (getenv('FLOW_CONNECT_TEST_COLLABORATOR_ID') ?: 0)),
            'allowed_project' => trim((string) (getenv('FLOW_CONNECT_TEST_ALLOWED_PROJECT') ?: '')),
        ];
    }
}

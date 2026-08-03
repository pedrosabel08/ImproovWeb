<?php

declare(strict_types=1);

namespace FlowConnect\Application;

use RuntimeException;

final class TemplateRenderer
{
    public function render(string $templateCode, array $event): array
    {
        if (!preg_match('/^[a-z0-9_]+$/', $templateCode)) {
            throw new RuntimeException('flow_connect_template_code_invalid');
        }
        require_once dirname(__DIR__) . '/templates/slack/flow_review/_base.php';
        $path = dirname(__DIR__) . '/templates/slack/operational/' . $templateCode . '.php';
        if (!is_file($path)) $path = dirname(__DIR__) . '/templates/slack/flow_review/' . $templateCode . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('flow_connect_template_not_found');
        }
        $renderer = require $path;
        if (!is_callable($renderer)) {
            throw new RuntimeException('flow_connect_template_invalid');
        }
        $result = $renderer($event['payload'] ?? [], $event);
        if (!is_array($result) || trim((string) ($result['text'] ?? '')) === '') {
            throw new RuntimeException('flow_connect_template_empty');
        }
        return ['text' => (string) $result['text'], 'blocks' => $result['blocks'] ?? null];
    }
}

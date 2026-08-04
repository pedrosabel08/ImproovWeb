<?php

return static function (array $payload): array {
    $modules = is_array($payload['modules'] ?? null) ? $payload['modules'] : [];
    $lines = [];
    foreach ($modules as $module) {
        $total = (int) ($module['total'] ?? 0);
        if ($total > 0) {
            $lines[] = flow_connect_tpl_escape((string) ($module['module_name'] ?? 'Pendências')) . ': *' . $total . '*';
        }
    }
    $total = (int) ($payload['total_pending'] ?? 0);
    $url = trim((string) ($payload['origin_url'] ?? ''));
    $link = preg_match('~^https?://~i', $url)
        ? '<' . str_replace(['<', '>'], '', $url) . '|Abrir Pendências>'
        : '';
    $text = "📋 *Resumo das suas pendências*\n\n"
        . implode("\n", $lines)
        . "\n\n*Total: {$total} pendência" . ($total === 1 ? '' : 's') . '*';
    return ['text' => $text . ($link === '' ? '' : "\n\n{$link}")];
};

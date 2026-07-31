<?php

declare(strict_types=1);

if (!function_exists('flow_connect_tpl_escape')) {
    function flow_connect_tpl_escape($value): string
    {
        return strtr(trim((string) $value), ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }
}

if (!function_exists('flow_connect_tpl_truncate')) {
    function flow_connect_tpl_truncate($value, int $limit = 500): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value);
        return mb_strlen($value, 'UTF-8') <= $limit ? $value : mb_substr($value, 0, $limit - 1, 'UTF-8') . '…';
    }
}

if (!function_exists('flow_connect_tpl_context')) {
    function flow_connect_tpl_context(array $payload): string
    {
        $parts = [];
        if (!empty($payload['obra_nome'])) $parts[] = '*' . flow_connect_tpl_escape($payload['obra_nome']) . '*';
        if (!empty($payload['imagem_nome'])) $parts[] = '_' . flow_connect_tpl_escape($payload['imagem_nome']) . '_';
        if (!empty($payload['funcao_nome'])) $parts[] = flow_connect_tpl_escape($payload['funcao_nome']);
        return implode(' · ', $parts);
    }
}

if (!function_exists('flow_connect_tpl_link')) {
    function flow_connect_tpl_link(array $payload): string
    {
        $url = trim((string) ($payload['flow_review_url'] ?? ''));
        if (!preg_match('~^https?://~i', $url)) return '';
        return '<' . str_replace(['<', '>'], '', $url) . '|Abrir no FlowReview>';
    }
}

if (!function_exists('flow_connect_tpl_message')) {
    function flow_connect_tpl_message(string $title, array $payload, string $detail = ''): array
    {
        $lines = [$title];
        $context = flow_connect_tpl_context($payload);
        if ($context !== '') $lines[] = $context;
        if ($detail !== '') $lines[] = $detail;
        $link = flow_connect_tpl_link($payload);
        if ($link !== '') $lines[] = $link;
        return ['text' => implode("\n", $lines)];
    }
}

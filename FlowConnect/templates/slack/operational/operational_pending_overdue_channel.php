<?php
return static function (array $payload): array {
    $module = flow_connect_tpl_escape((string) ($payload['module_key'] ?? 'operacional'));
    $title = flow_connect_tpl_escape((string) ($payload['titulo'] ?? 'Pendência'));
    $milestone = flow_connect_tpl_escape((string) ($payload['milestone'] ?? 'EXPIRED'));
    return ['text' => "🚨 SLA atrasado · *{$module}*\n*{$title}*\nMarco: {$milestone}"];
};

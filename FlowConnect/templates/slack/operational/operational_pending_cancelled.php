<?php
return static function (array $payload): array {
    $title = flow_connect_tpl_escape((string) ($payload['titulo'] ?? 'Pendência'));
    return ['text' => "🚫 Pendência cancelada: *{$title}*"];
};

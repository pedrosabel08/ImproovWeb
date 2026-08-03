<?php
return static function (array $payload): array {
    $title = flow_connect_tpl_escape((string) ($payload['titulo'] ?? 'Pendência'));
    $detail = trim((string) ($payload['descricao'] ?? 'Uma nova ação é necessária.'));
    return ['text' => "📌 Pendência criada: *{$title}*\n{$detail}"];
};

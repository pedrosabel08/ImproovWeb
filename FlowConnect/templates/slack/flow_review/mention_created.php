<?php
return static function (array $p): array {
    $author = flow_connect_tpl_escape($p['autor_nome'] ?? 'Alguém');
    $origin = !empty($p['resposta_id']) ? 'em uma resposta' : 'em um comentário';
    return flow_connect_tpl_message("📌 Você foi mencionado por *{$author}* {$origin}.", $p);
};

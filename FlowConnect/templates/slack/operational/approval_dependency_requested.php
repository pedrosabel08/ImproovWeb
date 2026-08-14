<?php
return static function (array $payload): array {
    $etapa = flow_connect_tpl_escape((string) ($payload['etapa_anterior'] ?? 'etapa anterior'));
    $imagem = flow_connect_tpl_escape((string) ($payload['imagem_nome'] ?? 'imagem'));
    $proxima = flow_connect_tpl_escape((string) ($payload['etapa_bloqueada'] ?? 'próxima etapa'));
    $bloqueadaPor = flow_connect_tpl_escape((string) ($payload['bloqueada_por_nome'] ?? 'uma pessoa da produção'));
    return ['text' => "📌 Você pode aprovar {$etapa} de {$imagem} para que {$bloqueadaPor} inicie {$proxima}?"];
};

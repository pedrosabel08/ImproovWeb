<?php
return static function (array $p): array {
    $reviewer = flow_connect_tpl_escape($p['revisor_nome'] ?? 'responsável operacional');
    return flow_connect_tpl_message('⏳ Validação da direção solicitada.', $p, "Aprovação operacional registrada por {$reviewer}.");
};

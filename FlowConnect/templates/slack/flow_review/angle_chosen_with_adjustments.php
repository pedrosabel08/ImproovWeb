<?php
return static function (array $p): array {
    $obs = flow_connect_tpl_truncate($p['observacao'] ?? '', 500);
    return flow_connect_tpl_message('✅ Ângulo escolhido com ajustes.', $p, $obs !== '' ? '> ' . flow_connect_tpl_escape($obs) : '');
};

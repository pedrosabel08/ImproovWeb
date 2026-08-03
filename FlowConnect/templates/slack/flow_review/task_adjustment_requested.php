<?php
return static function (array $p): array {
    $obs = flow_connect_tpl_truncate($p['observacao'] ?? '', 500);
    return flow_connect_tpl_message(flow_connect_tpl_title_with_actor('🛠️ Ajuste solicitado na tarefa.', $p), $p, $obs !== '' ? '> ' . flow_connect_tpl_escape($obs) : 'A tarefa requer nova ação.');
};

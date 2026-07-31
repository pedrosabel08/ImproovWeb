<?php
return static function (array $p): array {
    $obs = flow_connect_tpl_truncate($p['observacao'] ?? '', 500);
    return flow_connect_tpl_message('🛠️ Ajuste solicitado na tarefa.', $p, $obs !== '' ? '> ' . flow_connect_tpl_escape($obs) : 'A tarefa requer nova ação.');
};

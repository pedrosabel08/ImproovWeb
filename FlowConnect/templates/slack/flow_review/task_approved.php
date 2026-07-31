<?php
return static fn (array $p): array => flow_connect_tpl_message('✅ Tarefa aprovada.', $p, !empty($p['revisor_nome']) ? 'Revisada por ' . flow_connect_tpl_escape($p['revisor_nome']) . '.' : '');

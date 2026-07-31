<?php
return static function (array $p): array {
    $hours = max(0, (int) ($p['tempo_em_aprovacao'] ?? 0));
    $limit = max(0, (int) ($p['limite_sla'] ?? 0));
    return flow_connect_tpl_message('🚨 SLA de aprovação excedido.', $p, "Há *{$hours}h* em aprovação (limite: {$limit}h). Por favor, revise a tarefa.");
};

<?php

declare(strict_types=1);

$modules = ['flow_review', 'pre_alteracao', 'render', 'projeto', 'imagem', 'links', 'cobranca_cliente', 'fotografico', 'briefing'];
$definitions = [];
foreach ($modules as $module) {
    foreach (['criada' => 'operational_pending_created', 'resolvida' => 'operational_pending_resolved', 'cancelada' => 'operational_pending_cancelled'] as $action => $template) {
        $definitions[$module . '.pendencia.' . $action] = [
            'severity' => $action === 'criada' ? 'ACAO' : 'INFORMATIVO',
            'category' => 'OPERATIONAL',
            'delivery_mode' => 'DM',
            'template' => $template,
            'recipient_strategy' => 'operational_pending_audience',
            'required_payload' => ['module_key', 'titulo', 'cycle_id'],
        ];
    }
}
foreach (['registrado' => 'operational_pending_created', 'resolvido' => 'operational_pending_resolved', 'cancelado' => 'operational_pending_cancelled'] as $action => $template) {
    $definitions['flow_block.bloqueio.' . $action] = [
        'severity' => $action === 'registrado' ? 'ACAO' : 'INFORMATIVO',
        'category' => 'OPERATIONAL',
        'delivery_mode' => 'DM',
        'template' => $template,
        'recipient_strategy' => 'operational_pending_audience',
        'required_payload' => ['module_key', 'titulo', 'cycle_id'],
    ];
}
$definitions['operacional.pendencia.sla_marco_atingido'] = [
    'severity' => 'ACAO',
    'category' => 'OPERATIONAL',
    'delivery_mode' => 'DM',
    'template' => 'operational_pending_milestone',
    'recipient_strategy' => 'operational_pending_audience',
    'required_payload' => ['module_key', 'titulo', 'cycle_id', 'milestone'],
    'overdue_webhook_milestones' => ['EXPIRED', 'OVERDUE_100', 'OVERDUE_200'],
    'overdue_webhook_template' => 'operational_pending_overdue_channel',
];
$definitions['arquivo.upload_pendente.resumo'] = [
    'severity' => 'ACAO',
    'category' => 'OPERATIONAL',
    'delivery_mode' => 'DM',
    'template' => 'file_upload_pending_summary',
    'recipient_strategy' => 'operational_pending_audience',
    'required_payload' => ['module_key', 'titulo', 'cycle_id', 'itens'],
];
return $definitions;

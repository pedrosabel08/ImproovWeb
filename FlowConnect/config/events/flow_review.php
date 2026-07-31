<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/flow_connect.php';
$approvalMode = $config['flow_review']['approval_delivery_mode'] ?? 'HISTORY_ONLY';
$approvalMode = in_array($approvalMode, ['HISTORY_ONLY', 'DM'], true) ? $approvalMode : 'HISTORY_ONLY';

return [
    'review.mencao.criada' => [
        'family' => 'mention', 'severity' => 'ACAO', 'category' => 'OPERATIONAL',
        'delivery_mode' => 'DM', 'template' => 'mention_created', 'recipient_strategy' => 'mentioned_user',
        'required_payload' => ['mencionado_id', 'autor_id', 'flow_review_url'],
    ],
    'review.angulo.escolhido' => [
        'family' => 'angle', 'severity' => 'INFORMATIVO', 'category' => 'OPERATIONAL',
        'delivery_mode' => 'DM', 'template' => 'angle_chosen', 'recipient_strategy' => 'task_responsible',
        'required_payload' => ['funcao_imagem_id', 'imagem_id', 'colaborador_responsavel_id', 'decisao'],
    ],
    'review.angulo.escolhido_com_ajustes' => [
        'family' => 'angle', 'severity' => 'ACAO', 'category' => 'OPERATIONAL',
        'delivery_mode' => 'DM', 'template' => 'angle_chosen_with_adjustments', 'recipient_strategy' => 'task_responsible',
        'required_payload' => ['funcao_imagem_id', 'imagem_id', 'colaborador_responsavel_id', 'decisao'],
    ],
    'review.angulo.ajuste_solicitado' => [
        'family' => 'angle', 'severity' => 'ACAO', 'category' => 'OPERATIONAL',
        'delivery_mode' => 'DM', 'template' => 'angle_adjustment_requested', 'recipient_strategy' => 'task_responsible',
        'required_payload' => ['funcao_imagem_id', 'imagem_id', 'colaborador_responsavel_id', 'decisao'],
    ],
    'review.tarefa.aprovada' => [
        'family' => 'task', 'severity' => 'INFORMATIVO', 'category' => 'OPERATIONAL',
        'delivery_mode' => $approvalMode, 'template' => 'task_approved', 'recipient_strategy' => 'flow_review_managers',
        'required_payload' => ['status_novo', 'tipo_fluxo'],
    ],
    'review.tarefa.ajuste_solicitado' => [
        'family' => 'task', 'severity' => 'ACAO', 'category' => 'OPERATIONAL',
        'delivery_mode' => 'DM', 'template' => 'task_adjustment_requested', 'recipient_strategy' => 'task_responsible',
        'required_payload' => ['status_novo', 'tipo_fluxo'],
    ],
    'review.tarefa.aprovada_com_ajustes' => [
        'family' => 'task', 'severity' => 'ACAO', 'category' => 'OPERATIONAL',
        'delivery_mode' => 'DM', 'template' => 'task_approved_with_adjustments', 'recipient_strategy' => 'task_responsible',
        'required_payload' => ['status_novo', 'tipo_fluxo'],
    ],
    // Contrato e template reservados; nenhum produtor atual emite este evento.
    'review.tarefa.reprovada' => [
        'family' => 'task', 'severity' => 'ACAO', 'category' => 'OPERATIONAL',
        'delivery_mode' => 'DM', 'template' => 'task_rejected', 'recipient_strategy' => 'task_responsible',
        'required_payload' => ['status_novo', 'tipo_fluxo'],
    ],
    'review.tarefa.enviada_direcao' => [
        'family' => 'direction', 'severity' => 'INFORMATIVO', 'category' => 'OPERATIONAL',
        'delivery_mode' => 'HISTORY_ONLY', 'template' => 'direction_validation_requested', 'recipient_strategy' => 'flow_review_managers',
        'required_payload' => ['historico_direcao_id', 'funcao_imagem_id'],
    ],
    'review.direcao.validacao_solicitada' => [
        'family' => 'direction', 'severity' => 'ACAO', 'category' => 'OPERATIONAL',
        'delivery_mode' => 'DM', 'template' => 'direction_validation_requested', 'recipient_strategy' => 'direction_group',
        'required_payload' => ['historico_direcao_id', 'funcao_imagem_id'],
    ],
    'review.sftp.envio_falhou' => [
        'family' => 'sftp', 'severity' => 'CRITICO', 'category' => 'TECHNICAL',
        'delivery_mode' => 'DM', 'template' => 'sftp_failed', 'recipient_strategy' => 'technical_admins',
        'required_payload' => ['operacao', 'tentativa', 'erro_tecnico_seguro'],
    ],
    'review.aprovacao.sla_excedido' => [
        'family' => 'sla', 'severity' => 'ATRASADO', 'category' => 'OPERATIONAL',
        'delivery_mode' => 'DM', 'template' => 'approval_sla_exceeded', 'recipient_strategy' => 'flow_review_managers',
        'required_payload' => ['funcao_imagem_id', 'tempo_em_aprovacao', 'limite_sla'],
    ],
];

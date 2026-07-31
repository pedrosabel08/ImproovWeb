<?php

declare(strict_types=1);

$events = [
    'contratos.documento.status_atualizado',
    'fotografico.registro.criado',
    'fotografico.plano.notificacao',
    'arquivo.upload.status',
    'arquivo.upload.worker_status',
    'arquivo.upload.publicado',
    'arquivo.upload.refeito',
    'pos.imagem.finalizada',
    'pre_alteracao.planejamento_liberado',
    'sire.importacao_referencias.falhou',
    'sire.importacao_referencias.resumo',
    'resposta_diaria.questionario.respondido',
    'notificacao.interna.visualizada',
    'render.job.transicao',
];

$definitions = [];
foreach ($events as $eventCode) {
    $definitions[$eventCode] = [
        'family' => 'legacy_immediate',
        'severity' => 'INFORMATIVO',
        'category' => 'OPERATIONAL',
        'delivery_mode' => 'DM',
        'template' => 'legacy_immediate',
        'recipient_strategy' => 'legacy_payload_destination',
        'required_payload' => ['message'],
    ];
}
$definitions['sire.importacao_referencias.falhou']['severity'] = 'CRITICO';
$definitions['render.job.transicao']['severity'] = 'ACAO';

return $definitions;

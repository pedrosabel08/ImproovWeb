<?php

declare(strict_types=1);

/**
 * Gera um evento operacional de teste para um colaborador.
 * Uso: php test_operational_shadow.php <modulo> <colaborador_id> [arquivo]
 * Este script nunca configura ACTIVE e nunca chama Slack.
 */
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';

$module = strtolower(trim((string) ($argv[1] ?? '')));
$collaboratorId = (int) ($argv[2] ?? 0);
$kind = strtolower(trim((string) ($argv[3] ?? 'pendencia')));
$allowed = ['projeto', 'imagem', 'pre_alteracao', 'render', 'flow_block', 'links', 'cobranca_cliente', 'fotografico', 'flow_review', 'arquivo'];
if (!in_array($module, $allowed, true) || $collaboratorId <= 0 || !in_array($kind, ['pendencia', 'arquivo'], true)) {
    fwrite(STDERR, "Uso: php test_operational_shadow.php <modulo> <colaborador_id> [pendencia|arquivo]\n");
    exit(2);
}

// O modo vive somente neste processo e o valor ACTIVE nunca é aceito pelo harness.
putenv('FLOW_CONNECT_' . strtoupper($module) . '_MODE=shadow');
$conn = conectarBanco();
$cycleId = 'test-' . $module . '-' . $collaboratorId . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3));
$payload = [
    'module_key' => $module, 'cycle_id' => $cycleId, 'titulo' => 'Teste de pendência · ' . $module,
    'descricao' => 'Evento SHADOW gerado pelo test_operational_shadow.php.', 'responsavel_id' => $collaboratorId,
];

if ($kind === 'arquivo') {
    $event = \FlowConnect\Contracts\EventEnvelope::normalize([
        'event_type' => 'arquivo.upload_pendente.resumo', 'source_module' => 'arquivo', 'entity_type' => 'funcao_imagem', 'entity_id' => (string) $collaboratorId,
        'idempotency_key' => 'operacional:arquivo:upload-pendente:test:' . $cycleId,
        'payload' => $payload + ['titulo' => 'Arquivos pendentes de upload', 'itens' => [
            ['titulo' => 'Teste · arquivo pendente 1', 'entity_id' => 'test-1'],
            ['titulo' => 'Teste · arquivo pendente 2', 'entity_id' => 'test-2'],
        ]],
        'metadata' => ['policy_key' => 'upload_pendente.resumo.v1', 'flow_connect_mode' => 'shadow', 'producer' => 'test_operational_shadow.php'],
    ]);
} else {
    $policy = $module . '.pendencia.v1';
    $event = \FlowConnect\Application\OperationalPendingEventFactory::lifecycle($module, 'criada', 'teste_operacional', $cycleId, $payload, $policy, $collaboratorId);
    $event['metadata']['flow_connect_mode'] = 'shadow';
    $event['metadata']['test_only'] = true;
}

try {
    $conn->begin_transaction();
    $eventId = flow_connect_publish_in_transaction($conn, $event);
    $conn->commit();
    echo json_encode(['event_id' => $eventId, 'event_uuid' => $event['event_uuid'], 'module' => $module, 'collaborator_id' => $collaboratorId, 'mode' => 'shadow', 'external_calls' => 0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    if ($conn->errno === 0) $conn->rollback();
    fwrite(STDERR, flow_connect_safe_error($e->getMessage(), 'shadow_test_failed') . PHP_EOL);
    $conn->close(); exit(1);
}
$conn->close();

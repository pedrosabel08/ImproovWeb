<?php

declare(strict_types=1);

require_once __DIR__ . '/_cli.php';

use FlowConnect\Contracts\EventEnvelope;

$policyKey = 'upload_pendente.resumo.v1';
if (flow_connect_operational_mode('arquivo', $policyKey) === 'off') {
    flow_connect_cli_log('upload_pending_summary_worker skipped: policy off', true);
    exit(0);
}
$conn = conectarBanco();
$table = $conn->query("SHOW TABLES LIKE 'flow_connect_pending_summary_windows'");
if (!$table || $table->num_rows === 0) {
    flow_connect_cli_log('upload_pending_summary_worker skipped: migration 002 not applied', true);
    $conn->close();
    exit(0);
}
$config = flow_connect_config();
$tz = new DateTimeZone((string) $config['operational']['business_timezone']);
$now = new DateTimeImmutable('now', $tz);
$currentTime = $now->format('H:i');
if (!in_array($currentTime, $config['operational']['upload_summary_times'], true)) {
    flow_connect_cli_log('upload_pending_summary_worker skipped: outside configured window', true);
    $conn->close();
    exit(0);
}
$windowKey = $now->format('Y-m-d') . 'T' . $currentTime;
$sql = "SELECT fi.idfuncao_imagem, fi.colaborador_id, fi.prazo, i.imagem_nome, fun.nome_funcao,
               COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) obra_nome
        FROM funcao_imagem fi
        JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra=fi.imagem_id
        JOIN obra o ON o.idobra=i.obra_id
        LEFT JOIN funcao fun ON fun.idfuncao=fi.funcao_id
        WHERE fi.requires_file_upload=1 AND fi.file_uploaded_at IS NULL AND fi.colaborador_id IS NOT NULL
        ORDER BY COALESCE(fi.prazo,fi.idfuncao_imagem) ASC";
$rows = $conn->query($sql);
$byRecipient = [];
while ($rows && ($row = $rows->fetch_assoc())) {
    $byRecipient[(int) $row['colaborador_id']][] = $row;
}
foreach ($byRecipient as $collaboratorId => $items) {
    $check = $conn->prepare('SELECT id FROM flow_connect_pending_summary_windows WHERE policy_key=? AND window_key=? AND collaborator_id=?');
    $check->bind_param('ssi', $policyKey, $windowKey, $collaboratorId);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();
    if ($exists)
        continue;
    $maxDetailedItems = 5;
    $summaryItems = array_map(static fn(array $row): array => [
        'imagem_nome' => (string) ($row['imagem_nome'] ?: 'Imagem'),
        'funcao_nome' => (string) ($row['nome_funcao'] ?: 'Função não identificada'),
        'titulo' => ($row['imagem_nome'] ?: 'Imagem') . ' — ' . ($row['nome_funcao'] ?: 'Função não identificada') . ' — arquivo pendente',
        'entity_id' => (int) $row['idfuncao_imagem'],
    ], array_slice($items, 0, $maxDetailedItems));
    $workCount = count(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row['obra_nome'] ?? ''), $items))));
    $event = EventEnvelope::normalize([
        'event_type' => 'arquivo.upload_pendente.resumo',
        'source_module' => 'arquivo',
        'entity_type' => 'funcao_imagem',
        'entity_id' => (string) $collaboratorId,
        'idempotency_key' => "operacional:arquivo:upload-pendente:{$windowKey}:colaborador:{$collaboratorId}",
        'payload' => ['module_key' => 'arquivo', 'cycle_id' => $windowKey, 'titulo' => 'Arquivos pendentes de upload', 'responsavel_id' => $collaboratorId, 'total' => count($items), 'obras_total' => $workCount, 'resumo_compacto' => count($items) > $maxDetailedItems, 'origin_url' => 'https://improov.com.br/flow/ImproovWeb/inicio.php', 'itens' => $summaryItems],
        'metadata' => ['policy_key' => $policyKey, 'flow_connect_mode' => flow_connect_operational_mode('arquivo', $policyKey), 'producer' => 'upload_pending_summary_worker'],
    ]);
    $conn->begin_transaction();
    try {
        flow_connect_publish_in_transaction($conn, $event);
        $uuid = $event['event_uuid'];
        $insert = $conn->prepare('INSERT INTO flow_connect_pending_summary_windows (policy_key,window_key,collaborator_id,event_uuid) VALUES (?,?,?,?)');
        $insert->bind_param('ssis', $policyKey, $windowKey, $collaboratorId, $uuid);
        $insert->execute();
        $insert->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        flow_connect_cli_log('upload summary failed=' . flow_connect_safe_error($e->getMessage()), true);
    }
}
$conn->close();

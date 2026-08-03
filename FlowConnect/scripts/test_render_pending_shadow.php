<?php

declare(strict_types=1);

/**
 * Fixture isolada do Flow Connect para testar a pendência temporal de Render.
 * Não atualiza render_alta, funcao_imagem, status, prazo nem chama Slack.
 * O flag shadow_fixture mantém também os marcos futuros em SHADOW.
 *
 * Uso:
 *   php FlowConnect/scripts/test_render_pending_shadow.php 356946 --fixture
 *   php FlowConnect/scripts/test_render_pending_shadow.php 356946 --fixture --age-minutes=181
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/conexaoMain.php';

$renderId = (int) ($argv[1] ?? 0);
$fixture = in_array('--fixture', $argv, true);
$ageMinutes = 0;
$cycleSuffix = 'v1';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--age-minutes=')) $ageMinutes = max(0, min(10080, (int) substr($arg, 14)));
    if (str_starts_with($arg, '--cycle-suffix=')) $cycleSuffix = preg_replace('/[^A-Za-z0-9_-]+/', '-', substr($arg, 15)) ?: 'v1';
}
if ($renderId <= 0) {
    fwrite(STDERR, "Uso: php FlowConnect/scripts/test_render_pending_shadow.php <render_id> --fixture [--age-minutes=181]\n");
    exit(2);
}

// Never inherit ACTIVE from the caller for a test fixture.
putenv('FLOW_CONNECT_RENDER_MODE=shadow');
putenv('FLOW_CONNECT_POLICY_RENDER_RENDER_APROVACAO_V1_MODE=shadow');

$conn = conectarBanco();
$sql = "SELECT r.idrender_alta,r.imagem_id,r.status AS render_status,r.responsavel_id AS render_responsavel_id,
               fi.idfuncao_imagem,fi.funcao_id,fi.status AS funcao_status,fi.colaborador_id,
               ico.obra_id,ico.imagem_nome,COALESCE(NULLIF(o.nomenclatura,''),o.nome_obra) AS obra_nome
          FROM render_alta r
          JOIN funcao_imagem fi ON fi.imagem_id=r.imagem_id AND fi.funcao_id=4
          JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra=r.imagem_id
          LEFT JOIN obra o ON o.idobra=ico.obra_id
         WHERE r.idrender_alta=?
         ORDER BY fi.idfuncao_imagem DESC LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) throw new RuntimeException('render_fixture_prepare_failed');
$stmt->bind_param('i', $renderId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();
if (!$row) {
    $conn->close();
    fwrite(STDERR, "Render ou tarefa de Finalização não encontrado para {$renderId}.\n");
    exit(3);
}

$inApproval = (string) $row['funcao_status'] === 'Em aprovação';
if (!$inApproval && !$fixture) {
    $conn->close();
    echo json_encode(['status' => 'BLOCKED', 'reason' => 'render_not_in_approval', 'render_id' => $renderId, 'funcao_status' => $row['funcao_status'], 'hint' => 'Use --fixture apenas para simular no Flow Connect sem alterar o status de negócio.'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(4);
}

$now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$started = $now->sub(new DateInterval('PT' . $ageMinutes . 'M'));
$due = $started->add(new DateInterval('PT1H'));
$taskId = (int) $row['idfuncao_imagem'];
$cycleId = 'shadow-render:' . $renderId . ':funcao:' . $taskId . ':approval:' . $cycleSuffix;
$responsible = (int) ($row['render_responsavel_id'] ?? 0) ?: ((int) ($row['colaborador_id'] ?? 0) ?: null);
$logs = [];
$eventId = flow_connect_publish_operational_pending($conn, 'render', 'render.aprovacao.v1', 'criada', 'funcao_imagem', $taskId, [
    'cycle_id' => $cycleId,
    'pendencia_id' => $taskId,
    'responsavel_id' => $responsible,
    'started_at' => $started->format('Y-m-d H:i:s'),
    'due_at' => $due->format('Y-m-d H:i:s'),
    'sla_seconds' => 3600,
    'obra_id' => (int) ($row['obra_id'] ?? 0) ?: null,
    'imagem_id' => (int) ($row['imagem_id'] ?? 0),
    'funcao_imagem_id' => $taskId,
    'titulo' => 'Aprovação de render · ' . ((string) ($row['imagem_nome'] ?? ('Render #' . $renderId))),
    'contexto_seguro' => ['obra' => (string) ($row['obra_nome'] ?? ''), 'render_id' => $renderId],
    'origin_url' => 'FlowReview/index.php',
    'business_timezone' => 'America/Sao_Paulo',
    'shadow_fixture' => $fixture,
], null, $logs);
$conn->close();

echo json_encode([
    'status' => $eventId > 0 ? 'CREATED_OR_REUSED' : 'NOT_CREATED',
    'event_id' => $eventId,
    'mode' => 'shadow',
    'fixture' => $fixture,
    'render_id' => $renderId,
    'funcao_imagem_id' => $taskId,
    'cycle_id' => $cycleId,
    'funcao_status' => $row['funcao_status'],
    'age_minutes' => $ageMinutes,
    'external_calls' => 0,
    'logs' => $logs,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

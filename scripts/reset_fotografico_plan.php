<?php

declare(strict_types=1);

/**
 * Auditoria e remoção controlada de uma campanha fotográfica.
 *
 * Uso:
 *   php scripts/reset_fotografico_plan.php --plan=1
 *   php scripts/reset_fotografico_plan.php --plan=1 --apply
 *
 * Sem --apply, apenas apresenta as contagens. A remoção é feita apagando a
 * raiz fotografico_plano dentro de uma transação; as chaves estrangeiras do
 * módulo removem os registros dependentes em cascata. Arquivos em disco não
 * são removidos por este script.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(405);
    exit("Este script só pode ser executado pela CLI.\n");
}

require_once __DIR__ . '/../conexaoMain.php';

$options = getopt('', ['plan:', 'apply', 'json', 'help']);
if (isset($options['help']) || !isset($options['plan'])) {
    echo "Uso: php scripts/reset_fotografico_plan.php --plan=<id> [--apply] [--json]\n";
    exit(isset($options['help']) ? 0 : 1);
}

$planId = filter_var($options['plan'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($planId === false) {
    fwrite(STDERR, "O parâmetro --plan precisa ser um inteiro positivo.\n");
    exit(1);
}

/** @return bool */
function fotografico_reset_table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

/** @return int */
function fotografico_reset_count(mysqli $conn, string $sql, int $planId): int
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Não foi possível preparar a auditoria: ' . $conn->error);
    }
    $stmt->bind_param('i', $planId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: ['total' => 0];
    $stmt->close();
    return (int) $row['total'];
}

/** @return array<string, int> */
function fotografico_reset_collect(mysqli $conn, int $planId): array
{
    $queries = [
        'fotografico_plano' => ['fotografico_plano', 'SELECT COUNT(*) AS total FROM fotografico_plano WHERE id = ?'],
        'fotografico_plano_versao' => ['fotografico_plano_versao', 'SELECT COUNT(*) AS total FROM fotografico_plano_versao WHERE plano_id = ?'],
        'fotografico_plano_imagem' => ['fotografico_plano_imagem', 'SELECT COUNT(*) AS total FROM fotografico_plano_imagem pi JOIN fotografico_plano_versao v ON v.id = pi.versao_id WHERE v.plano_id = ?'],
        'fotografico_posicao' => ['fotografico_posicao', 'SELECT COUNT(*) AS total FROM fotografico_posicao po JOIN fotografico_plano_versao v ON v.id = po.versao_id WHERE v.plano_id = ?'],
        'fotografico_captura' => ['fotografico_captura', 'SELECT COUNT(*) AS total FROM fotografico_captura c JOIN fotografico_posicao po ON po.id = c.posicao_id JOIN fotografico_plano_versao v ON v.id = po.versao_id WHERE v.plano_id = ?'],
        'fotografico_captura_imagem' => ['fotografico_captura_imagem', 'SELECT COUNT(*) AS total FROM fotografico_captura_imagem ci JOIN fotografico_captura c ON c.id = ci.captura_id JOIN fotografico_posicao po ON po.id = c.posicao_id JOIN fotografico_plano_versao v ON v.id = po.versao_id WHERE v.plano_id = ?'],
        'fotografico_sla' => ['fotografico_sla', 'SELECT COUNT(*) AS total FROM fotografico_sla WHERE plano_id = ?'],
        'fotografico_hold' => ['fotografico_hold', 'SELECT COUNT(*) AS total FROM fotografico_hold WHERE plano_id = ?'],
        'fotografico_sla_pausa' => ['fotografico_sla_pausa', 'SELECT COUNT(*) AS total FROM fotografico_sla_pausa sp JOIN fotografico_sla s ON s.id = sp.sla_id WHERE s.plano_id = ?'],
        'fotografico_pendencia' => ['fotografico_pendencia', 'SELECT COUNT(*) AS total FROM fotografico_pendencia WHERE plano_id = ?'],
        'fotografico_pendencia_cobranca_envio' => ['fotografico_pendencia_cobranca_envio', 'SELECT COUNT(*) AS total FROM fotografico_pendencia_cobranca_envio ce JOIN fotografico_pendencia pe ON pe.id = ce.pendencia_id WHERE pe.plano_id = ?'],
        'fotografico_execucao' => ['fotografico_execucao', 'SELECT COUNT(*) AS total FROM fotografico_execucao WHERE plano_id = ?'],
        'fotografico_execucao_captura' => ['fotografico_execucao_captura', 'SELECT COUNT(*) AS total FROM fotografico_execucao_captura ec JOIN fotografico_execucao e ON e.id = ec.execucao_id WHERE e.plano_id = ?'],
        'fotografico_execucao_conferencia' => ['fotografico_execucao_conferencia', 'SELECT COUNT(*) AS total FROM fotografico_execucao_conferencia ec JOIN fotografico_execucao e ON e.id = ec.execucao_id WHERE e.plano_id = ?'],
        'fotografico_anexo' => ['fotografico_anexo', 'SELECT COUNT(*) AS total FROM fotografico_anexo WHERE plano_id = ?'],
        'fotografico_evento' => ['fotografico_evento', 'SELECT COUNT(*) AS total FROM fotografico_evento WHERE plano_id = ?'],
        'fotografico_notificacao_envio' => ['fotografico_notificacao_envio', 'SELECT COUNT(*) AS total FROM fotografico_notificacao_envio WHERE plano_id = ?'],
    ];

    $counts = [];
    foreach ($queries as $label => [$table, $sql]) {
        $counts[$label] = fotografico_reset_table_exists($conn, $table)
            ? fotografico_reset_count($conn, $sql, $planId)
            : 0;
    }
    return $counts;
}

function fotografico_reset_print(array $payload, bool $json): void
{
    if ($json) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
        return;
    }

    echo 'Plano #' . $payload['plano_id'] . ' — ' . $payload['modo'] . PHP_EOL;
    foreach ($payload['contagens'] as $table => $count) {
        echo str_pad($table, 42) . $count . PHP_EOL;
    }
    if (!empty($payload['observacao'])) {
        echo PHP_EOL . $payload['observacao'] . PHP_EOL;
    }
}

$conn = conectarBanco();
$json = isset($options['json']);
$before = fotografico_reset_collect($conn, (int) $planId);

if (!isset($options['apply'])) {
    fotografico_reset_print([
        'plano_id' => (int) $planId,
        'modo' => 'DRY-RUN — nenhum dado foi apagado',
        'contagens' => $before,
        'observacao' => 'Use --apply para remover o plano e todos os dependentes em cascata. Arquivos físicos não são removidos.',
    ], $json);
    $conn->close();
    exit(0);
}

try {
    $conn->begin_transaction();
    $lock = $conn->prepare('SELECT id FROM fotografico_plano WHERE id = ? FOR UPDATE');
    $lock->bind_param('i', $planId);
    $lock->execute();
    if (!$lock->get_result()->fetch_assoc()) {
        $lock->close();
        throw new RuntimeException('Plano fotográfico não encontrado. Nenhum dado foi apagado.');
    }
    $lock->close();

    $delete = $conn->prepare('DELETE FROM fotografico_plano WHERE id = ?');
    $delete->bind_param('i', $planId);
    $delete->execute();
    $deletedPlans = $delete->affected_rows;
    $delete->close();

    $after = fotografico_reset_collect($conn, (int) $planId);
    if (array_sum($after) !== 0) {
        throw new RuntimeException('A verificação encontrou registros vinculados após a exclusão; a transação será desfeita.');
    }
    $conn->commit();

    fotografico_reset_print([
        'plano_id' => (int) $planId,
        'modo' => 'APLICADO',
        'contagens' => $before,
        'plano_removido' => $deletedPlans,
        'observacao' => 'Registros do banco removidos. Arquivos físicos de upload foram preservados.',
    ], $json);
    $conn->close();
} catch (Throwable $error) {
    $conn->rollback();
    $conn->close();
    fwrite(STDERR, 'Falha: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}


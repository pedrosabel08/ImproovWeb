<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

function arg_value(string $name, $default = null)
{
    global $argv, $isCli;

    if ($isCli) {
        foreach (array_slice($argv ?? [], 1) as $arg) {
            if (str_starts_with($arg, '--' . $name . '=')) {
                return substr($arg, strlen('--' . $name . '='));
            }
        }
        return $default;
    }

    return $_GET[$name] ?? $_POST[$name] ?? $default;
}

function valid_date(string $date): bool
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
}

$loteId = (int) arg_value('lote_id', 0);
$date = trim((string) arg_value('data', ''));

if ($loteId <= 0 || !valid_date($date)) {
    echo json_encode([
        'success' => false,
        'message' => 'Informe lote_id e data no formato YYYY-MM-DD.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit($isCli ? 1 : 0);
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare('UPDATE pre_alt_lote SET data_finalizacao_cliente = ? WHERE id = ?');
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel preparar atualizacao do lote.');
    }
    $stmt->bind_param('si', $date, $loteId);
    $stmt->execute();
    $loteRows = $stmt->affected_rows;
    $stmt->close();

    $sqlCr = "
        UPDATE cobranca_review cr
        JOIN pre_alt_lote_batches plb ON plb.review_batch_id = cr.review_batch_id
        SET cr.resolved_at = CONCAT(?, ' ', TIME(COALESCE(cr.resolved_at, cr.status_changed_at, cr.updated_at, NOW()))),
            cr.status_changed_at = CONCAT(?, ' ', TIME(COALESCE(cr.status_changed_at, cr.resolved_at, cr.updated_at, NOW()))),
            cr.updated_at = CONCAT(?, ' ', TIME(COALESCE(cr.updated_at, cr.resolved_at, cr.status_changed_at, NOW())))
        WHERE plb.pre_alt_lote_id = ?
          AND cr.status = 'RESOLVED'";
    $stmt = $conn->prepare($sqlCr);
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel preparar atualizacao da cobranca.');
    }
    $stmt->bind_param('sssi', $date, $date, $date, $loteId);
    $stmt->execute();
    $cobrancaRows = $stmt->affected_rows;
    $stmt->close();

    $sqlRb = "
        UPDATE review_batch rb
        JOIN pre_alt_lote_batches plb ON plb.review_batch_id = rb.id
        SET rb.updated_at = CONCAT(?, ' ', TIME(COALESCE(rb.updated_at, NOW())))
        WHERE plb.pre_alt_lote_id = ?
          AND rb.status = 'RESOLVED'";
    $stmt = $conn->prepare($sqlRb);
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel preparar atualizacao dos batches.');
    }
    $stmt->bind_param('si', $date, $loteId);
    $stmt->execute();
    $batchRows = $stmt->affected_rows;
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'lote_id' => $loteId,
        'data' => $date,
        'lote_rows' => $loteRows,
        'cobranca_rows' => $cobrancaRows,
        'review_batch_rows' => $batchRows,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit($isCli ? 1 : 0);
} finally {
    $conn->close();
}

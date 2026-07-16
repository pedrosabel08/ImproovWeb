<?php

declare(strict_types=1);

/*
PASSO A PASSO — REVERTER UMA APROVACAO/RESOLUCAO FEITA POR ENGANO

1. Abra o PowerShell na raiz do projeto:
   cd C:\xampp\htdocs\ImproovWeb
2. Confira exatamente o que sera alterado (nao grava nada):
   php manutencao\reverter_review_batch.php --batch-id=88
3. Leia a quantidade de imagens e os lotes de Pre-Alteracao listados.
4. Se os dados estiverem corretos, aplique a reversao:
   php manutencao\reverter_review_batch.php --batch-id=88 --apply
5. Atualize Entregas e Pre-Alteracao no navegador. O lote volta a aparecer
   como aberto em Entregas e as imagens do batch deixam a Pre-Alteracao.

SEGURANCA
- Sem --apply, este arquivo e somente uma simulacao.
- Ele aceita somente review_batch que esteja RESOLVED.
- Remove somente os pre_alt_itens e pre_alt_lote_batches do batch informado.
- Um lote de Pre-Alteracao so e removido se ficar completamente vazio.
*/

function usage(): void
{
    echo <<<TXT
Uso:
  php manutencao\\reverter_review_batch.php --batch-id=88
  php manutencao\\reverter_review_batch.php --batch-id=88 --apply

TXT;
}

function countForLote(mysqli $conn, string $table, int $loteId): int
{
    $allowedTables = ['pre_alt_itens', 'pre_alt_lote_batches'];
    if (!in_array($table, $allowedTables, true)) {
        throw new InvalidArgumentException('Tabela de contagem invalida.');
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM {$table} WHERE pre_alt_lote_id = ?");
    if (!$stmt) {
        throw new RuntimeException('Falha ao preparar a contagem do lote de Pre-Alteracao.');
    }
    $stmt->bind_param('i', $loteId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0);
}

$options = getopt('', ['batch-id:', 'apply', 'help']);
if (isset($options['help']) || !isset($options['batch-id'])) {
    usage();
    exit(isset($options['help']) ? 0 : 1);
}

$batchId = filter_var($options['batch-id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($batchId === false) {
    fwrite(STDERR, "--batch-id deve ser um inteiro positivo.\n");
    exit(1);
}

$apply = array_key_exists('apply', $options);

require_once __DIR__ . '/../conexao.php';

try {
    $stmtBatch = $conn->prepare(
        'SELECT
            rb.id,
            rb.status AS batch_status,
            rb.entrega_id,
            rb.data_entrega_lote,
            cr.status AS cobranca_status,
            cr.due_at,
            cr.resolved_at,
            cr.resolved_reason
         FROM review_batch rb
         LEFT JOIN cobranca_review cr ON cr.review_batch_id = rb.id
         WHERE rb.id = ?'
    );
    if (!$stmtBatch) {
        throw new RuntimeException('Nao foi possivel consultar o lote de Review.');
    }
    $stmtBatch->bind_param('i', $batchId);
    $stmtBatch->execute();
    $batch = $stmtBatch->get_result()->fetch_assoc();
    $stmtBatch->close();

    if (!$batch) {
        throw new RuntimeException("Review batch {$batchId} nao encontrado.");
    }

    $stmtPreAlt = $conn->prepare(
        'SELECT
            plb.pre_alt_lote_id,
            pal.status AS pre_alt_status,
            (
                SELECT COUNT(*)
                FROM pre_alt_itens pai
                INNER JOIN review_batch_items rbi ON rbi.id = pai.review_batch_item_id
                WHERE pai.pre_alt_lote_id = plb.pre_alt_lote_id
                  AND rbi.review_batch_id = ?
            ) AS itens_do_batch
         FROM pre_alt_lote_batches plb
         INNER JOIN pre_alt_lote pal ON pal.id = plb.pre_alt_lote_id
         WHERE plb.review_batch_id = ?
         ORDER BY plb.pre_alt_lote_id'
    );
    if (!$stmtPreAlt) {
        throw new RuntimeException('Nao foi possivel consultar os vinculos de Pre-Alteracao.');
    }
    $stmtPreAlt->bind_param('ii', $batchId, $batchId);
    $stmtPreAlt->execute();
    $preAltLots = $stmtPreAlt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtPreAlt->close();

    $stmtItemCount = $conn->prepare(
        'SELECT COUNT(*) AS total
         FROM pre_alt_itens pai
         INNER JOIN review_batch_items rbi ON rbi.id = pai.review_batch_item_id
         WHERE rbi.review_batch_id = ?'
    );
    if (!$stmtItemCount) {
        throw new RuntimeException('Nao foi possivel contar os itens de Pre-Alteracao.');
    }
    $stmtItemCount->bind_param('i', $batchId);
    $stmtItemCount->execute();
    $preAltItemCount = (int) (($stmtItemCount->get_result()->fetch_assoc())['total'] ?? 0);
    $stmtItemCount->close();

    $report = [
        'modo' => $apply ? 'APLICAR' : 'SIMULACAO',
        'review_batch' => $batch,
        'pre_alt_itens_a_remover' => $preAltItemCount,
        'pre_alt_lotes_vinculados' => $preAltLots,
    ];

    if (!$apply) {
        $report['proximo_passo'] = "Revise os dados e rode novamente com --apply para reabrir o lote {$batchId}.";
        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(0);
    }

    if (strtoupper((string) $batch['batch_status']) !== 'RESOLVED') {
        throw new RuntimeException('Reversao cancelada: o review_batch nao esta RESOLVED.');
    }
    if (empty($batch['cobranca_status'])) {
        throw new RuntimeException('Reversao cancelada: a cobranca_review do lote nao foi encontrada.');
    }

    $conn->begin_transaction();

    $stmtLock = $conn->prepare(
        'SELECT rb.status, cr.status AS cobranca_status
         FROM review_batch rb
         INNER JOIN cobranca_review cr ON cr.review_batch_id = rb.id
         WHERE rb.id = ?
         FOR UPDATE'
    );
    if (!$stmtLock) {
        throw new RuntimeException('Nao foi possivel bloquear o lote para reversao.');
    }
    $stmtLock->bind_param('i', $batchId);
    $stmtLock->execute();
    $locked = $stmtLock->get_result()->fetch_assoc();
    $stmtLock->close();

    if (!$locked || strtoupper((string) $locked['status']) !== 'RESOLVED') {
        throw new RuntimeException('O lote mudou de estado durante a operacao; nenhuma alteracao foi feita.');
    }

    $stmtDeleteItems = $conn->prepare(
        'DELETE pai
         FROM pre_alt_itens pai
         INNER JOIN review_batch_items rbi ON rbi.id = pai.review_batch_item_id
         WHERE rbi.review_batch_id = ?'
    );
    if (!$stmtDeleteItems) {
        throw new RuntimeException('Nao foi possivel remover os itens de Pre-Alteracao.');
    }
    $stmtDeleteItems->bind_param('i', $batchId);
    $stmtDeleteItems->execute();
    $removedItems = $stmtDeleteItems->affected_rows;
    $stmtDeleteItems->close();

    $stmtDeleteLinks = $conn->prepare('DELETE FROM pre_alt_lote_batches WHERE review_batch_id = ?');
    if (!$stmtDeleteLinks) {
        throw new RuntimeException('Nao foi possivel remover os vinculos de Pre-Alteracao.');
    }
    $stmtDeleteLinks->bind_param('i', $batchId);
    $stmtDeleteLinks->execute();
    $removedLinks = $stmtDeleteLinks->affected_rows;
    $stmtDeleteLinks->close();

    $removedEmptyLots = 0;
    foreach ($preAltLots as $preAltLot) {
        $loteId = (int) $preAltLot['pre_alt_lote_id'];
        if (countForLote($conn, 'pre_alt_itens', $loteId) !== 0 || countForLote($conn, 'pre_alt_lote_batches', $loteId) !== 0) {
            continue;
        }

        $stmtDeleteLot = $conn->prepare('DELETE FROM pre_alt_lote WHERE id = ?');
        if (!$stmtDeleteLot) {
            throw new RuntimeException('Nao foi possivel remover um lote vazio de Pre-Alteracao.');
        }
        $stmtDeleteLot->bind_param('i', $loteId);
        $stmtDeleteLot->execute();
        $removedEmptyLots += $stmtDeleteLot->affected_rows;
        $stmtDeleteLot->close();
    }

    $note = 'Lote reaberto por reversao de aprovacao/resolucao feita por engano.';
    $stmtBilling = $conn->prepare(
        "UPDATE cobranca_review
         SET status = 'PENDING',
             resolved_at = NULL,
             resolved_reason = NULL,
             snooze_until = NULL,
             overdue_days = CASE WHEN due_at < NOW() THEN GREATEST(DATEDIFF(CURDATE(), DATE(due_at)), 0) ELSE 0 END,
             status_changed_at = NOW(),
             status_changed_by = NULL,
             last_action_note = ?
         WHERE review_batch_id = ?"
    );
    if (!$stmtBilling) {
        throw new RuntimeException('Nao foi possivel reabrir a cobranca do lote.');
    }
    $stmtBilling->bind_param('si', $note, $batchId);
    $stmtBilling->execute();
    $stmtBilling->close();

    $stmtReview = $conn->prepare("UPDATE review_batch SET status = 'OPEN', batch_active_slot = 1, updated_at = NOW() WHERE id = ?");
    if (!$stmtReview) {
        throw new RuntimeException('Nao foi possivel reabrir o lote de Review.');
    }
    $stmtReview->bind_param('i', $batchId);
    $stmtReview->execute();
    $stmtReview->close();

    $conn->commit();
    $report['resultado'] = [
        'review_batch_status' => 'OPEN',
        'cobranca_review_status' => 'PENDING',
        'pre_alt_itens_removidos' => $removedItems,
        'pre_alt_vinculos_removidos' => $removedLinks,
        'pre_alt_lotes_vazios_removidos' => $removedEmptyLots,
    ];
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    if ($conn->errno === 0) {
        // A transacao pode ainda nao ter sido iniciada; rollback e inofensivo nesse caso.
    }
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

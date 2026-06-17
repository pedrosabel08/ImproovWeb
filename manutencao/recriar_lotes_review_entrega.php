<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../Entregas/review_cobranca_lib.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['idcolaborador']) && !isset($_SESSION['idusuario'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sessao expirada. Faca login novamente.']);
        exit;
    }
}

function maintenance_arg(string $name, $default = null)
{
    global $argv, $isCli;

    if ($isCli) {
        foreach (array_slice($argv ?? [], 1) as $arg) {
            if (str_starts_with($arg, '--' . $name . '=')) {
                return substr($arg, strlen('--' . $name . '='));
            }
            if ($arg === '--' . $name) {
                return '1';
            }
        }
        return $default;
    }

    return $_GET[$name] ?? $_POST[$name] ?? $default;
}

function find_open_review_batch(mysqli $conn, int $entregaId, string $dataLote): int
{
    $stmt = $conn->prepare(
        "SELECT id
           FROM review_batch
          WHERE entrega_id = ?
            AND data_entrega_lote = ?
            AND status IN ('OPEN', 'OVERDUE', 'NOTIFIED', 'SNOOZED')
          ORDER BY review_round DESC, id DESC
          LIMIT 1"
    );
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('is', $entregaId, $dataLote);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['id'] : 0;
}

function create_review_batch(mysqli $conn, int $entregaId, string $dataLote, string $changedAt): int
{
    $reviewRound = 1;
    $stmtRound = $conn->prepare('SELECT COALESCE(MAX(review_round), 0) + 1 AS next_round FROM review_batch WHERE entrega_id = ? AND data_entrega_lote = ?');
    if ($stmtRound) {
        $stmtRound->bind_param('is', $entregaId, $dataLote);
        $stmtRound->execute();
        $row = $stmtRound->get_result()->fetch_assoc();
        $stmtRound->close();
        $reviewRound = (int) ($row['next_round'] ?? 1);
    }

    $stmt = $conn->prepare(
        "INSERT INTO review_batch (entrega_id, data_entrega_lote, review_round, status, batch_active_slot, created_at, updated_at)
         VALUES (?, ?, ?, 'OPEN', 1, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel criar o lote de review.');
    }

    $stmt->bind_param('isiss', $entregaId, $dataLote, $reviewRound, $changedAt, $changedAt);
    $stmt->execute();
    $batchId = (int) $conn->insert_id;
    $stmt->close();

    return $batchId;
}

function has_active_review_batch_item(mysqli $conn, int $entregaItemId, int $imagemId): bool
{
    $stmt = $conn->prepare(
        'SELECT rbi.id
           FROM review_batch_items rbi
           JOIN review_batch rb ON rb.id = rbi.review_batch_id
          WHERE rb.entrega_id = ?
            AND rbi.entrega_item_id = ?
            AND rbi.imagem_id = ?
            AND rbi.left_rvw_at IS NULL
          LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }

    $entregaId = (int) maintenance_arg('entrega_id', 0);
    $stmt->bind_param('iii', $entregaId, $entregaItemId, $imagemId);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function fetch_delivered_items(mysqli $conn, int $entregaId): array
{
    $stmt = $conn->prepare(
        "SELECT
            ei.id AS entrega_item_id,
            ei.imagem_id,
            ei.data_entregue,
            DATE(ei.data_entregue) AS data_lote,
            i.imagem_nome
         FROM entregas_itens ei
         JOIN entregas e ON e.id = ei.entrega_id
         JOIN imagens_cliente_obra i ON i.idimagens_cliente_obra = ei.imagem_id
         WHERE ei.entrega_id = ?
           AND ei.data_entregue IS NOT NULL
           AND COALESCE(e.tipo_entrega, 'PADRAO') <> 'P00'
         ORDER BY DATE(ei.data_entregue), ei.data_entregue, ei.id"
    );
    if (!$stmt) {
        throw new RuntimeException('Nao foi possivel consultar os itens entregues.');
    }

    $stmt->bind_param('i', $entregaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $row['entrega_item_id'] = (int) $row['entrega_item_id'];
        $row['imagem_id'] = (int) $row['imagem_id'];
        $items[] = $row;
    }
    $stmt->close();

    return $items;
}

function rebuild_delivery_review_batches(mysqli $conn, int $entregaId, bool $apply): array
{
    if (!entregas_review_schema_ready($conn)) {
        throw new RuntimeException('Schema de lotes de review indisponivel.');
    }

    $items = fetch_delivered_items($conn, $entregaId);
    $groups = [];
    foreach ($items as $item) {
        $dataLote = (string) ($item['data_lote'] ?? '');
        if ($dataLote === '') {
            continue;
        }
        $groups[$dataLote][] = $item;
    }

    $createdBatches = [];
    $createdItems = [];
    $skippedItems = [];
    $batchByDate = [];

    foreach ($groups as $dataLote => $groupItems) {
        $batchId = find_open_review_batch($conn, $entregaId, $dataLote);
        $batchCreated = false;

        if ($apply && $batchId <= 0) {
            $batchId = create_review_batch($conn, $entregaId, $dataLote, date('Y-m-d H:i:s'));
            $batchCreated = true;
            $createdBatches[] = [
                'batch_id' => $batchId,
                'data_lote' => $dataLote,
                'itens_previstos' => count($groupItems),
            ];
        }

        $batchByDate[$dataLote] = $batchId;

        foreach ($groupItems as $item) {
            $alreadyActive = has_active_review_batch_item($conn, (int) $item['entrega_item_id'], (int) $item['imagem_id']);
            if ($alreadyActive) {
                $skippedItems[] = $item;
                continue;
            }

            if ($apply) {
                if ($batchId <= 0) {
                    throw new RuntimeException('Lote de review nao criado para a data ' . $dataLote . '.');
                }
                $enteredAt = (string) ($item['data_entregue'] ?: date('Y-m-d H:i:s'));
                $stmtItem = $conn->prepare('INSERT INTO review_batch_items (review_batch_id, entrega_item_id, imagem_id, entered_rvw_at) VALUES (?, ?, ?, ?)');
                if (!$stmtItem) {
                    throw new RuntimeException('Nao foi possivel inserir item no lote de review.');
                }
                $stmtItem->bind_param('iiis', $batchId, $item['entrega_item_id'], $item['imagem_id'], $enteredAt);
                $stmtItem->execute();
                $stmtItem->close();

                $createdItems[] = [
                    'batch_id' => $batchId,
                    'data_lote' => $dataLote,
                    'entrega_item_id' => $item['entrega_item_id'],
                    'imagem_id' => $item['imagem_id'],
                    'imagem_nome' => $item['imagem_nome'],
                ];
            } else {
                $createdItems[] = [
                    'batch_id' => $batchId,
                    'data_lote' => $dataLote,
                    'entrega_item_id' => $item['entrega_item_id'],
                    'imagem_id' => $item['imagem_id'],
                    'imagem_nome' => $item['imagem_nome'],
                ];
            }
        }

        if ($apply && $batchId > 0) {
            entregas_review_sync_batch_billing($conn, $batchId, $dataLote, date('Y-m-d H:i:s'));
        }
    }

    if (!$apply) {
        foreach ($groups as $dataLote => $groupItems) {
            if (($batchByDate[$dataLote] ?? 0) <= 0) {
                $createdBatches[] = [
                    'batch_id' => null,
                    'data_lote' => $dataLote,
                    'itens_previstos' => count($groupItems),
                ];
            }
        }
    }

    return [
        'entrega_id' => $entregaId,
        'apply' => $apply,
        'datas' => array_keys($groups),
        'total_itens_entregues' => count($items),
        'batches_criados' => $createdBatches,
        'itens_loteados' => $createdItems,
        'itens_ignorados' => $skippedItems,
    ];
}

$entregaId = (int) maintenance_arg('entrega_id', 0);
$apply = in_array((string) maintenance_arg('apply', '0'), ['1', 'true', 'sim', 'yes'], true);

if ($entregaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Informe entrega_id.'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit($isCli ? 1 : 0);
}

try {
    if ($apply) {
        $conn->begin_transaction();
    }

    $result = rebuild_delivery_review_batches($conn, $entregaId, $apply);

    if ($apply) {
        $conn->commit();
    }

    echo json_encode([
        'success' => true,
        'mode' => $apply ? 'apply' : 'dry-run',
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    if ($apply) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit($isCli ? 1 : 0);
} finally {
    $conn->close();
}

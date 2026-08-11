<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/review_cobranca_lib.php';
require_once __DIR__ . '/../PreAlteracao/pre_alt_helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Nao autenticado.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$reviewBatchId = isset($payload['review_batch_id']) && is_numeric($payload['review_batch_id'])
    ? (int) $payload['review_batch_id']
    : 0;
$entregaId = isset($payload['entrega_id']) && is_numeric($payload['entrega_id'])
    ? (int) $payload['entrega_id']
    : 0;

if ($reviewBatchId <= 0 && $entregaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Lote de review invalido.']);
    exit;
}

if (!entregas_review_schema_ready($conn)) {
    http_response_code(412);
    echo json_encode(['success' => false, 'error' => 'Estrutura de review ainda nao instalada.']);
    exit;
}

$stmt = $reviewBatchId > 0
    ? $conn->prepare(
        'SELECT rb.id, e.id AS entrega_id, e.status_id
           FROM review_batch rb
           INNER JOIN entregas e ON e.id = rb.entrega_id
          WHERE rb.id = ?
          LIMIT 1'
    )
    : $conn->prepare(
        'SELECT e.id AS entrega_id, e.status_id
           FROM entregas e
          WHERE e.id = ?
          LIMIT 1'
    );

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Nao foi possivel validar o lote de review.']);
    exit;
}

$lookupId = $reviewBatchId > 0 ? $reviewBatchId : $entregaId;
$stmt->bind_param('i', $lookupId);
$stmt->execute();
$batch = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$batch) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Lote de review nao encontrado.']);
    exit;
}

if ((int) $batch['status_id'] !== 6) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Esta acao esta disponivel somente para entregas EF.']);
    exit;
}

try {
    $conn->begin_transaction();

    $entregaId = (int) ($batch['entrega_id'] ?? $entregaId);

    // Entregas EF que nao possuem review_batch (por exemplo, entregas antigas
    // geradas pela conclusao de uma Pre-Alteracao) recebem um batch tecnico
    // resolvido, sem cobranca, apenas para manter o modelo de itens da triagem.
    if ($reviewBatchId <= 0) {
        $stmtExistingItem = $conn->prepare(
            'SELECT pai.pre_alt_lote_id
               FROM pre_alt_itens pai
               INNER JOIN pre_alt_lote l ON l.id = pai.pre_alt_lote_id
              WHERE pai.entrega_id = ?
                AND l.status <> \'CANCELADO\'
              ORDER BY pai.pre_alt_lote_id DESC
              LIMIT 1'
        );
        if ($stmtExistingItem) {
            $stmtExistingItem->bind_param('i', $entregaId);
            $stmtExistingItem->execute();
            $existingItem = $stmtExistingItem->get_result()->fetch_assoc();
            $stmtExistingItem->close();
            if ($existingItem) {
                $conn->commit();
                echo json_encode([
                    'success' => true,
                    'pre_alt_lote_id' => (int) $existingItem['pre_alt_lote_id'],
                    'already_exists' => true,
                    'message' => 'A entrega ja esta na Pre-Alteracao.',
                ]);
                exit;
            }
        }

        $stmtSynthetic = $conn->prepare(
            'SELECT rb.id
               FROM review_batch rb
               LEFT JOIN cobranca_review cr ON cr.review_batch_id = rb.id
              WHERE rb.entrega_id = ?
                AND rb.status = \'RESOLVED\'
                AND cr.id IS NULL
              ORDER BY rb.id DESC
              LIMIT 1'
        );
        if ($stmtSynthetic) {
            $stmtSynthetic->bind_param('i', $entregaId);
            $stmtSynthetic->execute();
            $synthetic = $stmtSynthetic->get_result()->fetch_assoc();
            $stmtSynthetic->close();
            $reviewBatchId = (int) ($synthetic['id'] ?? 0);
        }

        if ($reviewBatchId <= 0) {
            $today = date('Y-m-d');
            $stmtCreateBatch = $conn->prepare(
                "INSERT INTO review_batch (entrega_id, data_entrega_lote, review_round, status, batch_active_slot)
                 VALUES (?, ?, 1, 'RESOLVED', NULL)"
            );
            if (!$stmtCreateBatch) {
                throw new RuntimeException('Nao foi possivel criar o lote tecnico da entrega.');
            }
            $stmtCreateBatch->bind_param('is', $entregaId, $today);
            $stmtCreateBatch->execute();
            $reviewBatchId = (int) $stmtCreateBatch->insert_id;
            $stmtCreateBatch->close();

            $stmtImages = $conn->prepare(
                'SELECT id, imagem_id
                   FROM entregas_itens
                  WHERE entrega_id = ?
                  ORDER BY id ASC'
            );
            if (!$stmtImages) {
                throw new RuntimeException('Nao foi possivel carregar as imagens da entrega.');
            }
            $stmtImages->bind_param('i', $entregaId);
            $stmtImages->execute();
            $images = $stmtImages->get_result();
            $stmtItem = $conn->prepare(
                'INSERT INTO review_batch_items
                    (review_batch_id, entrega_item_id, imagem_id, entered_rvw_at, left_rvw_at, item_active_slot)
                 VALUES (?, ?, ?, NOW(), NOW(), NULL)'
            );
            if (!$stmtItem) {
                throw new RuntimeException('Nao foi possivel preparar os itens da entrega.');
            }
            while ($image = $images->fetch_assoc()) {
                $entregaItemId = (int) $image['id'];
                $imagemId = (int) $image['imagem_id'];
                $stmtItem->bind_param('iii', $reviewBatchId, $entregaItemId, $imagemId);
                $stmtItem->execute();
            }
            $stmtItem->close();
            $stmtImages->close();
        }
    }

    $stmtExisting = $conn->prepare(
        'SELECT l.id
           FROM pre_alt_lote_batches lb
           INNER JOIN pre_alt_lote l ON l.id = lb.pre_alt_lote_id
          WHERE lb.review_batch_id = ?
          ORDER BY l.id DESC
          LIMIT 1'
    );
    if ($stmtExisting) {
        $stmtExisting->bind_param('i', $reviewBatchId);
        $stmtExisting->execute();
        $existing = $stmtExisting->get_result()->fetch_assoc();
        $stmtExisting->close();

        if ($existing) {
            $conn->commit();
            echo json_encode([
                'success' => true,
                'pre_alt_lote_id' => (int) $existing['id'],
                'already_exists' => true,
                'message' => 'O lote ja esta na Pre-Alteracao.',
            ]);
            exit;
        }
    }

    // Inclusao manual: nao encerra o review nem cria cobranca ou pendencia.
    $loteId = pre_alt_criar_de_review_batch(
        $conn,
        $reviewBatchId,
        isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null,
        date('Y-m-d')
    );

    $conn->commit();
    echo json_encode([
        'success' => true,
        'pre_alt_lote_id' => $loteId,
        'message' => 'Lote adicionado a Pre-Alteracao sem criar pendencia.',
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/p00_delivery_helpers.php';
require_once __DIR__ . '/review_cobranca_lib.php';
require_once __DIR__ . '/../PreAlteracao/pre_alt_helpers.php';

function review_batch_action_active_p00_items(array $batch): array
{
    $items = is_array($batch['items'] ?? null) ? $batch['items'] : [];

    return array_values(array_filter($items, static function ($item) {
        return !empty($item['is_active']) && (int) ($item['p00_versao_id'] ?? 0) > 0;
    }));
}

function review_batch_action_is_p00_batch(array $batch): bool
{
    return !empty(review_batch_action_active_p00_items($batch));
}

function review_batch_action_validate_change_origin(string $origin): string
{
    $allowedOrigins = ['WhatsApp', 'Drive', 'Arquivo', 'Reunião', 'Outro'];
    if (!in_array($origin, $allowedOrigins, true)) {
        throw new RuntimeException('Origem da alteração inválida.');
    }

    return $origin;
}

function review_batch_action_validate_date(?string $date): string
{
    $date = trim((string) ($date ?? ''));
    if ($date === '') {
        return date('Y-m-d');
    }

    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        throw new RuntimeException('Data do retorno invalida.');
    }

    return $date;
}

function review_batch_action_mark_pending_upload(mysqli $conn, int $funcaoImagemId): void
{
    if ($funcaoImagemId <= 0) {
        throw new RuntimeException('Versão P00 sem função de modelagem vinculada.');
    }

    $stmt = $conn->prepare(
        'UPDATE funcao_imagem
         SET requires_file_upload = 1,
             file_uploaded_at = NULL
         WHERE idfuncao_imagem = ?'
    );
    if (!$stmt) {
        throw new RuntimeException('Não foi possível sinalizar upload final pendente.');
    }

    $stmt->bind_param('i', $funcaoImagemId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Não foi possível sinalizar upload final pendente: ' . $error);
    }

    $stmt->close();
}

function review_batch_action_fetch_funcao_imagem(mysqli $conn, int $funcaoImagemId): ?array
{
    if ($funcaoImagemId <= 0) {
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT
            fi.idfuncao_imagem,
            fi.colaborador_id,
            fi.status,
            fi.imagem_id,
            COALESCE(ico.imagem_nome, "") AS imagem_nome
         FROM funcao_imagem fi
         LEFT JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
         WHERE fi.idfuncao_imagem = ?
         LIMIT 1'
    );
    if (!$stmt) {
        throw new RuntimeException('Não foi possível carregar a função de modelagem vinculada.');
    }

    $stmt->bind_param('i', $funcaoImagemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function review_batch_action_build_change_notification(array $funcaoImagem, string $changeOrigin, string $changeOriginDetail = ''): string
{
    $imageName = trim((string) ($funcaoImagem['imagem_nome'] ?? ''));
    $originLabel = $changeOrigin === 'Outro' && $changeOriginDetail !== ''
        ? 'Outro: ' . $changeOriginDetail
        : $changeOrigin;

    if ($imageName !== '') {
        return 'Alteração solicitada na modelagem P00 da imagem ' . $imageName . '. Origem: ' . $originLabel . '.';
    }

    return 'Alteração solicitada na modelagem P00. Origem: ' . $originLabel . '.';
}

function review_batch_action_requeue_funcao_for_change(mysqli $conn, int $funcaoImagemId, string $notificationMessage): void
{
    $funcaoImagem = review_batch_action_fetch_funcao_imagem($conn, $funcaoImagemId);
    if (!$funcaoImagem) {
        throw new RuntimeException('Função de modelagem vinculada não encontrada.');
    }

    $stmtUpdate = $conn->prepare('UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?');
    if (!$stmtUpdate) {
        throw new RuntimeException('Não foi possível atualizar o status da função de modelagem.');
    }

    $newStatus = 'Não iniciado';
    $stmtUpdate->bind_param('si', $newStatus, $funcaoImagemId);
    if (!$stmtUpdate->execute()) {
        $error = $stmtUpdate->error;
        $stmtUpdate->close();
        throw new RuntimeException('Não foi possível atualizar o status da função de modelagem: ' . $error);
    }
    $stmtUpdate->close();

    $colaboradorId = isset($funcaoImagem['colaborador_id']) ? (int) $funcaoImagem['colaborador_id'] : 0;
    if ($colaboradorId <= 0) {
        throw new RuntimeException('Função de modelagem sem colaborador vinculado para notificação.');
    }

    $stmtNotify = $conn->prepare(
        'INSERT INTO notificacoes_gerais (colaborador_id, mensagem, data, lida, funcao_imagem_id)
         VALUES (?, ?, NOW(), 0, ?)'
    );
    if (!$stmtNotify) {
        throw new RuntimeException('Não foi possível criar a notificação de alteração.');
    }

    $stmtNotify->bind_param('isi', $colaboradorId, $notificationMessage, $funcaoImagemId);
    if (!$stmtNotify->execute()) {
        $error = $stmtNotify->error;
        $stmtNotify->close();
        throw new RuntimeException('Não foi possível criar a notificação de alteração: ' . $error);
    }

    $stmtNotify->close();
}

function review_batch_action_fetch_p00_version(mysqli $conn, int $versionId): ?array
{
    return call_user_func('improov_p00_fetch_version_by_id', $conn, $versionId);
}

function review_batch_action_update_p00_version_status(mysqli $conn, int $versionId, string $status): void
{
    call_user_func('improov_p00_update_version_status', $conn, $versionId, $status);
}

function review_batch_action_create_p00_followup_version(mysqli $conn, int $versionId, array $payload = []): int
{
    return (int) call_user_func('improov_p00_create_followup_version', $conn, $versionId, $payload);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}

if (!entregas_review_schema_ready($conn)) {
    http_response_code(412);
    echo json_encode(['success' => false, 'error' => 'Estrutura de review/cobrança ainda não instalada.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']);
    exit;
}

$reviewBatchId = isset($input['review_batch_id']) && is_numeric($input['review_batch_id'])
    ? (int) $input['review_batch_id']
    : 0;
$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
$reason = isset($input['reason']) ? trim((string) $input['reason']) : '';
$note = isset($input['note']) ? trim((string) $input['note']) : '';
$snoozeUntil = isset($input['snooze_until']) ? trim((string) $input['snooze_until']) : '';
$customerResponse = isset($input['customer_response']) ? strtolower(trim((string) $input['customer_response'])) : '';
$changeOrigin = isset($input['change_origin']) ? trim((string) $input['change_origin']) : '';
$changeOriginDetail = isset($input['change_origin_detail']) ? trim((string) $input['change_origin_detail']) : '';
$resolvedDate = isset($input['resolved_date']) ? trim((string) $input['resolved_date']) : '';
$actorUserId = (int) ($_SESSION['idusuario'] ?? 0);
$actorColaboradorId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;

if ($reviewBatchId <= 0 || !in_array($action, ['notify', 'snooze', 'resolve', 'ignore'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
    exit;
}

$current = entregas_review_fetch_batch($conn, $reviewBatchId);
if ($current === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Batch não encontrado.']);
    exit;
}

$currentStatus = strtoupper((string) ($current['billing_status'] ?? $current['batch_status'] ?? 'PENDING'));
if (in_array($currentStatus, ['RESOLVED', 'IGNORED'], true)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Batch já encerrado.']);
    exit;
}

$billingId = isset($current['cobranca_id']) ? (int) $current['cobranca_id'] : 0;
if ($billingId <= 0) {
    $sqlCreate = "INSERT INTO cobranca_review (
            review_batch_id,
            due_at,
            overdue_days,
            status,
            status_changed_at,
            created_at,
            updated_at
        ) VALUES (
            ?,
            DATE_ADD(CONCAT(?, ' 23:59:59'), INTERVAL 3 DAY),
            0,
            'PENDING',
            NOW(),
            NOW(),
            NOW()
        )";
    $stmtCreate = $conn->prepare($sqlCreate);
    if (!$stmtCreate) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Não foi possível criar cobrança do batch.']);
        exit;
    }
    $stmtCreate->bind_param('is', $reviewBatchId, $current['data_entrega_lote']);
    $stmtCreate->execute();
    $stmtCreate->close();
}

if (
    $action === 'resolve'
    && $customerResponse === 'change_requested'
    && !review_batch_action_is_p00_batch($current)
) {
    pre_alt_ensure_schema($conn);
}

try {
    $conn->begin_transaction();

    if ($action === 'notify') {
        $sql = "UPDATE cobranca_review
                SET status = 'NOTIFIED',
                    notification_count = notification_count + 1,
                    last_notification_at = NOW(),
                    overdue_days = CASE WHEN due_at < NOW() THEN GREATEST(DATEDIFF(CURDATE(), DATE(due_at)), 0) ELSE 0 END,
                    snooze_until = NULL,
                    status_changed_at = NOW(),
                    status_changed_by = ?,
                    last_action_note = ?
                WHERE review_batch_id = ?
                  AND status NOT IN ('RESOLVED', 'IGNORED')";
        $stmt = $conn->prepare($sql);
                $noteValue = $note !== '' ? substr($note, 0, 255) : 'Cobrança manual registrada.';
        $stmt->bind_param('isi', $actorUserId, $noteValue, $reviewBatchId);
        $stmt->execute();
        $stmt->close();

        $stmtBatch = $conn->prepare("UPDATE review_batch SET status = 'NOTIFIED', batch_active_slot = 1, updated_at = NOW() WHERE id = ? AND status NOT IN ('RESOLVED', 'IGNORED')");
        $stmtBatch->bind_param('i', $reviewBatchId);
        $stmtBatch->execute();
        $stmtBatch->close();
    }

    if ($action === 'snooze') {
        $parsedSnooze = strtotime($snoozeUntil);
        if ($parsedSnooze === false) {
            throw new RuntimeException('snooze_until inválido.');
        }
        $snoozeAt = date('Y-m-d H:i:s', $parsedSnooze);
        if ($snoozeAt <= date('Y-m-d H:i:s')) {
            throw new RuntimeException('snooze_until precisa estar no futuro.');
        }

        $sql = "UPDATE cobranca_review
                SET status = 'SNOOZED',
                    snooze_until = ?,
                    status_changed_at = NOW(),
                    status_changed_by = ?,
                    last_action_note = ?
                WHERE review_batch_id = ?
                  AND status NOT IN ('RESOLVED', 'IGNORED')";
        $stmt = $conn->prepare($sql);
                $noteValue = $note !== '' ? substr($note, 0, 255) : 'Cobrança pausada manualmente.';
        $stmt->bind_param('sisi', $snoozeAt, $actorUserId, $noteValue, $reviewBatchId);
        $stmt->execute();
        $stmt->close();

        $stmtBatch = $conn->prepare("UPDATE review_batch SET status = 'SNOOZED', batch_active_slot = 1, updated_at = NOW() WHERE id = ? AND status NOT IN ('RESOLVED', 'IGNORED')");
        $stmtBatch->bind_param('i', $reviewBatchId);
        $stmtBatch->execute();
        $stmtBatch->close();
    }

    if ($action === 'resolve') {
        $resolvedDate = review_batch_action_validate_date($resolvedDate);
        $resolvedAt = $resolvedDate . ' ' . date('H:i:s');
        $resolvedReason = $reason !== '' ? substr($reason, 0, 255) : 'MANUAL_RESOLVED';
        $noteValue = $note !== '' ? substr($note, 0, 255) : 'Batch resolvido manualmente.';
        $shouldCreatePreAlt = false;
        $isP00Batch = review_batch_action_is_p00_batch($current);

        if (!$isP00Batch) {
            if ($customerResponse === 'approved') {
                $resolvedReason = 'Aprovada';
                if ($note === '') {
                    $noteValue = 'Cliente aprovou o lote sem alteracoes.';
                }
            } elseif ($customerResponse === 'change_requested') {
                $resolvedReason = 'Alteracao solicitada';
                $shouldCreatePreAlt = true;
                if ($note === '') {
                    $noteValue = 'Cliente solicitou alteracoes. Lote enviado para Pre-Alteracao.';
                }
            } else {
                throw new RuntimeException('Selecione se o cliente aprovou o lote ou pediu alteracao.');
            }
        }

        if ($isP00Batch) {
            $activeP00Items = review_batch_action_active_p00_items($current);
            if (empty($activeP00Items)) {
                throw new RuntimeException('Nenhuma versão P00 ativa foi encontrada para este batch.');
            }

            if ($customerResponse === 'approved') {
                foreach ($activeP00Items as $item) {
                    $versionId = (int) ($item['p00_versao_id'] ?? 0);
                    $version = review_batch_action_fetch_p00_version($conn, $versionId);
                    if (!$version) {
                        throw new RuntimeException('Versão P00 vinculada ao batch não encontrada.');
                    }

                    review_batch_action_mark_pending_upload($conn, (int) ($version['funcao_imagem_id'] ?? 0));
                }

                $resolvedReason = 'Aprovada';
                if ($note === '') {
                    $noteValue = count($activeP00Items) > 1
                        ? 'Cliente aprovou as versões P00 do lote. Arquivo final pendente de upload organizado.'
                        : 'Cliente aprovou a versão P00. Arquivo final pendente de upload organizado.';
                }
            } elseif ($customerResponse === 'change_requested') {
                $changeOrigin = review_batch_action_validate_change_origin($changeOrigin);
                $changeOriginDetail = $changeOriginDetail !== '' ? substr($changeOriginDetail, 0, 255) : '';
                if ($changeOrigin === 'Outro' && $changeOriginDetail === '') {
                    throw new RuntimeException('Detalhe da origem é obrigatório quando a origem for Outro.');
                }

                foreach ($activeP00Items as $item) {
                    $versionId = (int) ($item['p00_versao_id'] ?? 0);
                    $version = review_batch_action_fetch_p00_version($conn, $versionId);
                    if (!$version) {
                        throw new RuntimeException('Versão P00 vinculada ao batch não encontrada.');
                    }

                    $funcaoImagemId = isset($version['funcao_imagem_id']) ? (int) $version['funcao_imagem_id'] : 0;
                    $notificationMessage = review_batch_action_build_change_notification(
                        review_batch_action_fetch_funcao_imagem($conn, $funcaoImagemId) ?? [],
                        $changeOrigin,
                        $changeOriginDetail
                    );

                    review_batch_action_requeue_funcao_for_change($conn, $funcaoImagemId, $notificationMessage);
                    review_batch_action_create_p00_followup_version($conn, $versionId, [
                        'origem_alteracao' => $changeOrigin,
                        'origem_alteracao_detalhe' => $changeOriginDetail,
                    ]);
                }

                $resolvedReason = 'Alteração solicitada';
                if ($note === '') {
                    $originLabel = $changeOrigin === 'Outro' && $changeOriginDetail !== ''
                        ? 'Outro: ' . $changeOriginDetail
                        : $changeOrigin;
                    $noteValue = count($activeP00Items) > 1
                        ? 'Cliente solicitou alterações nas versões P00 do lote via ' . $originLabel . '. Nova versão criada automaticamente.'
                        : 'Cliente solicitou alteração na versão P00 via ' . $originLabel . '. Nova versão criada automaticamente.';
                }
            } else {
                throw new RuntimeException('Resposta do cliente inválida para o batch P00.');
            }
        }

        $sql = "UPDATE cobranca_review
                SET status = 'RESOLVED',
                    resolved_at = ?,
                    resolved_reason = ?,
                    snooze_until = NULL,
                    status_changed_at = ?,
                    status_changed_by = ?,
                    last_action_note = ?
                WHERE review_batch_id = ?
                  AND status <> 'IGNORED'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssisi', $resolvedAt, $resolvedReason, $resolvedAt, $actorUserId, $noteValue, $reviewBatchId);
        $stmt->execute();
        $stmt->close();

        $stmtBatch = $conn->prepare("UPDATE review_batch SET status = 'RESOLVED', batch_active_slot = NULL, updated_at = ? WHERE id = ? AND status <> 'IGNORED'");
        $stmtBatch->bind_param('si', $resolvedAt, $reviewBatchId);
        $stmtBatch->execute();
        $stmtBatch->close();

        if ($shouldCreatePreAlt) {
            pre_alt_criar_de_review_batch($conn, $reviewBatchId, $actorColaboradorId, $resolvedDate);
        }
    }

    if ($action === 'ignore') {
        $resolvedReason = $reason !== '' ? substr($reason, 0, 255) : 'IGNORED_MANUALLY';
        $noteValue = $note !== '' ? substr($note, 0, 255) : 'Batch ignorado manualmente.';

        $sql = "UPDATE cobranca_review
                SET status = 'IGNORED',
                    resolved_at = NOW(),
                    resolved_reason = ?,
                    snooze_until = NULL,
                    status_changed_at = NOW(),
                    status_changed_by = ?,
                    last_action_note = ?
                WHERE review_batch_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sisi', $resolvedReason, $actorUserId, $noteValue, $reviewBatchId);
        $stmt->execute();
        $stmt->close();

        $stmtBatch = $conn->prepare("UPDATE review_batch SET status = 'IGNORED', batch_active_slot = NULL, updated_at = NOW() WHERE id = ?");
        $stmtBatch->bind_param('i', $reviewBatchId);
        $stmtBatch->execute();
        $stmtBatch->close();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'data' => entregas_review_fetch_batch($conn, $reviewBatchId),
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}

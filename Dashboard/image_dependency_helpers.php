<?php

declare(strict_types=1);

require_once __DIR__ . '/planned_function_helpers.php';

const DASHBOARD_FUNCAO_FINALIZACAO_ID = 4;

function dashboard_image_dependency_schema_ready(mysqli $conn): bool
{
    return dashboard_planning_column_exists($conn, 'imagens_cliente_obra', 'imagem_principal_id');
}

function dashboard_normalize_principal_id($value): ?int
{
    $id = is_numeric($value) ? (int) $value : 0;
    return $id > 0 ? $id : null;
}

function dashboard_fetch_image_dependency_context(mysqli $conn, int $imageId): ?array
{
    if ($imageId <= 0 || !dashboard_image_dependency_schema_ready($conn)) {
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT ico.idimagens_cliente_obra AS imagem_id,
                ico.obra_id,
                ico.imagem_nome,
                ico.tipo_imagem,
                ico.imagem_principal_id,
                principal.imagem_nome AS imagem_principal_nome
           FROM imagens_cliente_obra ico
      LEFT JOIN imagens_cliente_obra principal
             ON principal.idimagens_cliente_obra = ico.imagem_principal_id
          WHERE ico.idimagens_cliente_obra = ?
          LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $imageId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function dashboard_validate_image_principal(mysqli $conn, int $imageId, ?int $principalId): array
{
    if (!dashboard_image_dependency_schema_ready($conn)) {
        return ['success' => false, 'message' => 'A migration de imagens por ângulo ainda não foi aplicada.'];
    }

    $image = dashboard_fetch_image_dependency_context($conn, $imageId);
    if (!$image) {
        return ['success' => false, 'message' => 'Imagem não encontrada.'];
    }
    if ($principalId === null) {
        return ['success' => true, 'image' => $image, 'principal' => null];
    }
    if ($principalId === $imageId) {
        return ['success' => false, 'message' => 'Uma imagem não pode ser principal dela mesma.'];
    }

    $principal = dashboard_fetch_image_dependency_context($conn, $principalId);
    if (!$principal) {
        return ['success' => false, 'message' => 'A imagem principal selecionada não foi encontrada.'];
    }
    if ((int) $principal['obra_id'] !== (int) $image['obra_id']) {
        return ['success' => false, 'message' => 'A imagem principal deve pertencer à mesma obra.'];
    }
    if (!empty($principal['imagem_principal_id'])) {
        return ['success' => false, 'message' => 'Uma imagem secundária não pode ser principal de outro ângulo.'];
    }

    $stmtChildren = $conn->prepare(
        'SELECT 1 FROM imagens_cliente_obra WHERE imagem_principal_id = ? LIMIT 1'
    );
    if (!$stmtChildren) {
        return ['success' => false, 'message' => $conn->error];
    }
    $stmtChildren->bind_param('i', $imageId);
    $stmtChildren->execute();
    $hasChildren = $stmtChildren->get_result()->num_rows > 0;
    $stmtChildren->close();
    if ($hasChildren) {
        return ['success' => false, 'message' => 'Desvincule os ângulos desta imagem antes de torná-la secundária.'];
    }

    $stmtExecution = $conn->prepare(
        "SELECT 1
           FROM funcao_imagem
          WHERE imagem_id = ?
            AND funcao_id <> ?
            AND (
                colaborador_id IS NOT NULL
                OR LOWER(TRIM(COALESCE(status, ''))) NOT IN ('', 'não iniciado', 'nao iniciado')
            )
          LIMIT 1"
    );
    if (!$stmtExecution) {
        return ['success' => false, 'message' => $conn->error];
    }
    $finalizacaoId = DASHBOARD_FUNCAO_FINALIZACAO_ID;
    $stmtExecution->bind_param('ii', $imageId, $finalizacaoId);
    $stmtExecution->execute();
    $hasStartedNonFinal = $stmtExecution->get_result()->num_rows > 0;
    $stmtExecution->close();
    if ($hasStartedNonFinal) {
        return ['success' => false, 'message' => 'A imagem já possui outra etapa alocada ou iniciada e não pode virar ângulo secundário.'];
    }

    return ['success' => true, 'image' => $image, 'principal' => $principal];
}

function dashboard_sync_image_dependency_planning(mysqli $conn, int $imageId, ?int $principalId): array
{
    if (!dashboard_planning_tables_ready($conn)) {
        return ['success' => true, 'skipped' => true, 'inserted' => 0];
    }

    if ($principalId !== null) {
        $stmt = $conn->prepare(
            'UPDATE imagem_funcao_planejada
                SET status = \'CANCELADO\', updated_at = NOW()
              WHERE imagem_id = ?
                AND funcao_id <> ?
                AND funcao_imagem_id IS NULL'
        );
        if (!$stmt) return ['success' => false, 'message' => $conn->error];
        $finalizacaoId = DASHBOARD_FUNCAO_FINALIZACAO_ID;
        $stmt->bind_param('ii', $imageId, $finalizacaoId);
        if (!$stmt->execute()) {
            $message = $stmt->error;
            $stmt->close();
            return ['success' => false, 'message' => $message];
        }
        $stmt->close();

        return dashboard_upsert_planned_function($conn, $imageId, DASHBOARD_FUNCAO_FINALIZACAO_ID);
    }

    $stmtRestore = $conn->prepare(
        "UPDATE imagem_funcao_planejada ifp
           INNER JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = ifp.imagem_id
           INNER JOIN imagem_funcao_template template
                   ON LOWER(TRIM(template.tipo_imagem)) = LOWER(TRIM(ico.tipo_imagem))
                  AND template.ativo = 1
           INNER JOIN imagem_funcao_template_item item
                   ON item.template_id = template.idimagem_funcao_template
                  AND item.funcao_id = ifp.funcao_id
                  AND item.ativo = 1
             SET ifp.status = 'TODO', ifp.updated_at = NOW()
           WHERE ifp.imagem_id = ?
             AND ifp.status = 'CANCELADO'
             AND ifp.funcao_imagem_id IS NULL"
    );
    if (!$stmtRestore) return ['success' => false, 'message' => $conn->error];
    $stmtRestore->bind_param('i', $imageId);
    if (!$stmtRestore->execute()) {
        $message = $stmtRestore->error;
        $stmtRestore->close();
        return ['success' => false, 'message' => $message];
    }
    $stmtRestore->close();

    $image = dashboard_fetch_image_snapshot($conn, $imageId);
    if (!$image) return ['success' => false, 'message' => 'Imagem não encontrada.'];
    return dashboard_insert_planned_functions_for_image($conn, $imageId, (string) ($image['tipo_imagem'] ?? ''));
}

function dashboard_apply_image_principal(mysqli $conn, int $imageId, $principalValue): array
{
    $principalId = dashboard_normalize_principal_id($principalValue);
    $validation = dashboard_validate_image_principal($conn, $imageId, $principalId);
    if (empty($validation['success'])) return $validation;

    $stmt = $conn->prepare(
        'UPDATE imagens_cliente_obra SET imagem_principal_id = ? WHERE idimagens_cliente_obra = ?'
    );
    if (!$stmt) return ['success' => false, 'message' => $conn->error];
    $stmt->bind_param('ii', $principalId, $imageId);
    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        return ['success' => false, 'message' => $message];
    }
    $stmt->close();

    $planning = dashboard_sync_image_dependency_planning($conn, $imageId, $principalId);
    if (empty($planning['success'])) return $planning;

    return array_merge(
        ['success' => true, 'imagem_principal_id' => $principalId],
        $planning
    );
}

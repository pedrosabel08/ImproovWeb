<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/planned_function_helpers.php';
require_once __DIR__ . '/../helpers/pendencias_operacionais_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload inválido.']);
    exit;
}

$obraId = isset($payload['obra_id']) ? (int) $payload['obra_id'] : 0;
$changes = isset($payload['changes']) && is_array($payload['changes']) ? $payload['changes'] : [];
$actorId = isset($_SESSION['idcolaborador']) ? (int) $_SESSION['idcolaborador'] : null;

if ($obraId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Obra inválida.']);
    exit;
}

if (empty($changes)) {
    echo json_encode(['success' => true, 'message' => 'Nenhuma alteração para salvar.', 'warnings' => []]);
    exit;
}

if (!dashboard_planning_tables_ready($conn)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'As tabelas de planejamento ainda não foram aplicadas no banco.']);
    exit;
}

$warnings = [];
$updatedImages = [];

$conn->begin_transaction();

try {
    foreach ($changes as $change) {
        $imageId = isset($change['imagem_id']) ? (int) $change['imagem_id'] : 0;
        $functions = isset($change['planned_functions']) && is_array($change['planned_functions'])
            ? $change['planned_functions']
            : [];

        if ($imageId <= 0) {
            throw new RuntimeException('Imagem inválida no payload.');
        }

        $image = dashboard_fetch_image_snapshot($conn, $imageId);
        if (!$image) {
            throw new RuntimeException('Imagem não encontrada: ' . $imageId);
        }

        $desiredByFunction = [];
        foreach ($functions as $functionRow) {
            $functionId = isset($functionRow['funcao_id']) ? (int) $functionRow['funcao_id'] : 0;
            if ($functionId <= 0) {
                continue;
            }
            if (isset($desiredByFunction[$functionId])) {
                throw new RuntimeException('Função duplicada na imagem ' . $imageId . '.');
            }

            $desiredByFunction[$functionId] = [
                'ordem' => isset($functionRow['ordem']) ? (int) $functionRow['ordem'] : 1,
                'obrigatoria' => !empty($functionRow['obrigatoria']) ? 1 : 0,
                'responsavel_sugerido_id' => ($functionRow['responsavel_sugerido_id'] ?? '') === ''
                    ? null
                    : (int) $functionRow['responsavel_sugerido_id'],
            ];
        }

        $stmtExisting = $conn->prepare(
            "SELECT funcao_id, funcao_imagem_id, status
             FROM imagem_funcao_planejada
             WHERE imagem_id = ?
               AND status <> 'CANCELADO'"
        );
        if (!$stmtExisting) {
            throw new RuntimeException($conn->error);
        }

        $stmtExisting->bind_param('i', $imageId);
        $stmtExisting->execute();
        $resultExisting = $stmtExisting->get_result();
        $existingByFunction = [];
        while ($resultExisting && ($row = $resultExisting->fetch_assoc())) {
            $existingByFunction[(int) $row['funcao_id']] = $row;
        }
        $stmtExisting->close();

        foreach ($desiredByFunction as $functionId => $functionPayload) {
            $result = dashboard_upsert_planned_function($conn, $imageId, $functionId, $functionPayload, $actorId);
            if (!$result['success']) {
                throw new RuntimeException($result['message'] ?? ('Falha ao salvar função ' . $functionId));
            }
        }

        foreach ($existingByFunction as $functionId => $existingRow) {
            if (isset($desiredByFunction[$functionId])) {
                continue;
            }

            $cancelResult = dashboard_cancel_planned_function($conn, $imageId, $functionId, $actorId);
            if (!$cancelResult['success']) {
                $warnings[] = [
                    'imagem_id' => $imageId,
                    'imagem_nome' => $image['imagem_nome'] ?? '',
                    'funcao_id' => $functionId,
                    'message' => $cancelResult['message'] ?? 'Não foi possível remover a função.',
                ];
            }
        }

        pendencias_operacionais_sync_image_checklist($conn, $imageId);
        $updatedImages[] = $imageId;
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Planejamento salvo com sucesso.',
        'updated_images' => array_values(array_unique($updatedImages)),
        'warnings' => $warnings,
    ]);
} catch (Throwable $throwable) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $throwable->getMessage()]);
}

<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../conexaoMain.php';
require_once __DIR__ . '/eventos_obra_helper.php';

eventos_obra_require_auth();
eventos_obra_require_editor();

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$eventoId = isset($payload['evento_id']) ? (int) $payload['evento_id'] : 0;
$obraId = isset($payload['obra_id']) ? (int) $payload['obra_id'] : 0;

if ($eventoId <= 0 || $obraId <= 0) {
    eventos_obra_json(['success' => false, 'error' => 'Parametros invalidos'], 400);
}

eventos_obra_assert_obra_access($conn, $obraId);
$usuarioId = eventos_obra_session_usuario_id();

try {
    eventos_obra_ensure_schema($conn);
    $conn->begin_transaction();

    $stmt = $conn->prepare(
        "UPDATE eventos_obra
            SET arquivado_em = CURRENT_TIMESTAMP,
                arquivado_por = ?,
                updated_at = CURRENT_TIMESTAMP
          WHERE id = ?
            AND obra_id = ?
            AND origem_modulo = 'EVENTOS_OBRA'
            AND arquivado_em IS NULL"
    );
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar arquivamento: ' . $conn->error);
    }
    $stmt->bind_param('iii', $usuarioId, $eventoId, $obraId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        throw new InvalidArgumentException('Evento nao encontrado ou ja arquivado.');
    }

    $stmtRefs = $conn->prepare(
        "UPDATE evento_obra_referencias
            SET arquivado_em = CURRENT_TIMESTAMP,
                arquivado_por = ?
          WHERE evento_id = ?
            AND obra_id = ?
            AND arquivado_em IS NULL"
    );
    if ($stmtRefs) {
        $stmtRefs->bind_param('iii', $usuarioId, $eventoId, $obraId);
        $stmtRefs->execute();
        $stmtRefs->close();
    }

    $conn->commit();
    eventos_obra_json(['success' => true]);
} catch (InvalidArgumentException $e) {
    if ($conn) {
        try { $conn->rollback(); } catch (Throwable $_) {}
    }
    eventos_obra_json(['success' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    if ($conn) {
        try { $conn->rollback(); } catch (Throwable $_) {}
    }
    eventos_obra_json(['success' => false, 'error' => 'Erro interno', 'details' => $e->getMessage()], 500);
}


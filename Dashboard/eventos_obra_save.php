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

$eventoId = isset($_POST['evento_id']) ? (int) $_POST['evento_id'] : 0;
$obraId = isset($_POST['obra_id']) ? (int) $_POST['obra_id'] : 0;
$tipoEvento = trim((string) ($_POST['tipo_evento'] ?? ''));
$dataEvento = eventos_obra_norm_date($_POST['data_evento'] ?? null);
$horaEvento = eventos_obra_norm_time($_POST['hora_evento'] ?? null);
$participantes = trim((string) ($_POST['participantes'] ?? ''));
$ata = trim((string) ($_POST['ata'] ?? ''));

eventos_obra_assert_obra_access($conn, $obraId);

if ($tipoEvento === '' || !$dataEvento) {
    eventos_obra_json(['success' => false, 'error' => 'Informe tipo e data do evento'], 400);
}

$responsavelId = eventos_obra_session_colaborador_id();
$usuarioId = eventos_obra_session_usuario_id();

try {
    eventos_obra_ensure_schema($conn);
    $conn->begin_transaction();

    if ($eventoId > 0) {
        $stmtCheck = $conn->prepare(
            "SELECT id
               FROM eventos_obra
              WHERE id = ?
                AND obra_id = ?
                AND origem_modulo = 'EVENTOS_OBRA'
                AND arquivado_em IS NULL
              LIMIT 1"
        );
        if (!$stmtCheck) {
            throw new RuntimeException('Erro ao preparar validacao do evento: ' . $conn->error);
        }
        $stmtCheck->bind_param('ii', $eventoId, $obraId);
        $stmtCheck->execute();
        $exists = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();
        if (!$exists) {
            throw new InvalidArgumentException('Evento nao encontrado para esta obra.');
        }

        $descricao = $tipoEvento;
        $stmt = $conn->prepare(
            "UPDATE eventos_obra
                SET tipo_evento = ?,
                    descricao = ?,
                    data_evento = ?,
                    hora_evento = ?,
                    participantes = ?,
                    ata = ?,
                    updated_at = CURRENT_TIMESTAMP
              WHERE id = ?
                AND obra_id = ?"
        );
        if (!$stmt) {
            throw new RuntimeException('Erro ao preparar atualizacao: ' . $conn->error);
        }
        $stmt->bind_param('ssssssii', $tipoEvento, $descricao, $dataEvento, $horaEvento, $participantes, $ata, $eventoId, $obraId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Erro ao atualizar evento: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        $descricao = $tipoEvento;
        $origemModulo = 'EVENTOS_OBRA';
        $stmt = $conn->prepare(
            "INSERT INTO eventos_obra
                (obra_id, responsavel_id, data_evento, hora_evento, descricao, participantes, ata, tipo_evento, origem_modulo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            throw new RuntimeException('Erro ao preparar criacao: ' . $conn->error);
        }
        $stmt->bind_param('iisssssss', $obraId, $responsavelId, $dataEvento, $horaEvento, $descricao, $participantes, $ata, $tipoEvento, $origemModulo);
        if (!$stmt->execute()) {
            throw new RuntimeException('Erro ao criar evento: ' . $stmt->error);
        }
        $eventoId = (int) $conn->insert_id;
        $stmt->close();
    }

    $urls = [];
    if (isset($_POST['referencias_urls']) && is_array($_POST['referencias_urls'])) {
        $urls = $_POST['referencias_urls'];
    } elseif (isset($_POST['referencias_urls'])) {
        $urls = preg_split('/\r\n|\r|\n/', (string) $_POST['referencias_urls']);
    }
    foreach ($urls as $url) {
        eventos_obra_add_url_ref($conn, $eventoId, $obraId, (string) $url, $usuarioId);
    }

    if (isset($_FILES['referencias_uploads'])) {
        foreach (eventos_obra_normalize_files_array($_FILES['referencias_uploads']) as $file) {
            eventos_obra_store_upload($conn, $eventoId, $obraId, $file, $usuarioId);
        }
    }

    $conn->commit();

    eventos_obra_json([
        'success' => true,
        'data' => [
            'id' => $eventoId,
            'obra_id' => $obraId,
        ],
    ]);
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


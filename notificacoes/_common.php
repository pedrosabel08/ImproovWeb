<?php

require_once __DIR__ . '/../config/session_bootstrap.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include __DIR__ . '/../conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: ../index.html');
    exit();
}

$nivel_acesso = $_SESSION['nivel_acesso'] ?? null;
if ((int)$nivel_acesso !== 1) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit();
}

include __DIR__ . '/../conexaoMain.php';
$connMain = conectarBanco();
$obras = obterObras($connMain);
$obras_inativas = obterObras($connMain, 1);
$funcoes = obterFuncoes($connMain);
$connMain->close();

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function toMysqlDateTimeOrNull($datetimeLocal)
{
    $datetimeLocal = trim((string)$datetimeLocal);
    if ($datetimeLocal === '') {
        return null;
    }

    // input datetime-local: YYYY-MM-DDTHH:MM
    $datetimeLocal = str_replace('T', ' ', $datetimeLocal);

    // Append seconds to match DATETIME
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $datetimeLocal)) {
        return $datetimeLocal . ':00';
    }

    return null;
}

function toDatetimeLocalValue($mysqlDatetime)
{
    if (!$mysqlDatetime) {
        return '';
    }

    // MySQL DATETIME: YYYY-MM-DD HH:MM:SS -> datetime-local: YYYY-MM-DDTHH:MM
    return str_replace(' ', 'T', substr($mysqlDatetime, 0, 16));
}

function getAllUsuarios($conn)
{
    $usuarios = [];
    $res = $conn->query("SELECT idusuario, nome_usuario, ativo, idcolaborador FROM usuario WHERE ativo = 1 ORDER BY nome_usuario ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $usuarios[] = $row;
        }
    }
    return $usuarios;
}

function computeRecipientUserIds($conn, $segmentacaoTipo, $alvoIds)
{
    $segmentacaoTipo = (string)$segmentacaoTipo;
    $alvoIds = is_array($alvoIds) ? array_values(array_filter(array_map('intval', $alvoIds))) : [];

    if ($segmentacaoTipo === 'geral') {
        $ids = [];
        $res = $conn->query("SELECT idusuario FROM usuario WHERE ativo = 1");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int)$row['idusuario'];
            }
        }
        return array_values(array_unique($ids));
    }

    if ($segmentacaoTipo === 'pessoa') {
        if (empty($alvoIds)) {
            return [];
        }

        // Confere se usuários existem e estão ativos
        $placeholders = implode(',', array_fill(0, count($alvoIds), '?'));
        $types = str_repeat('i', count($alvoIds));
        $sql = "SELECT idusuario FROM usuario WHERE ativo = 1 AND idusuario IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$alvoIds);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $ids[] = (int)$row['idusuario'];
        }
        $stmt->close();
        return array_values(array_unique($ids));
    }

    if ($segmentacaoTipo === 'funcao') {
        if (empty($alvoIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($alvoIds), '?'));
        $types = str_repeat('i', count($alvoIds));

        $sql = "SELECT DISTINCT u.idusuario
                FROM usuario u
                JOIN funcao_colaborador fc ON fc.colaborador_id = u.idcolaborador
                WHERE u.ativo = 1 AND fc.funcao_id IN ($placeholders)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$alvoIds);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $ids[] = (int)$row['idusuario'];
        }
        $stmt->close();
        return array_values(array_unique($ids));
    }

    if ($segmentacaoTipo === 'projeto') {
        if (empty($alvoIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($alvoIds), '?'));
        $types = str_repeat('i', count($alvoIds));

        // Pessoas envolvidas no projeto: colaboradores com funcao_imagem associada a imagens da obra
        $sql = "SELECT DISTINCT u.idusuario
                FROM usuario u
                JOIN funcao_imagem fi ON fi.colaborador_id = u.idcolaborador
                JOIN imagens_cliente_obra ico ON ico.idimagens_cliente_obra = fi.imagem_id
                WHERE u.ativo = 1 AND ico.obra_id IN ($placeholders)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$alvoIds);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($res && ($row = $res->fetch_assoc())) {
            $ids[] = (int)$row['idusuario'];
        }
        $stmt->close();
        return array_values(array_unique($ids));
    }

    return [];
}

function replaceTargetsAndRecipients($conn, $notificacaoId, $segmentacaoTipo, $alvoIds)
{
    $notificacaoId = (int)$notificacaoId;
    $segmentacaoTipo = (string)$segmentacaoTipo;
    $alvoIds = is_array($alvoIds) ? array_values(array_filter(array_map('intval', $alvoIds))) : [];

    // Alvos
    $stmtDel = $conn->prepare('DELETE FROM notificacoes_alvos WHERE notificacao_id = ?');
    if ($stmtDel) {
        $stmtDel->bind_param('i', $notificacaoId);
        $stmtDel->execute();
        $stmtDel->close();
    }

    if ($segmentacaoTipo !== 'geral') {
        $stmtIns = $conn->prepare('INSERT INTO notificacoes_alvos (notificacao_id, tipo, alvo_id) VALUES (?, ?, ?)');
        if ($stmtIns) {
            foreach ($alvoIds as $alvoId) {
                $stmtIns->bind_param('isi', $notificacaoId, $segmentacaoTipo, $alvoId);
                $stmtIns->execute();
            }
            $stmtIns->close();
        }
    }

    // Destinatários (recalcula a lista) — OBS: atualizar segmentação reseta status visto/confirmado nesta etapa.
    $stmtDel2 = $conn->prepare('DELETE FROM notificacoes_destinatarios WHERE notificacao_id = ?');
    if ($stmtDel2) {
        $stmtDel2->bind_param('i', $notificacaoId);
        $stmtDel2->execute();
        $stmtDel2->close();
    }

    $usuarioIds = computeRecipientUserIds($conn, $segmentacaoTipo, $alvoIds);
    if (empty($usuarioIds)) {
        return 0;
    }

    $stmtIns2 = $conn->prepare('INSERT INTO notificacoes_destinatarios (notificacao_id, usuario_id) VALUES (?, ?)');
    if (!$stmtIns2) {
        return 0;
    }

    foreach ($usuarioIds as $usuarioId) {
        $stmtIns2->bind_param('ii', $notificacaoId, $usuarioId);
        $stmtIns2->execute();
    }

    $stmtIns2->close();
    return count($usuarioIds);
}

function saveUploadedPdf($fileField, $existingPath = null)
{
    if (!isset($_FILES[$fileField]) || !is_array($_FILES[$fileField])) {
        return [null, null, $existingPath];
    }

    $file = $_FILES[$fileField];
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null, $existingPath];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, null, $existingPath];
    }

    $originalName = (string)$file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExt = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'];
    if (!in_array($ext, $allowedExt, true)) {
        return [null, null, $existingPath];
    }

    $uploadDir = __DIR__ . '/../uploads/notificacao';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    $safeName = 'noti_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $uploadDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return [null, null, $existingPath];
    }

    // Remove arquivo antigo se existir e estiver dentro de uploads/notificacao
    if ($existingPath) {
        $existingReal = realpath(__DIR__ . '/../' . ltrim($existingPath, '/'));
        $uploadReal = realpath($uploadDir);
        if ($existingReal && $uploadReal && str_starts_with($existingReal, $uploadReal)) {
            @unlink($existingReal);
        }
    }

    $publicPath = 'uploads/notificacao/' . $safeName;
    return [$publicPath, $originalName, $publicPath];
}

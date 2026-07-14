<?php

require_once __DIR__ . '/../config/session_bootstrap.php';

function notificacaoIsActionRequest()
{
    return strpos(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/notificacoes/actions/') !== false;
}

function notificacaoJsonResponse($ok, $message, $status = 200, array $extra = [])
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => (bool)$ok, 'message' => (string)$message], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include_once __DIR__ . '/../conexao.php';

if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    if (notificacaoIsActionRequest()) {
        notificacaoJsonResponse(false, 'Sessão expirada. Faça login novamente.', 401);
    }
    header('Location: ../index.html');
    exit();
}

$nivel_acesso = $_SESSION['nivel_acesso'] ?? null;
if ((int)$nivel_acesso !== 1) {
    if (notificacaoIsActionRequest()) {
        notificacaoJsonResponse(false, 'Acesso negado.', 403);
    }
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
    $res = $conn->query("SELECT u.idusuario, u.nome_usuario, c.ativo, u.idcolaborador FROM usuario u JOIN colaborador c ON u.idcolaborador = c.idcolaborador WHERE c.ativo = 1 ORDER BY u.nome_usuario ASC");
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

const NOTIFICACAO_MAX_ARQUIVOS = 10;
const NOTIFICACAO_MAX_TAMANHO_ARQUIVO = 10485760; // 10 MiB
const NOTIFICACAO_MAX_TAMANHO_TOTAL = 41943040; // 40 MiB

function notificacaoSanitizeHtml($html)
{
    $html = trim((string)$html);

    if ($html === '') {
        return '';
    }

    $allowedTags = [
        'p',
        'br',
        'strong',
        'b',
        'em',
        'i',
        'u',
        's',
        'ul',
        'ol',
        'li',
        'a',
        'h1',
        'h2',
        'h3',
        'blockquote',
        'span',
    ];

    $allowedClasses = '/^(ql-align-(center|right|justify)|ql-indent-[1-8])$/';

    $dom = new DOMDocument('1.0', 'UTF-8');

    $previousLibxmlState = libxml_use_internal_errors(true);

    $loaded = $dom->loadHTML(
        '<?xml encoding="utf-8" ?><div>' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlState);

    if (!$loaded) {
        return '';
    }

    $walk = function ($node) use (&$walk, $allowedTags, $allowedClasses) {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);

            if (!$child || $child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (!in_array($tag, $allowedTags, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);
                continue;
            }

            $attrs = [];

            foreach ($child->attributes as $attr) {
                $attrs[] = $attr->nodeName;
            }

            foreach ($attrs as $name) {
                $value = trim($child->getAttribute($name));
                $keep = false;

                if ($tag === 'a' && $name === 'href') {
                    $keep = preg_match('#^(https?://|mailto:|/)#i', $value) === 1;
                } elseif ($tag === 'a' && $name === 'target') {
                    $keep = in_array($value, ['_blank', '_self'], true);
                } elseif ($name === 'class') {
                    $classes = preg_split(
                        '/\s+/',
                        $value,
                        -1,
                        PREG_SPLIT_NO_EMPTY
                    );

                    $classes = array_values(array_filter(
                        $classes,
                        static fn($class) =>
                            preg_match($allowedClasses, $class) === 1
                    ));

                    if ($classes !== []) {
                        $child->setAttribute('class', implode(' ', $classes));
                        $keep = true;
                    }
                } elseif ($tag === 'li' && $name === 'data-list') {
                    $keep = in_array($value, ['ordered', 'bullet'], true);
                } elseif ($name === 'style') {
                    $safeStyle = notificacaoSanitizeStyle($value);

                    if ($safeStyle !== '') {
                        $child->setAttribute('style', $safeStyle);
                        $keep = true;
                    }
                }

                if (!$keep) {
                    $child->removeAttribute($name);
                }
            }

            if (
                $tag === 'a'
                && $child->getAttribute('target') === '_blank'
            ) {
                $child->setAttribute('rel', 'noopener noreferrer');
            }

            $walk($child);
        }
    };

    $root = $dom->getElementsByTagName('div')->item(0);

    if (!$root) {
        return '';
    }

    $walk($root);

    $result = '';

    foreach ($root->childNodes as $child) {
        $result .= $dom->saveHTML($child);
    }

    return trim($result);
}

function notificacaoSanitizeStyle($style)
{
    $allowed = [];
    foreach (explode(';', (string)$style) as $rule) {
        [$property, $value] = array_pad(explode(':', $rule, 2), 2, '');
        $property = strtolower(trim($property));
        $value = trim($value);
        if (in_array($property, ['color', 'background-color'], true)
            && preg_match('/^(#[0-9a-f]{3,8}|rgb\([\d\s,%]+\)|rgba\([\d\s,.%]+\))$/i', $value)) {
            $allowed[] = $property . ': ' . $value;
        }
    }
    return implode('; ', $allowed);
}

function notificacaoAnexosTableExists($conn)
{
    static $exists = null;
    if ($exists !== null) return $exists;
    $result = $conn->query("SHOW TABLES LIKE 'notificacoes_anexos'");
    $exists = $result && $result->num_rows > 0;
    return $exists;
}

function notificacaoNormalizeUploads($field)
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return [];
    $source = $_FILES[$field];
    $names = is_array($source['name'] ?? null) ? $source['name'] : [$source['name'] ?? ''];
    $files = [];
    foreach ($names as $index => $name) {
        $error = is_array($source['error'] ?? null) ? ($source['error'][$index] ?? UPLOAD_ERR_NO_FILE) : ($source['error'] ?? UPLOAD_ERR_NO_FILE);
        if ((int)$error === UPLOAD_ERR_NO_FILE) continue;
        $files[] = [
            'name' => (string)$name,
            'tmp_name' => is_array($source['tmp_name'] ?? null) ? ($source['tmp_name'][$index] ?? '') : ($source['tmp_name'] ?? ''),
            'error' => (int)$error,
            'size' => (int)(is_array($source['size'] ?? null) ? ($source['size'][$index] ?? 0) : ($source['size'] ?? 0)),
        ];
    }
    return $files;
}

function notificacaoSaveUploadedFiles($field = 'arquivos')
{
    $files = notificacaoNormalizeUploads($field);
    if (!$files) return [];
    if (count($files) > NOTIFICACAO_MAX_ARQUIVOS) throw new RuntimeException('Envie no máximo ' . NOTIFICACAO_MAX_ARQUIVOS . ' arquivos.');

    $total = array_sum(array_column($files, 'size'));
    if ($total > NOTIFICACAO_MAX_TAMANHO_TOTAL) throw new RuntimeException('O total dos arquivos excede 40 MB.');

    $mimeMap = [
        'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png',
        'image/gif' => 'gif', 'image/webp' => 'webp', 'image/bmp' => 'bmp'
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $uploadDir = __DIR__ . '/../uploads/notificacao';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Não foi possível preparar o diretório de anexos.');
    }

    $saved = [];
    try {
        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no upload de "' . basename($file['name']) . '".');
            if ($file['size'] < 1 || $file['size'] > NOTIFICACAO_MAX_TAMANHO_ARQUIVO) throw new RuntimeException('O arquivo "' . basename($file['name']) . '" deve ter até 10 MB.');
            if (!is_uploaded_file($file['tmp_name'])) throw new RuntimeException('Upload inválido para "' . basename($file['name']) . '".');
            $mime = $finfo->file($file['tmp_name']);
            if (!isset($mimeMap[$mime])) throw new RuntimeException('Tipo de arquivo não permitido: "' . basename($file['name']) . '".');
            $safeName = 'noti_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $mimeMap[$mime];
            $destination = $uploadDir . '/' . $safeName;
            if (!move_uploaded_file($file['tmp_name'], $destination)) throw new RuntimeException('Não foi possível salvar "' . basename($file['name']) . '".');
            $saved[] = ['nome_original' => basename($file['name']), 'nome_arquivo' => $safeName, 'caminho' => 'uploads/notificacao/' . $safeName, 'mime_type' => $mime, 'tamanho' => $file['size']];
        }
    } catch (Throwable $e) {
        notificacaoRemoveFiles($saved);
        throw $e;
    }
    return $saved;
}

function notificacaoRemoveFiles($files)
{
    foreach ($files as $file) {
        $path = is_array($file) ? ($file['caminho'] ?? '') : $file;
        $real = realpath(__DIR__ . '/../' . ltrim((string)$path, '/'));
        $base = realpath(__DIR__ . '/../uploads/notificacao');
        if ($real && $base && str_starts_with($real, $base)) @unlink($real);
    }
}

function notificacaoInsertAttachments($conn, $notificacaoId, $attachments)
{
    if (!$attachments) return;
    if (!notificacaoAnexosTableExists($conn)) throw new RuntimeException('A migration de anexos de notificações ainda não foi aplicada.');
    $stmt = $conn->prepare('INSERT INTO notificacoes_anexos (notificacao_id, nome_original, nome_arquivo, caminho, mime_type, tamanho, ordem) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) throw new RuntimeException('Não foi possível preparar os anexos da notificação.');
    foreach ($attachments as $ordem => $attachment) {
        $stmt->bind_param('issssii', $notificacaoId, $attachment['nome_original'], $attachment['nome_arquivo'], $attachment['caminho'], $attachment['mime_type'], $attachment['tamanho'], $ordem);
        if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Não foi possível salvar os anexos da notificação.'); }
    }
    $stmt->close();
}

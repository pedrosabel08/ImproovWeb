<?php
require_once __DIR__ . '/config/session_bootstrap.php';
header('Content-Type: application/json');

// CORS: allow requests from the browser origin and handle preflight.
// Uses the request Origin when present to allow credentials (cookies).
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
    header('Access-Control-Max-Age: 86400');
} else {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexao.php';

function log_notificacao_modulo($message, $context = [])
{
    try {
        $baseDir = __DIR__ . '/logs';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $line .= PHP_EOL;
        @file_put_contents($baseDir . '/notificacao_modulo_status.log', $line, FILE_APPEND);
    } catch (Throwable $e) {
        // no-op
    }
}

function parse_env_file($filePath)
{
    if (!is_readable($filePath)) return [];
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return [];
    $data = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "'\"");
        if ($key !== '') {
            $data[$key] = $value;
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
            if (!isset($_ENV[$key])) $_ENV[$key] = $value;
            if (!isset($_SERVER[$key])) $_SERVER[$key] = $value;
        }
    }
    return $data;
}

function normalize_name($s)
{
    if (!$s) return '';
    $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9\s]/', '', $s);
    $s = preg_replace('/\s+/', ' ', trim($s));
    return $s;
}

function slack_fetch_users($token)
{
    if (!$token || !function_exists('curl_init')) return [];
    $ch = curl_init('https://slack.com/api/users.list');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['ok']) || empty($data['members'])) return [];
    return $data['members'];
}

function slack_resolve_user_id($token, $nameOrId, $usersCache = null)
{
    if (!$nameOrId) return null;
    if (preg_match('/^[UW][A-Z0-9]+$/', $nameOrId)) {
        return $nameOrId;
    }
    $users = is_array($usersCache) ? $usersCache : slack_fetch_users($token);
    $target = normalize_name($nameOrId);
    if (!$target) return null;
    foreach ($users as $member) {
        $candidates = [];
        if (isset($member['real_name'])) $candidates[] = $member['real_name'];
        if (isset($member['profile']['real_name_normalized'])) $candidates[] = $member['profile']['real_name_normalized'];
        if (isset($member['profile']['display_name'])) $candidates[] = $member['profile']['display_name'];
        if (isset($member['profile']['display_name_normalized'])) $candidates[] = $member['profile']['display_name_normalized'];
        if (isset($member['profile']['email'])) $candidates[] = $member['profile']['email'];
        foreach ($candidates as $c) {
            if (normalize_name($c) === $target) {
                return $member['id'] ?? null;
            }
        }
    }
    return null;
}

function slack_send_dm($token, $userId, $text)
{
    if (!$token || !$userId || !function_exists('curl_init')) return false;
    $payload = [
        'channel' => $userId,
        'text' => $text,
    ];
    $ch = curl_init('https://slack.com/api/chat.postMessage');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return is_array($data) && !empty($data['ok']);
}

$debugLogs = [];
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    log_notificacao_modulo('auth_failed', [
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? null,
        'has_session' => session_id() ? 'yes' : 'no'
    ]);
    echo json_encode(['ok' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$idusuario = (int)($_SESSION['idusuario'] ?? 0);
$id = (int)($_POST['id'] ?? 0);
$action = (string)($_POST['action'] ?? 'visto');

if ($id <= 0 || $idusuario <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Parâmetros inválidos']);
    exit;
}

if ($action === 'confirmado') {
    $sql = "UPDATE notificacoes_destinatarios
            SET confirmado_em = NOW(), visto_em = COALESCE(visto_em, NOW())
            WHERE notificacao_id = ? AND usuario_id = ?";
} else {
    $sql = "UPDATE notificacoes_destinatarios
            SET visto_em = COALESCE(visto_em, NOW())
            WHERE notificacao_id = ? AND usuario_id = ?";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['ok' => false, 'message' => 'Erro ao preparar SQL']);
    exit;
}

$stmt->bind_param('ii', $id, $idusuario);
$ok = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if (!$ok) {
    echo json_encode(['ok' => false, 'message' => 'Falha ao atualizar']);
    exit;
}

// Notificar Slack somente quando houve atualização (evita duplicidade)
if ($affected > 0) {
    $slackToken = getenv('SLACK_TOKEN') ?: null;
    if (!$slackToken) {
        $envPath = __DIR__ . '/.env';
        $parsed = parse_env_file($envPath);
        $debugLogs[] = 'env_loaded=' . (!empty($parsed) ? 'yes' : 'no');
        $slackToken = getenv('SLACK_TOKEN') ?: null;
    }

    $debugLogs[] = 'slack_token=' . ($slackToken ? 'present' : 'missing');

    if ($slackToken) {
        // Nome do usuário que confirmou/viu
        $nomeUsuario = $_SESSION['nome_usuario'] ?? null;
        if (!$nomeUsuario) {
            $stmtUser = $conn->prepare("SELECT nome_usuario FROM usuario WHERE idusuario = ? LIMIT 1");
            if ($stmtUser) {
                $stmtUser->bind_param('i', $idusuario);
                $stmtUser->execute();
                $stmtUser->bind_result($nomeUsuarioDb);
                if ($stmtUser->fetch()) {
                    $nomeUsuario = $nomeUsuarioDb;
                }
                $stmtUser->close();
            }
        }
        if (!$nomeUsuario) {
            $nomeUsuario = 'Usuário';
        }

        // Título da notificação
        $tituloNotificacao = null;
        $stmtTit = $conn->prepare("SELECT titulo FROM notificacoes WHERE id = ? LIMIT 1");
        if ($stmtTit) {
            $stmtTit->bind_param('i', $id);
            $stmtTit->execute();
            $stmtTit->bind_result($tituloDb);
            if ($stmtTit->fetch()) {
                $tituloNotificacao = $tituloDb;
            }
            $stmtTit->close();
        }
        if (!$tituloNotificacao) {
            $tituloNotificacao = 'Notificação';
        }

        $acaoTexto = ($action === 'confirmado') ? 'confirmou' : 'viu';
        $mensagem = "{$nomeUsuario} {$acaoTexto} a notificação: {$tituloNotificacao}.";
        $debugLogs[] = 'mensagem=' . $mensagem;

        // Destinatários fixos: você e o André
        $targets = ['Pedro Sabel', 'Andre L. de Souza'];
        $targetsNorm = array_map('normalize_name', $targets);
        $slackNameMap = [];

        $placeholders = implode(',', array_fill(0, count($targets), '?'));
        $sqlUsers = "SELECT nome_usuario, nome_slack FROM usuario WHERE nome_usuario IN ($placeholders)";
        $stmtTargets = $conn->prepare($sqlUsers);
        if ($stmtTargets) {
            $types = str_repeat('s', count($targets));
            $stmtTargets->bind_param($types, ...$targets);
            $stmtTargets->execute();
            $res = $stmtTargets->get_result();
            while ($row = $res->fetch_assoc()) {
                $key = normalize_name($row['nome_usuario'] ?? '');
                if ($key) {
                    $slackNameMap[$key] = $row['nome_slack'] ?: $row['nome_usuario'];
                }
            }
            $stmtTargets->close();
        }

        $usersCache = null;
        foreach ($targetsNorm as $idx => $norm) {
            $targetLabel = $targets[$idx];
            $candidate = $slackNameMap[$norm] ?? $targetLabel;
            $userId = slack_resolve_user_id($slackToken, $candidate, $usersCache);
            if (!$userId && $usersCache === null) {
                $usersCache = slack_fetch_users($slackToken);
                $userId = slack_resolve_user_id($slackToken, $candidate, $usersCache);
            }
            $debugLogs[] = 'target=' . $targetLabel . ' candidate=' . $candidate . ' resolvedId=' . ($userId ?? 'null');
            if ($userId) {
                $sent = slack_send_dm($slackToken, $userId, $mensagem);
                $debugLogs[] = 'sent_to=' . $userId . ' ok=' . ($sent ? '1' : '0');
                if (!$sent) {
                    log_notificacao_modulo('slack_send_failed', [
                        'userId' => $userId,
                        'action' => $action
                    ]);
                }
            } else {
                log_notificacao_modulo('slack_user_not_found', [
                    'candidate' => $candidate,
                    'action' => $action
                ]);
            }
        }
    } else {
        log_notificacao_modulo('slack_token_missing', [
            'action' => $action
        ]);
    }
}

if (!empty($debugLogs)) {
    echo json_encode(['ok' => true, 'debug' => $debugLogs]);
} else {
    echo json_encode(['ok' => true]);
}

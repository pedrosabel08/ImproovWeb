<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/secure_env.php';

use phpseclib3\Net\SFTP;

include '../conexao.php';
// Start session to capture colaborador_id for auditing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone: keep dates consistent with the rest of the app (default: São Paulo).
// This prevents server-default timezone drift (e.g., UTC+1) from shifting stored times.
$APP_TIMEZONE = getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo';
@date_default_timezone_set($APP_TIMEZONE);
$APP_TZ = null;
try {
    $APP_TZ = new DateTimeZone($APP_TIMEZONE);
} catch (Exception $e) {
    // fallback to São Paulo if an invalid timezone string is configured
    $APP_TIMEZONE = 'America/Sao_Paulo';
    @date_default_timezone_set($APP_TIMEZONE);
    $APP_TZ = new DateTimeZone($APP_TIMEZONE);
}

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: https://improov.com.br");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// --- Small dotenv loader (no external dependency) ---
function load_dotenv($path)
{
    if (!file_exists($path))
        return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#')
            continue;
        if (strpos($line, '=') === false)
            continue;
        list($k, $v) = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        // set into environment
        putenv("$k=$v");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }
}

// --- Slack helpers ---
function send_slack_webhook($webhookUrl, $text, &$log)
{
    if (!$webhookUrl) {
        $log[] = "Slack webhook not configured";
        return false;
    }
    $payload = json_encode(['text' => $text]);
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $code >= 400) {
        $log[] = "Slack webhook failed (code=$code): $err / resp=" . substr($res ?: '', 0, 200);
        return false;
    }
    $log[] = "Slack webhook sent (code=$code)";
    return true;
}

function send_slack_token_message($token, $channel, $text, &$log)
{
    if (!$token || !$channel) {
        $log[] = "Slack token or channel missing";
        return false;
    }
    // If $channel is not an ID (starts with U/C/D), try to resolve it using users.list
    if (!preg_match('/^[UCD][A-Z0-9]+$/', $channel)) {
        $resolved = resolve_slack_user_id($token, $channel, $log);
        if ($resolved) {
            $log[] = "Resolved Slack identifier '$channel' => user id $resolved";
            $channel = $resolved;
        } else {
            $log[] = "Could not resolve Slack identifier: $channel";
            // continue and let chat.postMessage return channel_not_found if still invalid
        }
    }

    $url = 'https://slack.com/api/chat.postMessage';
    $body = json_encode(['channel' => $channel, 'text' => $text]);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        $log[] = "Slack API (token) request failed: $err";
        return false;
    }
    $json = json_decode($res, true);
    if (!$json || empty($json['ok'])) {
        $log[] = "Slack API error: " . ($json['error'] ?? substr($res, 0, 200));
        return false;
    }
    $log[] = "Slack message sent via token to $channel";
    return true;
}

function safe_lower_text($text)
{
    $value = trim((string) $text);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

/**
 * Resolve a human-readable Slack identifier (username, display name or email) to a Slack user ID using users.list.
 * Returns the user ID (e.g. U1234...) or null if not found.
 */
function resolve_slack_user_id($token, $identifier, &$log)
{
    if (!$token || !$identifier)
        return null;
    $ch = curl_init('https://slack.com/api/users.list');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        $log[] = "Slack users.list request failed: $err";
        return null;
    }
    $json = json_decode($res, true);
    if (!$json || empty($json['ok']) || empty($json['members'])) {
        $log[] = "Slack users.list error: " . ($json['error'] ?? 'no members');
        return null;
    }

    $needle = safe_lower_text($identifier);
    foreach ($json['members'] as $m) {
        // skip bots and deleted
        if (!empty($m['deleted']) || !empty($m['is_bot']))
            continue;
        $candidates = [];
        if (!empty($m['name']))
            $candidates[] = $m['name'];
        if (!empty($m['profile']['display_name']))
            $candidates[] = $m['profile']['display_name'];
        if (!empty($m['profile']['real_name']))
            $candidates[] = $m['profile']['real_name'];
        if (!empty($m['profile']['email']))
            $candidates[] = $m['profile']['email'];
        foreach ($candidates as $cand) {
            if (safe_lower_text($cand) === $needle) {
                return $m['id'];
            }
        }
    }
    return null;
}

// Load .env (if present) from project root
load_dotenv(__DIR__ . '/../.env');

// Slack config from env
$SLACK_WEBHOOK_URL = getenv('SLACK_WEBHOOK_URL') ?: null;
$FLOW_TOKEN = getenv('FLOW_TOKEN') ?: null;

try {
    $sftpCfg = improov_sftp_config();
    $ftpCfg = improov_ftp_config();
} catch (RuntimeException $e) {
    http_response_code(500);
    $envProbe = [
        'IMPROOV_SFTP_HOST' => getenv('IMPROOV_SFTP_HOST'),
        'IMPROOV_SFTP_PORT' => getenv('IMPROOV_SFTP_PORT'),
        'IMPROOV_SFTP_USER' => getenv('IMPROOV_SFTP_USER'),
        'IMPROOV_SFTP_PASS' => getenv('IMPROOV_SFTP_PASS') ? '***SET***' : null,
        'NAS_IP' => getenv('NAS_IP'),
        'NAS_HOST' => getenv('NAS_HOST'),
    ];
    echo json_encode(['errors' => ['Configuração de credenciais ausente no ambiente'], 'env' => $envProbe]);
    exit;
}

$host = $sftpCfg['host'];
$port = $sftpCfg['port'];
$username = $sftpCfg['user'];
$password = $sftpCfg['pass'];

// ---------- Dados FTP ----------
$ftp_host = $ftpCfg['host'];
$ftp_port = $ftpCfg['port'];
$ftp_user = $ftpCfg['user'];
$ftp_pass = $ftpCfg['pass'];
$ftp_base = $ftpCfg['base'];
// Variáveis do log
$log = [];
$success = [];
$errors = [];

// Agrupamento para acompanhamento inteligente: chave = "categoria|tipo_arquivo"
$acompGroups = [];

// Atualizações de status do briefing (pendente/validado -> recebido) por (tipo_imagem|categoria_id|tipo_arquivo)
$briefingUpdates = [];

// Colaborador (auditoria) — use NULL when not available to avoid FK constraint failures
$colaborador_id_sess = (isset($_SESSION['idcolaborador']) && intval($_SESSION['idcolaborador']) > 0) ? intval($_SESSION['idcolaborador']) : null;

// Usuário (auditoria do briefing) — pode ser NULL
$usuario_id_sess = (isset($_SESSION['idusuario']) && intval($_SESSION['idusuario']) > 0) ? intval($_SESSION['idusuario']) : null;

// Dados do formulário
$obra_id = intval($_POST['obra_id']);
$tipo_arquivo = $_POST['tipo_arquivo'] ?? "outros";
$descricao = $_POST['descricao'] ?? "";
$flag_master = !empty($_POST['flag_master']) ? 1 : 0;
$substituicao = !empty($_POST['flag_substituicao']);
$tiposImagem = $_POST['tipo_imagem'] ?? [];
$categoria = intval($_POST['tipo_categoria'] ?? 0);
$refsSkpModo = $_POST['refsSkpModo'] ?? 'geral';
$descricao = $_POST['descricao'] ?? "";
$sufixo = $_POST['sufixo'] ?? "";

// Data de recebimento fornecida pelo usuário (pode ser YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS).
// Gera um valor completo em formato DATETIME (Y-m-d H:i:s) usado em todas as inserções.
$data_recebido_raw = $_POST['data_recebido'] ?? null;
$data_recebido_datetime = null;
if ($data_recebido_raw) {
    // tenta vários formatos: full datetime, ISO T, ou apenas date
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $data_recebido_raw, $APP_TZ);
    if ($d === false)
        $d = DateTime::createFromFormat('Y-m-d\TH:i:s', $data_recebido_raw, $APP_TZ);
    if ($d === false)
        $d = DateTime::createFromFormat('Y-m-d', $data_recebido_raw, $APP_TZ);
    if ($d !== false) {
        // se o usuário enviou apenas a data (YYYY-MM-DD), ajusta a hora para o horário atual
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($data_recebido_raw))) {
            $now = new DateTime('now', $APP_TZ);
            $d->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
        }
        $data_recebido_datetime = $d->format('Y-m-d H:i:s');
    }
}
if (!$data_recebido_datetime) {
    $data_recebido_datetime = (new DateTime('now', $APP_TZ))->format('Y-m-d H:i:s');
}
$log[] = "Data recebimento selecionada: $data_recebido_datetime";
$log[] = "Timezone efetivo: $APP_TIMEZONE";

$log[] = "Recebido: obra_id=$obra_id, tipo_arquivo=$tipo_arquivo, substituicao=" . ($substituicao ? 'SIM' : 'NAO');
$log[] = "Tipos imagem: " . json_encode($tiposImagem);
$log[] = "Sufixo: " . ($sufixo ?: '(vazio)');

// Arquivos principais
$arquivosTmp = $_FILES['arquivos']['tmp_name'] ?? [];
$arquivosName = $_FILES['arquivos']['name'] ?? [];

// Arquivos por imagem (refs/skp)
$arquivosPorImagem = $_FILES['arquivos_por_imagem'] ?? [];
// Observações por imagem (nome: observacoes_por_imagem[<id>])
$observacoesPorImagem = $_POST['observacoes_por_imagem'] ?? [];

$sftp = new SFTP($host, $port);
if (!$sftp->login($username, $password)) {
    echo json_encode(['errors' => ["Erro ao conectar no servidor SFTP."], 'log' => $log]);
    exit;
}
$log[] = "Conectado no servidor SFTP.";

// Helper: tenta enviar via SFTP e registra erros detalhados; faz fallback para enviar o conteúdo do arquivo
function sftpPutWithFallback($sftp, $remotePath, $localFile, &$log, &$errors)
{
    // tentativa 1: enviar como arquivo local
    try {
        if ($sftp->put($remotePath, $localFile, SFTP::SOURCE_LOCAL_FILE)) {
            // aplicar permissões 0777 no arquivo enviado, se possível
            if (method_exists($sftp, 'chmod')) {
                try {
                    $sftp->chmod(0777, $remotePath);
                    $log[] = "Permissões 0777 aplicadas em $remotePath";
                } catch (Exception $e) {
                    $log[] = "Falha chmod após put(local): " . $e->getMessage();
                }
            }
            return true;
        }
    } catch (Exception $e) {
        $log[] = "Exception during sftp->put(local): " . $e->getMessage();
    }

    // registrar info diagnóstica
    if (method_exists($sftp, 'getSFTPErrors')) {
        $errs = $sftp->getSFTPErrors();
        $log[] = "SFTP errors: " . json_encode($errs);
    } elseif (method_exists($sftp, 'getLastSFTPError')) {
        $log[] = "SFTP last error: " . $sftp->getLastSFTPError();
    } elseif (method_exists($sftp, 'getErrors')) {
        $log[] = "SFTP errors (generic): " . json_encode($sftp->getErrors());
    } else {
        $log[] = "SFTP put failed but no error retrieval method available on phpseclib instance.";
    }

    // tentativa 2: enviar o conteúdo do arquivo (fallback)
    if (file_exists($localFile) && is_readable($localFile)) {
        $data = @file_get_contents($localFile);
        if ($data !== false) {
            try {
                if ($sftp->put($remotePath, $data)) {
                    $log[] = "Fallback: enviado via conteúdo para $remotePath";
                    // aplicar permissões 0777 no arquivo enviado, se possível
                    if (method_exists($sftp, 'chmod')) {
                        try {
                            $sftp->chmod(0777, $remotePath);
                            $log[] = "Permissões 0777 aplicadas em $remotePath";
                        } catch (Exception $e) {
                            $log[] = "Falha chmod após put(content): " . $e->getMessage();
                        }
                    }
                    return true;
                } else {
                    $log[] = "Fallback put retornou false para $remotePath";
                }
            } catch (Exception $e) {
                $log[] = "Exception during sftp->put(content): " . $e->getMessage();
            }
        } else {
            $log[] = "Falha ao ler o arquivo local para fallback: $localFile";
        }
    } else {
        $log[] = "Arquivo local não existe ou não legível para fallback: $localFile";
    }

    $errors[] = "Falha ao enviar: $remotePath";
    return false;
}

// Funções auxiliares
function buscarTipoImagemId($conn, $nomeTipo, &$log)
{
    $nomeTipo = $conn->real_escape_string($nomeTipo);
    $res = $conn->query("SELECT id_tipo_imagem FROM tipo_imagem WHERE nome='$nomeTipo'");
    $log[] = "Query buscarTipoImagemId: $nomeTipo (" . ($res && $res->num_rows > 0 ? "ENCONTRADO" : "NÃO ENCONTRADO") . ")";
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return $row['id_tipo_imagem'];
    }
    return null;
}

function buscarNomeCategoria($categoriaId)
{
    $categorias = [
        1 => 'Arquitetonico',
        2 => 'Referencias',
        3 => 'Paisagismo',
        4 => 'Luminotecnico',
        5 => 'Estrutural',
        6 => 'Alteracoes',
        7 => 'Angulo definido'
    ];
    return $categorias[$categoriaId] ?? 'Outros';
}

// Mapeia id de categoria (Arquivos) para label usada no briefing (com acentos)
function mapCategoriaBriefing($categoriaId)
{
    $map = [
        1 => 'Arquitetônico',
        2 => 'Referências',
        3 => 'Paisagismo',
        4 => 'Luminotécnico',
        5 => 'Estrutural',
    ];
    return $map[intval($categoriaId)] ?? null;
}

function normTipoArquivoBriefing($tipo)
{
    $s = trim((string) $tipo);
    if ($s === '')
        return null;
    if (strtoupper($s) === 'OUTROS' || strtolower($s) === 'outros')
        return 'Outros';
    return strtoupper($s);
}

function markBriefingRecebido($conn, $obraId, $tipoImagem, $categoriaId, $tipoArquivo, &$log)
{
    $cat = mapCategoriaBriefing($categoriaId);
    $ta = normTipoArquivoBriefing($tipoArquivo);
    if (!$cat || !$ta)
        return;

    // resolve tipo_id
    if ($stmtTipo = $conn->prepare('SELECT id FROM briefing_tipo_imagem WHERE obra_id = ? AND tipo_imagem = ? LIMIT 1')) {
        $stmtTipo->bind_param('is', $obraId, $tipoImagem);
        $stmtTipo->execute();
        $res = $stmtTipo->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmtTipo->close();

        $tipoId = $row ? intval($row['id']) : 0;
        if (!$tipoId)
            return;

        // obtém requisito atual
        if (
            $stmtSel = $conn->prepare(
                "SELECT id, status FROM briefing_requisitos_arquivo
             WHERE briefing_tipo_imagem_id = ?
               AND categoria = ?
               AND tipo_arquivo = ?
               AND origem = 'cliente'
               AND tipo_arquivo <> 'INTERNAL'
             LIMIT 1"
            )
        ) {
            $stmtSel->bind_param('iss', $tipoId, $cat, $ta);
            $stmtSel->execute();
            $r = $stmtSel->get_result();
            $rowReq = $r ? $r->fetch_assoc() : null;
            $stmtSel->close();

            if (!$rowReq)
                return;
            $reqId = intval($rowReq['id']);
            $from = strtolower((string) ($rowReq['status'] ?? ''));
            if (!in_array($from, ['pendente', 'validado'], true))
                return;

            // pendente/validado -> recebido
            if (
                $stmtUp = $conn->prepare(
                    "UPDATE briefing_requisitos_arquivo
                 SET status = 'recebido', updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND status IN ('pendente','validado')"
                )
            ) {
                $stmtUp->bind_param('i', $reqId);
                $stmtUp->execute();
                $aff = $stmtUp->affected_rows;
                $stmtUp->close();
                if ($aff > 0) {
                    $log[] = "Briefing requisito marcado como recebido: obra=$obraId tipo='$tipoImagem' cat='$cat' arquivo='$ta'";

                    // log (arquivo_id/usuario_id resolvidos fora)
                    if (
                        $stmtLog = $conn->prepare(
                            "INSERT INTO briefing_requisitos_arquivo_log
                            (requisito_id, obra_id, tipo_imagem, categoria, tipo_arquivo, from_status, to_status, action, arquivo_id, usuario_id)
                         VALUES (?, ?, ?, ?, ?, ?, 'recebido', 'upload', ?, ?)"
                        )
                    ) {
                        $arquivoId = isset($GLOBALS['__briefing_last_arquivo_id']) ? intval($GLOBALS['__briefing_last_arquivo_id']) : null;
                        $usuarioId = isset($GLOBALS['__briefing_usuario_id']) ? intval($GLOBALS['__briefing_usuario_id']) : null;
                        $fromStatus = $from;
                        // bind: i i s s s s i i
                        $stmtLog->bind_param('iissssii', $reqId, $obraId, $tipoImagem, $cat, $ta, $fromStatus, $arquivoId, $usuarioId);
                        $stmtLog->execute();
                        $stmtLog->close();
                    }
                }
            }
        }
    }
}

// Normaliza um nome para uso em pasta: remove acentos, caracteres perigosos e substitui espaços por '_'
function sanitizeDirName($str)
{
    $map = [
        '/[áàãâä]/ui' => 'a',
        '/[éèêë]/ui' => 'e',
        '/[íìîï]/ui' => 'i',
        '/[óòõôö]/ui' => 'o',
        '/[úùûü]/ui' => 'u',
        '/[ç]/ui' => 'c'
    ];
    $str = preg_replace(array_keys($map), array_values($map), $str);
    // remove caracteres não alfanuméricos exceto espaço e underscore
    $str = preg_replace('/[^A-Za-z0-9 _-]/', '', $str);
    $str = preg_replace('/\s+/', '_', trim($str));
    return $str;
}

// Tenta resolver a pasta de categoria existente no SFTP; se não encontrar, cria uma pasta sanitizada
function resolveCategoriaDir($sftp, $pastaBase, $categoriaNome, &$log)
{
    $candidates = [];
    $candidates[] = $categoriaNome;
    $candidates[] = str_replace(' ', '', $categoriaNome);
    $candidates[] = str_replace(' ', '_', $categoriaNome);
    $candidates[] = sanitizeDirName($categoriaNome);

    foreach ($candidates as $cand) {
        $path = rtrim($pastaBase, '/') . '/' . $cand;
        if ($sftp->is_dir($path)) {
            $log[] = "Categoria encontrada: $path (usando candidato '$cand')";
            return $cand;
        }
    }

    // Não encontrou — cria com versão sanitizada
    $safe = sanitizeDirName($categoriaNome);
    $pathSafe = rtrim($pastaBase, '/') . '/' . $safe;
    if (!$sftp->is_dir($pathSafe)) {
        if ($sftp->mkdir($pathSafe, 0777, true)) {
            $log[] = "Criada pasta de categoria sanitizada: $pathSafe";
            // tentar ajustar permissões da pasta para 0777
            if (method_exists($sftp, 'chmod')) {
                try {
                    $sftp->chmod(0777, $pathSafe);
                    $log[] = "Permissões 0777 aplicadas em: $pathSafe";
                } catch (Exception $e) {
                    $log[] = "Falha ao aplicar chmod SFTP em $pathSafe: " . $e->getMessage();
                }
            }
        } else {
            $log[] = "Falha ao criar pasta de categoria: $pathSafe";
        }
    }
    return $safe;
}

// Aplica chmod 0777 via SFTP quando suportado (retorna true se aplicado)
function setSftpPermissions($sftp, $remotePath, &$log)
{
    if (!is_object($sftp))
        return false;
    if (!method_exists($sftp, 'chmod')) {
        $log[] = "SFTP chmod não disponível para $remotePath";
        return false;
    }
    try {
        $ok = $sftp->chmod(0777, $remotePath);
        $log[] = $ok ? "SFTP chmod 0777 aplicado: $remotePath" : "SFTP chmod retornou false: $remotePath";
        return (bool) $ok;
    } catch (Exception $e) {
        $log[] = "Exception ao aplicar chmod SFTP em $remotePath: " . $e->getMessage();
        return false;
    }
}

// Tenta aplicar CHMOD 0777 via FTP (usa ftp_chmod se disponível, senão ftp_site)
function ensureFtpPermissions($ftpConn, $path, &$log)
{
    if (!$ftpConn)
        return false;
    if (function_exists('ftp_chmod')) {
        $res = @ftp_chmod($ftpConn, 0777, $path);
        $log[] = $res !== false ? "FTP chmod 0777 aplicado: $path" : "FTP chmod falhou: $path";
        return $res !== false;
    }
    // fallback: usar SITE CHMOD
    $res = @ftp_site($ftpConn, "CHMOD 0777 $path");
    $log[] = $res !== false ? "FTP SITE CHMOD aplicado: $path" : "FTP SITE CHMOD falhou: $path";
    return $res !== false;
}

function buscarNomenclatura($conn, $obra_id)
{
    $stmt = $conn->prepare("SELECT nomenclatura FROM obra WHERE idobra = ?");
    $stmt->bind_param("i", $obra_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        return $row['nomenclatura'];
    }

    return null;
}

// Garante que um caminho exista no FTP (cria recursivamente). Retorna true/false e adiciona logs.
function ensureFtpDir($ftp, $path, &$log)
{
    // Remove barras duplicadas e mantém caminho absoluto
    $parts = array_filter(explode('/', $path), 'strlen');
    $cur = '/';
    // Salva diretório atual
    $orig = @ftp_pwd($ftp);
    foreach ($parts as $p) {
        $cur = rtrim($cur, '/') . '/' . $p;
        // tenta mudar para o diretório
        if (@ftp_chdir($ftp, $cur) === false) {
            // tenta criar
            if (@ftp_mkdir($ftp, $cur) === false) {
                $log[] = "Falha ao criar pasta FTP: $cur";
                // tenta voltar
                if ($orig)
                    @ftp_chdir($ftp, $orig);
                return false;
            } else {
                $log[] = "Criada pasta FTP: $cur";
            }
        }
    }
    // volta para o original, se possível
    if ($orig)
        @ftp_chdir($ftp, $orig);
    return true;
}

function buscarPastaBaseSFTP($sftp, $conn, $obra_id)
{
    // Bases de clientes (anos possíveis)
    $clientes_base = ['/mnt/clientes/2024', '/mnt/clientes/2025', '/mnt/clientes/2026'];

    // Busca a nomenclatura da obral
    $stmt = $conn->prepare("SELECT nomenclatura FROM obra WHERE idobra = ?");
    $stmt->bind_param("i", $obra_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || !$row = $result->fetch_assoc()) {
        return null;
    }

    $nomenclatura = $row['nomenclatura'];

    // Verifica em qual base a obra existe **no SFTP**
    foreach ($clientes_base as $base) {
        $pasta = $base . "/" . $nomenclatura . "/05.Exchange/01.Input/";
        if ($sftp->is_dir($pasta)) {
            return $pasta; // Retorna caminho válido no servidor
        }
    }

    return null; // Não encontrado
}



$pastaBase = buscarPastaBaseSFTP($sftp, $conn, $obra_id);
if (!$pastaBase) {
    $errors[] = "Pasta da obra não encontrada no servidor SFTP para Obra ID $obra_id.";
    echo json_encode(['success' => $success, 'errors' => $errors, 'log' => $log]);
    exit;
}

// calcula próxima ordem de acompanhamento uma única vez (será usada para inserts agregados)
$next_ordem_acomp = 1;
if ($obra_id) {
    if ($stmtOrdem = $conn->prepare("SELECT IFNULL(MAX(ordem),0)+1 AS next_ordem FROM acompanhamento_email WHERE obra_id = ?")) {
        $stmtOrdem->bind_param('i', $obra_id);
        $stmtOrdem->execute();
        $rOrd = $stmtOrdem->get_result()->fetch_assoc();
        if ($rOrd && isset($rOrd['next_ordem']))
            $next_ordem_acomp = intval($rOrd['next_ordem']);
        $stmtOrdem->close();
    }
}

// Variável de conexão FTP (inicializada apenas se necessária)
$ftp_conn = null;


function gerarNomeInterno($conn, $obra_id, $tipo_id, $categoria, $nomeTipo, $tipo_arquivo, $ext, &$log, $sufixo = '', $imagem_id = null, $fileOriginalName = null, $indiceEnvio = 1)
{
    // 🔹 Busca nomenclatura da obra
    $obraRes = $conn->query("SELECT nomenclatura FROM obra WHERE idobra = $obra_id");
    $nomenclatura = ($obraRes && $obraRes->num_rows > 0)
        ? $obraRes->fetch_assoc()['nomenclatura']
        : "OBRA{$obra_id}";

    // 🔹 Limpeza e redução dos nomes
    $nomeTipoLimpo = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nomeTipo), 0, 3));
    $categoriaNome = strtoupper(substr(buscarNomeCategoria($categoria), 0, 3));
    $tipoArquivoAbrev = strtoupper(substr($tipo_arquivo, 0, 3));

    // 🔹 Parte base do nome original (sem extensão)
    $fileOriginalBase = $fileOriginalName
        ? preg_replace('/[^A-Za-z0-9]/', '', pathinfo($fileOriginalName, PATHINFO_FILENAME))
        : '';

    // 🔹 Se for SKP ou IMG → buscar versão pelo campo versao
    if ($tipo_arquivo === 'SKP' || $tipo_arquivo === 'IMG') {
        // Primeiro tenta encontrar por nome_original (comportamento antigo)
        $sql = "SELECT versao FROM arquivos 
                WHERE obra_id = ? 
                  AND tipo_imagem_id = ? 
                  AND categoria_id = ? 
                  AND tipo = ? 
                  AND nome_original = ?";
        $params = [$obra_id, $tipo_id, $categoria, $tipo_arquivo, $fileOriginalName];
        $types = "iiiss";

        if ($imagem_id) {
            $sql .= " AND imagem_id = ?";
            $params[] = $imagem_id;
            $types .= "i";
        }

        $sql .= " ORDER BY versao DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $versao = 1;
        if ($row = $res->fetch_assoc()) {
            $versao = intval($row['versao']) + 1;
        } else {
            // Fallback: procurar última versão por obra/tipo_imagem/categoria/(imagem_id) independente do nome_original
            $sql2 = "SELECT versao FROM arquivos 
                     WHERE obra_id = ? 
                       AND tipo_imagem_id = ? 
                       AND categoria_id = ? 
                       AND tipo = ?";
            $params2 = [$obra_id, $tipo_id, $categoria, $tipo_arquivo];
            $types2 = "iiii";
            if ($imagem_id) {
                $sql2 .= " AND imagem_id = ?";
                $params2[] = $imagem_id;
                $types2 .= "i";
            }
            $sql2 .= " ORDER BY versao DESC LIMIT 1";
            $stmt2 = $conn->prepare($sql2);
            if ($stmt2) {
                $stmt2->bind_param($types2, ...$params2);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if ($row2 = $res2->fetch_assoc()) {
                    $versao = intval($row2['versao']) + 1;
                }
                $stmt2->close();
            }
        }
    }
    // 🔹 Demais tipos → usar o campo `versao` baseado no MESMO critério do nome interno.
    // Importante: não usar nome_original aqui, porque o usuário pode subir o “mesmo” arquivo com outro nome local,
    // mas ainda assim queremos gerar v2/v3 e não sobrescrever o v1.
    else {
        $versao = 1;

        $sufixoDb = (string) $sufixo;

        // Versão incremental por contexto do nome interno: obra/tipo_imagem/categoria/tipo/sufixo/(imagem_id quando existir)
        if ($imagem_id !== null) {
            $sql = "SELECT MAX(versao) AS max_versao FROM arquivos
                    WHERE obra_id = ?
                      AND tipo_imagem_id = ?
                      AND categoria_id = ?
                      AND tipo = ?
                      AND sufixo = ?
                      AND imagem_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('iiissi', $obra_id, $tipo_id, $categoria, $tipo_arquivo, $sufixoDb, $imagem_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && ($row = $res->fetch_assoc()) && $row['max_versao'] !== null) {
                    $versao = intval($row['max_versao']) + 1;
                }
                $stmt->close();
            }
        } else {
            $sql = "SELECT MAX(versao) AS max_versao FROM arquivos
                    WHERE obra_id = ?
                      AND tipo_imagem_id = ?
                      AND categoria_id = ?
                      AND tipo = ?
                      AND sufixo = ?
                      AND imagem_id IS NULL";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('iiiss', $obra_id, $tipo_id, $categoria, $tipo_arquivo, $sufixoDb);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && ($row = $res->fetch_assoc()) && $row['max_versao'] !== null) {
                    $versao = intval($row['max_versao']) + 1;
                }
                $stmt->close();
            }
        }
    }

    // sanitize sufixo for filename
    $sufixoSafe = $sufixo ? preg_replace('/[^A-Za-z0-9_-]/', '', strtoupper(str_replace(' ', '_', $sufixo))) : '';

    // 🔹 Montagem final do nome interno (inclui sufixo quando presente)
    $envioStr = sprintf("env%02d", $indiceEnvio);
    if ($sufixoSafe) {
        $nomeInterno = "{$nomenclatura}-{$categoriaNome}-{$tipoArquivoAbrev}-{$sufixoSafe}-{$envioStr}-v{$versao}.{$ext}";
    } else {
        $nomeInterno = "{$nomenclatura}-{$categoriaNome}-{$tipoArquivoAbrev}-{$envioStr}-v{$versao}.{$ext}";
    }

    $log[] = "Gerado nome interno: $nomeInterno (versão $versao, envio $indiceEnvio)";

    return [$nomeInterno, $versao];
}


// =======================
// Upload principal
// =======================
if (!empty($arquivosTmp) && count($arquivosTmp) > 0 && ($refsSkpModo === 'geral' || $tipo_arquivo !== 'SKP')) {
    $indice = 1;
    $imagem_id = null; // upload principal não é por imagem específica

    // Pré-calcula próxima ordem para acompanhamento_email
    $next_ordem_acomp = 1;
    if ($obra_id) {
        if ($stmtOrdem = $conn->prepare("SELECT IFNULL(MAX(ordem),0)+1 AS next_ordem FROM acompanhamento_email WHERE obra_id = ?")) {
            $stmtOrdem->bind_param('i', $obra_id);
            $stmtOrdem->execute();
            $rOrd = $stmtOrdem->get_result()->fetch_assoc();
            if ($rOrd && isset($rOrd['next_ordem']))
                $next_ordem_acomp = intval($rOrd['next_ordem']);
            $stmtOrdem->close();
        }
    }

    foreach ($arquivosTmp as $index => $fileTmp) {
        $fileOriginalName = basename($arquivosName[$index]);
        $ext = pathinfo($fileOriginalName, PATHINFO_EXTENSION);
        $log[] = "Processando upload principal: $fileOriginalName";
        $tamanhoArquivo = (is_file($fileTmp) ? (string) filesize($fileTmp) : '0');

        foreach ($tiposImagem as $nomeTipo) {
            $tipo_id = buscarTipoImagemId($conn, $nomeTipo, $log);
            if (!$tipo_id) {
                $errors[] = "Tipo de imagem '$nomeTipo' não encontrado.";
                continue;
            }

            $categoriaNome = buscarNomeCategoria($categoria);
            // resolve candidate folder name no SFTP (tenta variações e cria uma pasta sanitizada caso necessário)
            $categoriaDir = resolveCategoriaDir($sftp, $pastaBase, $categoriaNome, $log);

            $destDir = rtrim($pastaBase, '/') . '/' . $categoriaDir . '/' . $nomeTipo . '/' . $tipo_arquivo;
            if (!$sftp->is_dir($destDir)) {
                $sftp->mkdir($destDir, 0777, true);
                $log[] = "Criado diretório: $destDir";
                // tentar definir permissões da pasta
                if (method_exists($sftp, 'chmod')) {
                    try {
                        $sftp->chmod(0777, $destDir);
                        $log[] = "Permissões 0777 aplicadas em $destDir";
                    } catch (Exception $e) {
                        $log[] = "Falha chmod dir $destDir: " . $e->getMessage();
                    }
                }
            }
            if (!$sftp->is_dir($destDir . "/OLD")) {
                $sftp->mkdir($destDir . "/OLD", 0777, true);
                $log[] = "Criado diretório: $destDir/OLD";
                if (method_exists($sftp, 'chmod')) {
                    try {
                        $sftp->chmod(0777, $destDir . "/OLD");
                        $log[] = "Permissões 0777 aplicadas em $destDir/OLD";
                    } catch (Exception $e) {
                        $log[] = "Falha chmod dir $destDir/OLD: " . $e->getMessage();
                    }
                }
            }

            list($fileNomeInterno, $versao) = gerarNomeInterno($conn, $obra_id, $tipo_id, $categoria, $nomeTipo, $tipo_arquivo, $ext, $log, $sufixo, null, $fileOriginalName, $indice);
            $destFile = $destDir . "/" . $fileNomeInterno;
            $log[] = "Destino final: $destFile";
            $indice++;

            // Substituição
            // Substituição: considerar a mesma “família” do nome interno (independente do nome_original)
            $check = $conn->prepare("SELECT * FROM arquivos 
        WHERE obra_id = ? 
            AND tipo_imagem_id = ? 
            AND categoria_id = ?
            AND tipo = ? 
            AND sufixo = ?
            AND imagem_id IS NULL
            AND status = 'atualizado'");
            $check->bind_param("iiiss", $obra_id, $tipo_id, $categoria, $tipo_arquivo, $sufixo);
            $check->execute();
            $result = $check->get_result();
            $log[] = "Encontrados {$result->num_rows} arquivos antigos para $fileOriginalName.";
            $foiAtualizacao = ($result->num_rows > 0 && $substituicao);
            if ($result->num_rows > 0 && $substituicao) {
                while ($old = $result->fetch_assoc()) {
                    $oldPath = $destDir . "/" . $old['nome_interno'];
                    $newPath = $destDir . "/OLD/" . $old['nome_interno'];
                    $log[] = "Movendo $oldPath => $newPath";
                    if (!$sftp->rename($oldPath, $newPath)) {
                        $errors[] = "Falha ao mover {$old['nome_interno']} para OLD.";
                    }
                    $conn->query("UPDATE arquivos SET status='antigo' WHERE idarquivo=" . $old['idarquivo']);
                }
            }

            if (!empty($fileTmp) && file_exists($fileTmp)) {
                if (sftpPutWithFallback($sftp, $destFile, $fileTmp, $log, $errors)) {
                    $stmt = $conn->prepare("INSERT INTO arquivos 
                    (obra_id, tipo_imagem_id, imagem_id, nome_original, nome_interno, caminho, tipo, versao, status, origem, recebido_por, recebido_em, categoria_id, sufixo, descricao, tamanho, colaborador_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'atualizado', 'upload_web', 'sistema', ?, ?, ?, ?, ?, ?)");
                    // types: obra_id(i), tipo_imagem_id(i), imagem_id(i), nome_original(s), nome_interno(s), caminho(s), tipo(s), versao(i), recebido_em(s), categoria(i), sufixo(s), descricao(s), tamanho(s), colaborador_id(i)
                    $stmt->bind_param("iiissssisisssi", $obra_id, $tipo_id, $imagem_id, $fileOriginalName, $fileNomeInterno, $destFile, $tipo_arquivo, $versao, $data_recebido_datetime, $categoria, $sufixo, $descricao, $tamanhoArquivo, $colaborador_id_sess);

                    $stmt->execute();
                    $arquivo_id = $conn->insert_id;
                    $success[] = "Arquivo '$fileOriginalName' enviado para $nomeTipo como '$fileNomeInterno'";
                    $log[] = "Arquivo enviado com sucesso: $destFile";

                    // Marca briefing como recebido (apenas se for categoria do briefing 1..5) + log
                    $GLOBALS['__briefing_last_arquivo_id'] = $arquivo_id;
                    $GLOBALS['__briefing_usuario_id'] = $usuario_id_sess ? intval($usuario_id_sess) : 0;
                    markBriefingRecebido($conn, $obra_id, $nomeTipo, $categoria, $tipo_arquivo, $log);

                    // Acumula dados para acompanhamento agregado (vamos inserir 1 registro por categoria|tipo_arquivo)
                    $key = $categoria . '|' . $tipo_arquivo;
                    if (!isset($acompGroups[$key])) {
                        $acompGroups[$key] = [
                            'categoria' => $categoria,
                            'tipo_arquivo' => $tipo_arquivo,
                            'targets' => [], // nomes de tipo (ex: Fachada, Planta ...)
                            'arquivo_ids' => [],
                            'is_update' => false,
                            'count' => 0,
                        ];
                    }
                    $acompGroups[$key]['targets'][] = $nomeTipo;
                    $acompGroups[$key]['arquivo_ids'][] = $arquivo_id;
                    $acompGroups[$key]['is_update'] = ($acompGroups[$key]['is_update'] || $foiAtualizacao);
                    $acompGroups[$key]['count']++;

                    // Se for categoria 7, também envia ao FTP secundário
                    if ($categoria == 7) {
                        if ($ftp_conn === null) {
                            $ftp_conn = @ftp_connect($ftp_host, $ftp_port, 10);
                            if ($ftp_conn && @ftp_login($ftp_conn, $ftp_user, $ftp_pass)) {
                                @ftp_pasv($ftp_conn, true);
                                $log[] = "Conectado no FTP secundário: $ftp_host";
                            } else {
                                $log[] = "Falha ao conectar no FTP secundário: $ftp_host";
                                $errors[] = "Falha ao conectar no FTP secundário.";
                                $ftp_conn = null;
                            }
                        }

                        if ($ftp_conn) {
                            $nomen = buscarNomenclatura($conn, $obra_id);
                            $ftpTargetDir = rtrim($ftp_base, '/') . '/' . ($nomen ? $nomen : 'OBRA' . $obra_id) . '/' . $categoriaDir . '/' . $nomeTipo . '/' . $tipo_arquivo;
                            if (ensureFtpDir($ftp_conn, $ftpTargetDir, $log)) {
                                $ftpDest = $ftpTargetDir . '/' . $fileNomeInterno;
                                if (@ftp_put($ftp_conn, $ftpDest, $fileTmp, FTP_BINARY)) {
                                    $log[] = "Arquivo enviado para FTP: $ftpDest";
                                    // tentar ajustar permissões no FTP
                                    ensureFtpPermissions($ftp_conn, $ftpDest, $log);
                                } else {
                                    $errors[] = "Erro ao enviar para FTP: $ftpDest";
                                    $log[] = "Falha FTP put: $ftpDest";
                                }
                            }
                        }
                    }
                } else {
                    $errors[] = "Erro ao enviar '$fileOriginalName' para $nomeTipo";
                    $log[] = "Falha ao enviar: $destFile";
                }
            }
        }
    }
}

// status do briefing já aplicado durante o loop de uploads

// =======================
// Upload por imagem (permitir para todos os tipos quando modo = 'porImagem')
// =======================
if (!empty($arquivosPorImagem) && $refsSkpModo === 'porImagem') {

    $log[] = "Iniciando upload por imagem (modo porImagem)...";
    foreach ($arquivosPorImagem['name'] as $imagem_id => $arquivosArray) {
        // Substituição apenas uma vez por imagem
        // Filtra por obra_id, tipo, imagem_id e categoria_id para evitar mover arquivos de outras categorias
        $stmtCheck = $conn->prepare("SELECT * FROM arquivos 
        WHERE obra_id = ? AND tipo = ? AND imagem_id = ? AND categoria_id = ? AND status = 'atualizado'");
        if ($stmtCheck) {
            $stmtCheck->bind_param("isii", $obra_id, $tipo_arquivo, $imagem_id, $categoria);
            $stmtCheck->execute();
            $check = $stmtCheck->get_result();
            $log[] = "Encontrados {$check->num_rows} arquivos refs/skp antigos para imagem $imagem_id (categoria $categoria).";
            $foiAtualizacaoImagem = ($check->num_rows > 0 && $substituicao);
            if ($check->num_rows > 0 && $substituicao) {
                while ($old = $check->fetch_assoc()) {
                    $oldPath = $old['caminho'];
                    $newPath = dirname($oldPath) . "/OLD/" . basename($oldPath);
                    $log[] = "Movendo $oldPath => $newPath";
                    if ($sftp->file_exists($oldPath)) {
                        if (!$sftp->rename($oldPath, $newPath)) {
                            $errors[] = "Falha ao mover {$old['nome_interno']} para OLD.";
                        }
                    } else {
                        $errors[] = "Arquivo antigo {$old['nome_interno']} não encontrado.";
                    }
                    $conn->query("UPDATE arquivos SET status='antigo' WHERE idarquivo=" . $old['idarquivo']);
                }
            }
            $stmtCheck->close();
        } else {
            $log[] = "Falha ao preparar consulta de verificação de arquivos antigos: " . $conn->error;
        }
        $indice = 1;

        // Agora envia todos os arquivos novos para essa imagem
        foreach ($arquivosArray as $index => $nomeOriginal) {
            $tmpFile = $arquivosPorImagem['tmp_name'][$imagem_id][$index];
            if (empty($tmpFile) || !file_exists($tmpFile)) {
                $log[] = "Arquivo vazio ou inexistente: $nomeOriginal";
                continue;
            }
            $ext = pathinfo($nomeOriginal, PATHINFO_EXTENSION);
            $tamanhoArquivo = (is_file($tmpFile) ? (string) filesize($tmpFile) : '0');

            $nomeTipo = $tiposImagem[0] ?? '';
            $tipo_id = buscarTipoImagemId($conn, $nomeTipo, $log);
            if (!$tipo_id) {
                $errors[] = "Tipo de imagem '$nomeTipo' não encontrado.";
                continue;
            }

            $queryImagem = $conn->query("SELECT imagem_nome FROM imagens_cliente_obra WHERE idimagens_cliente_obra=$imagem_id");
            if ($queryImagem->num_rows == 0) {
                $errors[] = "Imagem ID $imagem_id não encontrada.";
                $log[] = "Imagem ID $imagem_id não encontrada.";
                continue;
            }
            $nome_imagem = $queryImagem->fetch_assoc()['imagem_nome'];
            // Descrição para insert: usar SOMENTE a observação enviada para esta imagem quando existir
            // Caso contrário, usar o campo de descrição global como fallback
            $observacao_local = '';
            if (is_array($observacoesPorImagem) && isset($observacoesPorImagem[$imagem_id])) {
                $observacao_local = trim((string) $observacoesPorImagem[$imagem_id]);
            }
            if ($observacao_local !== '') {
                $descricao_para_insert = $observacao_local;
            } else {
                $descricao_para_insert = $descricao ?: '';
            }

            $categoriaNome = buscarNomeCategoria($categoria);
            $categoriaDir = resolveCategoriaDir($sftp, $pastaBase, $categoriaNome, $log);

            $destDir = rtrim($pastaBase, '/') . '/' . $categoriaDir . '/' . $nomeTipo . '/' . $tipo_arquivo . '/' . $nome_imagem;
            if (!$sftp->is_dir($destDir)) {
                $sftp->mkdir($destDir, 0777, true);
                $log[] = "Criado diretório: $destDir";
            }
            if (!$sftp->is_dir($destDir . "/OLD")) {
                $sftp->mkdir($destDir . "/OLD", 0777, true);
                $log[] = "Criado diretório: $destDir/OLD";
            }

            list($fileNomeInterno, $versao) = gerarNomeInterno($conn, $obra_id, $tipo_id, $categoria, $nomeTipo, $tipo_arquivo, $ext, $log, $sufixo, $imagem_id, $nomeOriginal, $indice);
            $destFile = "$destDir/$fileNomeInterno";
            $log[] = "Destino final: $destFile";

            $indice++;

            if (sftpPutWithFallback($sftp, $destFile, $tmpFile, $log, $errors)) {
                $stmt = $conn->prepare("INSERT INTO arquivos 
                (obra_id, tipo_imagem_id, imagem_id, nome_original, nome_interno, caminho, tipo, versao, status, origem, recebido_por, recebido_em, categoria_id, sufixo, descricao, tamanho, colaborador_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'atualizado', 'upload_web', 'sistema', ?, ?, ?, ?, ?, ?)");
                // types: obra_id(i), tipo_imagem_id(i), imagem_id(i), nome_original(s), nome_interno(s), caminho(s), tipo(s), versao(i), recebido_em(s), categoria(i), sufixo(s), descricao(s), tamanho(s), colaborador_id(i)
                $stmt->bind_param("iiissssisisssi", $obra_id, $tipo_id, $imagem_id, $nomeOriginal, $fileNomeInterno, $destFile, $tipo_arquivo, $versao, $data_recebido_datetime, $categoria, $sufixo, $descricao_para_insert, $tamanhoArquivo, $colaborador_id_sess);

                $stmt->execute();
                $arquivo_id = $conn->insert_id;
                $success[] = "Arquivo '$nomeOriginal' enviado para Imagem $imagem_id";
                $log[] = "Arquivo enviado com sucesso: $destFile";

                // Garantir que temos next_ordem_acomp calculado (feito apenas uma vez)
                if (!isset($next_ordem_acomp)) {
                    $next_ordem_acomp = 1;
                    if ($obra_id) {
                        if ($stmtOrdem2 = $conn->prepare("SELECT IFNULL(MAX(ordem),0)+1 AS next_ordem FROM acompanhamento_email WHERE obra_id = ?")) {
                            $stmtOrdem2->bind_param('i', $obra_id);
                            $stmtOrdem2->execute();
                            $rOrd2 = $stmtOrdem2->get_result()->fetch_assoc();
                            if ($rOrd2 && isset($rOrd2['next_ordem']))
                                $next_ordem_acomp = intval($rOrd2['next_ordem']);
                            $stmtOrdem2->close();
                        }
                    }
                }
                // Acumula dados para acompanhamento agregado (modo porImagem usa nome da imagem como target)
                $key = $categoria . '|' . $tipo_arquivo;
                if (!isset($acompGroups[$key])) {
                    $acompGroups[$key] = [
                        'categoria' => $categoria,
                        'tipo_arquivo' => $tipo_arquivo,
                        'targets' => [],
                        'arquivo_ids' => [],
                        'is_update' => false,
                        'count' => 0,
                    ];
                }
                $acompGroups[$key]['targets'][] = $nome_imagem;
                $acompGroups[$key]['arquivo_ids'][] = $arquivo_id;
                $acompGroups[$key]['is_update'] = ($acompGroups[$key]['is_update'] || $foiAtualizacaoImagem);
                $acompGroups[$key]['count']++;

                // Se for categoria 7, envia também ao FTP secundário
                if ($categoria == 7) {
                    if ($ftp_conn === null) {
                        $ftp_conn = @ftp_connect($ftp_host, $ftp_port, 10);
                        if ($ftp_conn && @ftp_login($ftp_conn, $ftp_user, $ftp_pass)) {
                            @ftp_pasv($ftp_conn, true);
                            $log[] = "Conectado no FTP secundário: $ftp_host";
                        } else {
                            $log[] = "Falha ao conectar no FTP secundário: $ftp_host";
                            $errors[] = "Falha ao conectar no FTP secundário.";
                            $ftp_conn = null;
                        }
                    }

                    if ($ftp_conn) {
                        $nomen = buscarNomenclatura($conn, $obra_id);
                        $ftpTargetDir = rtrim($ftp_base, '/') . '/' . ($nomen ? $nomen : 'OBRA' . $obra_id) . '/' . $categoriaDir . '/' . $nomeTipo . '/' . $tipo_arquivo . '/' . $nome_imagem;
                        if (ensureFtpDir($ftp_conn, $ftpTargetDir, $log)) {
                            $ftpDest = $ftpTargetDir . '/' . $fileNomeInterno;
                            if (@ftp_put($ftp_conn, $ftpDest, $tmpFile, FTP_BINARY)) {
                                $log[] = "Arquivo enviado para FTP: $ftpDest";
                                // tentar ajustar permissões no FTP
                                ensureFtpPermissions($ftp_conn, $ftpDest, $log);
                            } else {
                                $errors[] = "Erro ao enviar para FTP: $ftpDest";
                                $log[] = "Falha FTP put: $ftpDest";
                            }
                        }
                    }
                }
                // Notificar colaborador(es) associados à funcao_id = 4 para esta imagem
                try {
                    $notifMsg = "Ângulo definido para a imagem, confira abaixo:";
                    $notifMsg2 = "Ângulo definido para a imagem";
                    $sel = $conn->prepare("SELECT colaborador_id, idfuncao_imagem FROM funcao_imagem WHERE imagem_id = ? AND funcao_id = 4");
                    if ($sel) {
                        $sel->bind_param("i", $imagem_id);
                        $sel->execute();
                        $resSel = $sel->get_result();
                        if ($resSel && $resSel->num_rows > 0) {
                            // Use selected date instead of NOW()
                            $ins = $conn->prepare("insert into notificacoes_gerais (colaborador_id, mensagem, data, lida, funcao_imagem_id) VALUES (?, ?, ?, 0, ?)");
                            while ($rowNotif = $resSel->fetch_assoc()) {
                                $colabId = $rowNotif['colaborador_id'];
                                $funcaoImagemId = $rowNotif['idfuncao_imagem'];
                                if ($ins) {
                                    $ins->bind_param("issi", $colabId, $notifMsg, $data_recebido_datetime, $funcaoImagemId);
                                    $ins->execute();
                                    $log[] = "Notificação criada para colaborador $colabId";

                                    // Atualiza o status da funcao_imagem para 'Não iniciado' quando o ângulo é definido
                                    try {
                                        $novoStatus = 'Não iniciado';
                                        $statusAnterior = null;
                                        $stmtStatus = $conn->prepare("SELECT status FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1");
                                        if ($stmtStatus) {
                                            $stmtStatus->bind_param('i', $funcaoImagemId);
                                            $stmtStatus->execute();
                                            $resStatus = $stmtStatus->get_result();
                                            if ($resStatus && $rowS = $resStatus->fetch_assoc()) {
                                                $statusAnterior = $rowS['status'];
                                            }
                                            $stmtStatus->close();
                                        }

                                        // Só atualiza se for diferente
                                        if ($statusAnterior !== $novoStatus) {
                                            $upd = $conn->prepare("UPDATE funcao_imagem SET status = ? WHERE idfuncao_imagem = ?");
                                            if ($upd) {
                                                $upd->bind_param('si', $novoStatus, $funcaoImagemId);
                                                $upd->execute();
                                                $log[] = "Status da funcao_imagem $funcaoImagemId atualizado: '" . ($statusAnterior ?? 'NULL') . "' => '$novoStatus'";
                                                $upd->close();

                                                // Insere no historico_aprovacoes para registrar a mudança
                                                $histColab = is_numeric($colabId) ? (int) $colabId : 0;
                                                $responsavel = 0; // upload automático / sistema
                                                $insHist = $conn->prepare("INSERT INTO historico_aprovacoes (funcao_imagem_id, status_anterior, status_novo, colaborador_id, responsavel) VALUES (?, ?, ?, ?, ?)");
                                                if ($insHist) {
                                                    // status_anterior pode ser NULL; passar como string vazia quando necessário
                                                    $sa = $statusAnterior ?? '';
                                                    $insHist->bind_param('issii', $funcaoImagemId, $sa, $novoStatus, $histColab, $responsavel);
                                                    $insHist->execute();
                                                    $insHist->close();
                                                    $log[] = "Histórico de status criado para funcao_imagem $funcaoImagemId";
                                                } else {
                                                    $log[] = "Falha ao preparar insert historico_aprovacoes: " . $conn->error;
                                                }
                                            } else {
                                                $log[] = "Falha ao preparar update funcao_imagem: " . $conn->error;
                                            }
                                        } else {
                                            $log[] = "Funcao_imagem $funcaoImagemId já está com status '$novoStatus' — sem alteração.";
                                        }
                                    } catch (Exception $e) {
                                        $log[] = "Erro ao atualizar status da funcao_imagem: " . $e->getMessage();
                                    }
                                    // Enviar notificação também para o Slack (se o usuário tiver nome_slack)
                                    try {
                                        $stmtSlack = $conn->prepare("SELECT nome_slack FROM usuario WHERE idcolaborador = ? LIMIT 1");
                                        if ($stmtSlack) {
                                            $stmtSlack->bind_param('i', $colabId);
                                            $stmtSlack->execute();
                                            $resSlack = $stmtSlack->get_result();
                                            if ($resSlack && $rowSlack = $resSlack->fetch_assoc()) {
                                                $nomeSlack = trim($rowSlack['nome_slack']);
                                                if ($nomeSlack) {
                                                    // prefer token-based chat.postMessage when FLOW_TOKEN is set
                                                    $text = "$notifMsg2: $nome_imagem\nAcesse: https://improov.com.br/flow/ImproovWeb/inicio.php";
                                                    if (!empty($FLOW_TOKEN)) {
                                                        $ok = send_slack_token_message($FLOW_TOKEN, $nomeSlack, $text, $log);
                                                        if (!$ok) {
                                                            // Per request: do NOT fallback to webhook/channel — only token-based person messages
                                                            $log[] = "Slack token send failed for $nomeSlack; webhook fallback disabled per config.";
                                                        }
                                                    } else {
                                                        // No FLOW_TOKEN configured — skip sending to Slack for individual
                                                        $log[] = "FLOW_TOKEN not configured; skipping Slack notification for $nomeSlack per request.";
                                                    }
                                                } else {
                                                    $log[] = "Usuário $colabId sem nome_slack cadastrado";
                                                }
                                            }
                                            $stmtSlack->close();
                                        } else {
                                            $log[] = "Falha ao preparar select nome_slack: " . $conn->error;
                                        }
                                    } catch (Throwable $e) {
                                        $log[] = "Erro ao enviar Slack: " . $e->getMessage();
                                    }
                                } else {
                                    $log[] = "Falha ao preparar insert notificacoes: " . $conn->error;
                                }
                            }
                            if ($ins)
                                $ins->close();
                        } else {
                            $log[] = "Nenhum colaborador (funcao_id=4) encontrado para imagem $imagem_id";
                        }
                        $sel->close();
                    } else {
                        $log[] = "Falha ao preparar select funcao_imagem: " . $conn->error;
                    }
                } catch (Exception $e) {
                    $log[] = "Erro ao criar notificacoes: " . $e->getMessage();
                }
            } else {
                $errors[] = "Erro ao enviar '$nomeOriginal' para Imagem $imagem_id";
                $log[] = "Falha ao enviar: $destFile";
            }
        }
    }
}
// Após processar todos os uploads, inserir acompanhamentos agregados (um por categoria|tipo_arquivo)
if (!empty($acompGroups)) {
    $log[] = "Inserindo " . count($acompGroups) . " acompanhamentos agregados...";
    foreach ($acompGroups as $key => $grp) {
        // Preparar texto: categoria antes do tipo de arquivo
        $categoriaNome = buscarNomeCategoria($grp['categoria']);
        $tipoUpper = strtoupper($grp['tipo_arquivo']);
        $targets = array_values(array_unique($grp['targets']));
        $targetsList = implode(', ', $targets);

        $acao = $grp['is_update'] ? ("Atualizado " . $categoriaNome . " em " . $tipoUpper . " para " . $targetsList)
            : ("Adicionado " . $categoriaNome . " em " . $tipoUpper . " para " . $targetsList);
        // Use the selected datetime (general for all uploads) instead of today's date
        $hojeData = $data_recebido_datetime;

        // Use o primeiro arquivo como referência (quando disponível)
        $arquivo_rep = isset($grp['arquivo_ids'][0]) ? intval($grp['arquivo_ids'][0]) : null;

        if ($stmtA = $conn->prepare("INSERT INTO acompanhamento_email (obra_id, colaborador_id, assunto, data, ordem, arquivo_id, tipo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")) {
            $tipo_acomp = 'arquivo';
            $status_acomp = 'pendente';
            $stmtA->bind_param('iissiiss', $obra_id, $colaborador_id_sess, $acao, $hojeData, $next_ordem_acomp, $arquivo_rep, $tipo_acomp, $status_acomp);
            $stmtA->execute();
            $stmtA->close();
            $log[] = "Acompanhamento inserido: [$key] $acao";
            $next_ordem_acomp++;
        } else {
            $log[] = "Falha ao preparar insert agregado em acompanhamento_email: " . $conn->error;
        }
    }
}

$conn->close();
if ($ftp_conn) {
    @ftp_close($ftp_conn);
    $log[] = "Conexão FTP fechada.";
}
echo json_encode(['success' => $success, 'errors' => $errors, 'log' => $log]);

<?php
// CLI worker: processa arquivos em uploads/staging e os envia via SFTP ao NAS.
// Uso: php scripts/upload_worker.php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../conexao.php';

use phpseclib3\Net\SFTP;
use phpseclib3\Exception\UnableToConnectException;
use Predis\Client as PredisClient;

$baseDir = __DIR__ . '/../uploads/staging';
$processedDir = __DIR__ . '/../uploads/sent';
$failedDir = __DIR__ . '/../uploads/failed';

if (!is_dir($processedDir)) mkdir($processedDir, 0777, true);
if (!is_dir($failedDir)) mkdir($failedDir, 0777, true);

// Config SFTP (pode ajustar conforme necessário)
$ftp_user = "flow";
$ftp_pass = "flow@2025";
// Load environment and prefer NAS_IP or NAS_HOST to avoid DNS issues
load_dotenv_if_present();
$__envNasIp = getenv('NAS_IP') ?: null;
$__envNasHost = getenv('NAS_HOST') ?: null;
$ftp_host = $__envNasIp ?: ($__envNasHost ?: "imp-nas.ddns.net");
$ftp_port = 2222;

// Slack webhook URL: set in environment `SLACK_WEBHOOK_URL`
// Example: setx SLACK_WEBHOOK_URL "https://hooks.slack.com/services/..../..../.."

function worker_log(string $message, string $level = 'INFO'): void
{
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] [{$level}] {$message}\n\n";
}

function append_failed_log(string $failedDir, string $processingMeta, string $message): void
{
    $ts = date('c');
    $line = "[{$ts}] {$message}\n\n";
    @file_put_contents($failedDir . '/' . basename($processingMeta) . '.err', $line, FILE_APPEND);
}


function enviarArquivoSFTP($host, $usuario, $senha, $arquivoLocal, $arquivoRemoto, $porta = 2222, callable $onProgress = null)
{
    if (!file_exists($arquivoLocal)) {
        return [false, "Arquivo local não encontrado: $arquivoLocal"];
    }

    try {
        $sftp = new SFTP($host, $porta);
        if (!$sftp->login($usuario, $senha)) {
            return [false, "Falha na autenticação SFTP."];
        }

        $diretorio = dirname($arquivoRemoto);
        if (!$sftp->is_dir($diretorio)) {
            return [false, "Diretório remoto não existe: $diretorio"];
        }

        // If phpseclib provides fopen/fwrite on the SFTP object we can stream and report progress.
        // Some distributions/versions may not expose these methods; provide a safe fallback to put().
        if (method_exists($sftp, 'fopen') && method_exists($sftp, 'fwrite') && method_exists($sftp, 'fclose')) {
            $localHandle = @fopen($arquivoLocal, 'rb');
            if (!$localHandle) return [false, 'Erro ao abrir arquivo local para leitura.'];

            $remoteHandle = $sftp->fopen($arquivoRemoto, 'wb');
            if ($remoteHandle === false) {
                fclose($localHandle);
                return [false, 'Erro ao abrir arquivo remoto para escrita.'];
            }

            $totalSize = filesize($arquivoLocal);
            $sent = 0;
            $chunkSize = 8192;
            while (!feof($localHandle)) {
                $buffer = fread($localHandle, $chunkSize);
                if ($buffer === false) break;
                $written = $sftp->fwrite($remoteHandle, $buffer);
                if ($written === false) {
                    fclose($localHandle);
                    $sftp->fclose($remoteHandle);
                    return [false, 'Erro ao escrever no remoto durante upload.'];
                }
                $sent += strlen($buffer);
                if ($onProgress && $totalSize > 0) {
                    try {
                        $onProgress($sent, $totalSize);
                    } catch (Exception $e) {
                    }
                }
            }

            fclose($localHandle);
            $sftp->fclose($remoteHandle);

            // basic verification: if we reached total size consider OK
            if ($totalSize > 0 && $sent >= $totalSize) {
                return [true, 'OK'];
            }

            return [false, 'Upload incompleto via SFTP.'];
        } else {
            // Fallback: use put() with local file source. This does not provide per-chunk callbacks
            // on all phpseclib builds but will reliably transfer the file. We still call the
            // onProgress callback before/after to indicate start and end.
            if ($onProgress) {
                try {
                    $onProgress(0, filesize($arquivoLocal));
                } catch (Exception $e) {
                }
            }
            if ($sftp->put($arquivoRemoto, $arquivoLocal, SFTP::SOURCE_LOCAL_FILE)) {
                if ($onProgress) {
                    try {
                        $onProgress(filesize($arquivoLocal), filesize($arquivoLocal));
                    } catch (Exception $e) {
                    }
                }
                return [true, 'OK'];
            }
            return [false, 'Erro ao enviar o arquivo via SFTP (put fallback).'];
        }
    } catch (UnableToConnectException $e) {
        return [false, 'Erro ao conectar SFTP: ' . $e->getMessage()];
    } catch (Exception $e) {
        return [false, 'Exceção: ' . $e->getMessage()];
    }
}

// Helpers para sanitização e criação de diretórios remotos
function removerTodosAcentos_worker($str)
{
    return preg_replace(
        ['/[áàãâä]/ui', '/[éèêë]/ui', '/[íìîï]/ui', '/[óòõôö]/ui', '/[úùûü]/ui', '/[ç]/ui'],
        ['a', 'e', 'i', 'o', 'u', 'c'],
        $str
    );
}

function sanitizeFilename_worker($str)
{
    $str = removerTodosAcentos_worker($str);
    $str = preg_replace('/[\/\\:*?"<>|]/', '', $str);
    $str = preg_replace('/\s+/', '_', $str);
    return $str;
}

function ensure_remote_dir_recursive(SFTP $sftp, string $dir): bool
{
    $dir = rtrim($dir, '/');
    if ($sftp->is_dir($dir)) return true;
    $parts = explode('/', ltrim($dir, '/'));
    $path = '';
    foreach ($parts as $p) {
        if ($p === '') continue;
        $path .= '/' . $p;
        if (!$sftp->is_dir($path)) {
            if (!$sftp->mkdir($path)) return false;
        }
    }
    return $sftp->is_dir($dir);
}

// Resolve hostname up front and provide a safe connector that won't fatal on DNS
function resolve_hostname_safe(string $host): ?string
{
    // If host is already a valid IP, use it directly.
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return $host;
    }
    $ip = @gethostbyname($host);
    if (!$ip || $ip === $host) {
        error_log("[upload_worker] DNS resolution failed for host: {$host}");
        return null;
    }
    return $ip;
}

function sftp_connect_safe(string $host, int $port, string $user, string $pass, ?string &$error = null): ?SFTP
{
    $error = null;
    $ip = resolve_hostname_safe($host);
    if ($ip === null) {
        $error = 'Falha ao resolver host SFTP';
        return null;
    }
    try {
        $sftp = new SFTP($ip, $port);
        if (!$sftp->login($user, $pass)) {
            error_log('[upload_worker] SFTP login failed');
            $error = 'Falha no login SFTP';
            return null;
        }
        return $sftp;
    } catch (UnableToConnectException $e) {
        error_log('[upload_worker] SFTP connect error: ' . $e->getMessage());
        $error = 'Falha de conexão SFTP: ' . $e->getMessage();
        return null;
    } catch (Exception $e) {
        error_log('[upload_worker] SFTP generic error: ' . $e->getMessage());
        $error = 'Erro SFTP: ' . $e->getMessage();
        return null;
    }
}

function normalize_nomenclatura_worker(string $value): string
{
    $value = trim($value);
    // remove control chars and non-breaking spaces that may come from form copy/paste
    $value = str_replace("\xC2\xA0", ' ', $value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    return trim($value);
}

// Converte caminho do NAS Linux para caminho acessível no Windows (Z:\)
function to_windows_access_path(string $path): string
{
    // troca prefixo /mnt/clientes por Z:\
    $out = preg_replace('#^/mnt/clientes#i', 'Z:', $path);
    // normaliza barras para backslashes
    $out = str_replace('/', '\\', $out);
    // remove possíveis duplicidades de backslash
    $out = preg_replace('/\\{2,}/', '\\', $out);
    return $out;
}

// Função auxiliar: mapear função -> pasta (mesma lógica simplificada de uploadFinal.php)
function mapFuncaoParaPasta($nome_funcao)
{
    $mapa = [
        'Caderno' => '02.Projetos',
        'Filtro de assets' => '02.Projetos',
        'modelagem' => '03.Models',
        'composição' => '03.Models',
        'finalização' => '03.Models',
        'pré-finalização' => '03.Models',
        'alteração' => '03.Models',
        'Pós-Produção' => '04.Finalizacao',
        'Planta Humanizada' => '04.Finalizacao',
    ];

    foreach ($mapa as $k => $v) {
        if (mb_strtolower($k, 'UTF-8') === mb_strtolower($nome_funcao, 'UTF-8')) return $v;
    }
    return '';
}

// --- New: daemon mode, atomic claim, retries, signal handling ---

$opts = getopt('', ['daemon', 'sleep:']);
$daemon = isset($opts['daemon']);
$sleepWhenIdle = isset($opts['sleep']) ? (int)$opts['sleep'] : 3;

$shutdown = false;
// pcntl extension may be missing on some PHP CLI builds (shared hosting).
// Guard calls to pcntl functions to avoid fatal errors.
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
}
if (function_exists('pcntl_signal') && defined('SIGTERM') && defined('SIGINT')) {
    pcntl_signal(SIGTERM, function () use (&$shutdown) {
        $shutdown = true;
    });
    pcntl_signal(SIGINT, function () use (&$shutdown) {
        $shutdown = true;
    });
} else {
    // pcntl not available: worker will not respond to SIGTERM gracefully.
    worker_log('pcntl extension not available; graceful shutdown disabled.', 'WARN');
}

// helper to claim a job by renaming the meta file atomically
function claimJob(string $metaFile)
{
    $pid = getmypid() ?: uniqid();
    $processing = $metaFile . '.processing.' . $pid;
    if (@rename($metaFile, $processing)) {
        error_log("[upload_worker] claimed {$metaFile} -> {$processing} by pid={$pid}");
        return $processing;
    }
    return false;
}

// helper to write meta back
function writeMeta(string $path, array $meta)
{
    @file_put_contents($path, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// Move a file out of staging to destination dir. Attempts rename, then copy+unlink fallback.
function moveFromStaging(string $src, string $destDir): bool
{
    if (!file_exists($src)) return false;
    if (!is_dir($destDir)) {
        if (!@mkdir($destDir, 0777, true)) {
            error_log("[upload_worker] failed to create dest dir: {$destDir}");
            return false;
        }
    }
    $dest = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . basename($src);
    // Try atomic rename first
    if (@rename($src, $dest)) {
        return true;
    }
    // Fallback: copy then unlink
    if (@copy($src, $dest)) {
        if (@unlink($src)) {
            return true;
        }
        // copy succeeded but unlink failed — leave copy and report
        error_log("[upload_worker] copied but failed to unlink source: {$src}");
        return false;
    }
    error_log("[upload_worker] failed to move file from {$src} to {$dest}");
    return false;
}

function publishProgress($redis, string $id, int $progress, string $status = 'running', string $message = '')
{
    // log attempt for observability (will go to journal when run under systemd)
    error_log("[upload_worker] publishProgress called id={$id} progress={$progress} status={$status} message={$message}");
    if (!$redis || !$id) {
        error_log("[upload_worker] publishProgress: Predis client missing or invalid id");
        return;
    }
    $payload = json_encode(['id' => $id, 'status' => $status, 'progress' => $progress, 'message' => $message]);
    try {
        $redis->publish("upload_progress:{$id}", $payload);
        $redis->setex("upload_status:{$id}", 3600, $payload);
        error_log("[upload_worker] publishProgress: published id={$id} progress={$progress}");
    } catch (Exception $e) {
        error_log("[upload_worker] publishProgress: Redis publish failed: " . $e->getMessage());
    }
}

// --- DB and Slack helpers ---
function load_dotenv_if_present()
{
    $envPath = __DIR__ . '/.env';
    if (!is_file($envPath)) return;
    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
            $key = $m[1];
            $val = $m[2];
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            if (getenv($key) === false) {
                putenv("{$key}={$val}");
            }
        }
    }
}
function ensure_db_connection_local()
{
    global $conn;
    if (!isset($conn)) {
        @include __DIR__ . '/../conexao.php';
        return;
    }
    try {
        if (method_exists($conn, 'ping')) {
            if (!$conn->ping()) {
                @$conn->close();
                @include __DIR__ . '/../conexao.php';
            }
        }
    } catch (Exception $e) {
        try {
            @$conn->close();
        } catch (Exception $_) {
        }
        @include __DIR__ . '/../conexao.php';
    }
}

function create_arquivo_log_table_if_missing()
{
    // No-op in production: tabela já existe com schema real.
}

function create_log_entries_if_missing(string $metaPath, array &$meta, string $remote_path = null, $nome_final = null, $tamanho = null, $tipo = null)
{
    global $conn;
    if (!isset($conn)) return;
    if (!empty($meta['log_ids'])) return;

    // Ensure connection is still alive before inserts
    if (function_exists('ensure_db_connection_local')) {
        ensure_db_connection_local();
    }

    $createIds = [];
    $dataIdFuncoes = $meta['dataIdFuncoes'] ?? [];
    $colaborador_id = $meta['post']['idcolaborador'] ?? null;

    // allow caller to set initial status; default to enfileirado
    $status = $meta['log_status'] ?? 'enfileirado';

    if (empty($dataIdFuncoes)) {
        // insert a single row with funcao_imagem_id = NULL
        $q = $conn->prepare("INSERT INTO arquivo_log (funcao_imagem_id, caminho, nome_arquivo, tamanho, tipo, colaborador_id, status) VALUES (NULL,?,?,?,?,?,?)");
        if ($q) {
            $colaborador_id_int = $colaborador_id !== null ? (int) $colaborador_id : null;
            $q->bind_param('ssisis', $remote_path, $nome_final, $tamanho, $tipo, $colaborador_id_int, $status);
            $q->execute();
            $createIds[] = $q->insert_id;
            $q->close();
        } else {
            error_log('[upload_worker] DB: failed to prepare INSERT (NULL funcao_imagem_id): ' . ($conn->error ?? ''));
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO arquivo_log (funcao_imagem_id, caminho, nome_arquivo, tamanho, tipo, colaborador_id, status) VALUES (?,?,?,?,?,?,?)");
        if ($stmt) {
            foreach ($dataIdFuncoes as $fid) {
                $fid_int = (int) $fid;
                $colaborador_id_int = $colaborador_id !== null ? (int) $colaborador_id : null;
                $stmt->bind_param('issisis', $fid_int, $remote_path, $nome_final, $tamanho, $tipo, $colaborador_id_int, $status);
                $stmt->execute();
                $createIds[] = $stmt->insert_id;
            }
            $stmt->close();
        } else {
            error_log('[upload_worker] DB: failed to prepare INSERT (with funcao_imagem_id): ' . ($conn->error ?? ''));
        }
    }

    if (!empty($createIds)) {
        $meta['log_ids'] = $createIds;
        writeMeta($metaPath, $meta);
    }
}

function update_log_entries_status(array $logIds = null, string $status = '', $caminho = null, $nome_arquivo = null, $tamanho = null, $tipo = null)
{
    global $conn;
    if (!isset($conn) || empty($logIds)) return;

    // Ensure connection is still alive before updates
    if (function_exists('ensure_db_connection_local')) {
        ensure_db_connection_local();
    }

    // Bind tamanho as string so NULL truly stays NULL (and COALESCE keeps old value).
    $sql = "UPDATE arquivo_log SET status = ?, caminho = COALESCE(?, caminho), nome_arquivo = COALESCE(?, nome_arquivo), tamanho = COALESCE(?, tamanho), tipo = COALESCE(?, tipo) WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('[upload_worker] DB: failed to prepare UPDATE arquivo_log: ' . ($conn->error ?? ''));
        return;
    }
    $tamanhoParam = ($tamanho === null) ? null : (string) $tamanho;
    foreach ($logIds as $id) {
        $idInt = (int) $id;
        $stmt->bind_param('sssssi', $status, $caminho, $nome_arquivo, $tamanhoParam, $tipo, $idInt);
        try {
            $stmt->execute();
        } catch (Exception $e) {
            error_log('[upload_worker] DB: UPDATE arquivo_log execute failed id=' . $idInt . ' err=' . $e->getMessage());
        }
    }
    $stmt->close();
}

// Remove any duplicate meta json recreated after claim (race in enqueue)
function cleanup_duplicate_meta_files(string $baseDir, string $jobId, string $processingMeta): void
{
    $original = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $jobId . '.json';
    if (is_file($original) && realpath($original) !== realpath($processingMeta)) {
        @unlink($original);
        error_log('[upload_worker] removed duplicate meta file: ' . $original);
    }
}

function send_slack_notification_for_colaborador($colaborador_id, array $meta, $remote_path = null, $nome_final = null, $ok = true)
{
    global $conn;
    // Load .env and use Slack API with token and optional default channel
    load_dotenv_if_present();
    $token = getenv('SLACK_TOKEN') ?: null;
    $defaultChannel = getenv('SLACK_CHANNEL') ?: null;
    $apiUrl = getenv('SLACK_API_URL') ?: 'https://slack.com/api/chat.postMessage';
    if (!$token) {
        error_log('[upload_worker] Slack token missing: set SLACK_TOKEN');
        return false;
    }
    if (!isset($conn) || !$colaborador_id) return false;
    // Correção: usar colunas reais da tabela `usuario`
    $stmt = $conn->prepare('SELECT nome_slack, nome_usuario FROM usuario WHERE idcolaborador = ? LIMIT 1');
    if (!$stmt) {
        error_log('[upload_worker] Slack: failed to prepare usuario query');
        return false;
    }
    $stmt->bind_param('i', $colaborador_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        error_log("[upload_worker] Slack: no usuario found for idcolaborador={$colaborador_id}");
        return false;
    }
    $nome_slack = $row['nome_slack'] ?? null;
    $nome = $row['nome_usuario'] ?? null;
    if (!$nome_slack) {
        error_log("[upload_worker] Slack: usuario {$nome} missing nome_slack, skipping");
        return false;
    }

    // Determine channel and mention formatting
    $mention = $nome_slack;
    $looksLikeUserId = preg_match('/^[UW][A-Z0-9]+$/', $nome_slack) === 1;
    if ($looksLikeUserId) {
        $mention = "<@{$nome_slack}>";
    }

    $original = $meta['original_name'] ?? ($meta['post']['arquivo_final'] ?? 'arquivo');
    $statusText = $ok ? 'enviado com sucesso' : 'falhou ao enviar';
    $text = "Olá {$mention}, o arquivo *{$original}* foi {$statusText}. Destino: `{$remote_path}`";

    // We must DM the user (no channels). Resolve user ID if nome_slack isn't already an ID.
    $userId = null;
    if ($looksLikeUserId) {
        $userId = $nome_slack;
    } else {
        // Try to resolve via users.list (match display name or real name)
        $listUrl = 'https://slack.com/api/users.list';
        $ch = null;
        $res = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($listUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($res === false || ($httpCode && $httpCode >= 400)) {
                error_log('[upload_worker] Slack users.list failed to resolve user');
                curl_close($ch);
                $res = null;
            } else {
                curl_close($ch);
            }
        } else {
            $ctx = stream_context_create(['http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$token}\r\n",
                'timeout' => 8
            ]]);
            $res = @file_get_contents($listUrl, false, $ctx);
        }
        if ($res) {
            $data = json_decode($res, true);
            if ($data && !empty($data['ok']) && !empty($data['members']) && is_array($data['members'])) {
                foreach ($data['members'] as $m) {
                    $real = ($m['profile']['real_name'] ?? '') ?: '';
                    $display = ($m['profile']['display_name'] ?? '') ?: '';
                    if (mb_strtolower($real) === mb_strtolower($nome_slack) || mb_strtolower($display) === mb_strtolower($nome_slack)) {
                        $userId = $m['id'] ?? null;
                        break;
                    }
                }
            } else {
                error_log('[upload_worker] Slack users.list returned not ok');
            }
        }
        if (!$userId) {
            error_log('[upload_worker] Slack: could not resolve user ID from nome_slack');
            return false;
        }
    }
    $payload = ['channel' => $userId, 'text' => $text];
    $json = json_encode($payload);
    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $token]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($res === false || ($httpCode && $httpCode >= 400)) {
            $err = curl_error($ch);
            error_log("[upload_worker] Slack API failed: http={$httpCode} err={$err}");
            curl_close($ch);
            return false;
        }
        $resp = json_decode($res, true);
        if (!$resp || empty($resp['ok'])) {
            error_log('[upload_worker] Slack API response not ok: ' . $res);
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        error_log('[upload_worker] Slack send OK via API');
        return true;
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$token}\r\n",
            'content' => $json,
            'timeout' => 8
        ]]);
        $res = @file_get_contents($apiUrl, false, $ctx);
        if ($res === false) {
            error_log('[upload_worker] Slack API send failed via file_get_contents');
            return false;
        }
        $resp = json_decode($res, true);
        if (!$resp || empty($resp['ok'])) {
            error_log('[upload_worker] Slack API response not ok: ' . $res);
            return false;
        }
        error_log('[upload_worker] Slack send OK via API (stream)');
        return true;
    }
}

// Process loop (single run or daemon loop)
do {
    // Considere jobs pendentes e jobs já "claimed" (.json.processing and variants)
    $metaFiles = array_merge(
        glob($baseDir . '/*.json') ?: [],
        glob($baseDir . '/*.json.processing*') ?: []
    );
    if (!$metaFiles) {
        if ($daemon && !$shutdown) {
            sleep($sleepWhenIdle);
            continue;
        }
        worker_log('Nenhum item na fila.');
        break;
    }

    foreach ($metaFiles as $metaFile) {
        if ($shutdown) break 2;

        // Se já está em modo processing (qualquer sufixo), use diretamente; senão tente claim.
        if (strpos($metaFile, '.json.processing') !== false) {
            $processingMeta = $metaFile;
            // If the processing file belongs to another PID and is stale, try to take it over.
            if (preg_match('/\.json\.processing\.(\d+)$/', $metaFile, $m)) {
                $ownerPid = $m[1];
                $age = time() - @filemtime($metaFile);
                $staleSeconds = 3600; // 1 hour
                if ($age > $staleSeconds) {
                    $newProc = preg_replace('/\.json\.processing\.(\d+)$/', '.json.processing.' . (getmypid() ?: uniqid()), $metaFile);
                    if (@rename($metaFile, $newProc)) {
                        $processingMeta = $newProc;
                        error_log("[upload_worker] took over stale processing file {$metaFile} -> {$newProc} (age={$age}s)");
                    } else {
                        error_log("[upload_worker] failed to takeover stale processing file {$metaFile}");
                    }
                }
            }
            worker_log("Retomando job em processing: {$processingMeta}");
        } else {
            worker_log("Tentando claim: {$metaFile}");
            $processingMeta = claimJob($metaFile);
            if (!$processingMeta) {
                worker_log("Falha ao claim (outro worker talvez esteja processando): {$metaFile}", 'WARN');
                continue;
            }
        }

        worker_log("Processando {$processingMeta}");
        $meta = json_decode(file_get_contents($processingMeta), true);
        if (!$meta) {
            worker_log("Erro ao ler metadados: {$processingMeta}", 'ERROR');
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            continue;
        }

        // try to connect to Redis for progress publishing
        $redis = null;
        try {
            if (class_exists('\Predis\Client')) {
                $redis = new PredisClient();
            }
        } catch (Exception $e) {
            $redis = null;
        }

        $staged = $meta['staged_path'] ?? null;
        if (!$staged || !file_exists($staged)) {
            // If we already uploaded previously and persisted result, we can still update DB.
            $persistedOk = !empty($meta['uploaded_remote']) && !empty($meta['windows_path']) && !empty($meta['nome_final']) && !empty($meta['tipo']);
            if ($persistedOk) {
                worker_log('Arquivo staged não encontrado, mas upload já foi concluído anteriormente. Tentando apenas atualizar DB.', 'WARN');

                $jobId = $meta['id'] ?? pathinfo($processingMeta, PATHINFO_FILENAME);
                cleanup_duplicate_meta_files($baseDir, $jobId, $processingMeta);

                ensure_db_connection_local();
                create_arquivo_log_table_if_missing();

                // Ensure log rows exist and finalize
                $meta['log_status'] = 'concluido';
                try {
                    create_log_entries_if_missing($processingMeta, $meta, $meta['windows_path'], $meta['nome_final'], $meta['tamanho_final'] ?? null, $meta['tipo']);
                } catch (Exception $_) {
                }
                if (!empty($meta['log_ids'])) {
                    update_log_entries_status($meta['log_ids'], 'concluido', $meta['windows_path'], $meta['nome_final'], $meta['tamanho_final'] ?? null, $meta['tipo']);
                    $meta['db_updated'] = true;
                    writeMeta($processingMeta, $meta);
                    // cleanup meta
                    @unlink($processingMeta);
                    cleanup_duplicate_meta_files($baseDir, $jobId, $processingMeta);
                    worker_log('DB atualizado e job finalizado (sem staged).');
                } else {
                    error_log('[upload_worker] could not finalize: missing log_ids even after insert; keeping meta for retry');
                }
                continue;
            }

            worker_log("Arquivo staged não encontrado: {$staged}", 'ERROR');
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            continue;
        }

        // ensure attempts count
        $meta['attempts'] = isset($meta['attempts']) ? (int)$meta['attempts'] : 0;

        // determine file type early for logging even on failures
        $ext = pathinfo($staged, PATHINFO_EXTENSION);
        $tipo = in_array(strtolower($ext), ['pdf']) ? 'PDF' : 'IMG';

        $jobId = $meta['id'] ?? pathinfo($processingMeta, PATHINFO_FILENAME);
        cleanup_duplicate_meta_files($baseDir, $jobId, $processingMeta);
        publishProgress($redis, $jobId, 0, 'claimed', 'Job claimed by worker');
        // Ensure DB rows exist and update status to 'processando'
        ensure_db_connection_local();
        create_arquivo_log_table_if_missing();
        $meta['log_status'] = 'processando';
        $tamanhoStaged = @filesize($staged) ?: null;
        $nomeInicial = $meta['original_name'] ?? basename($staged);
        try {
            create_log_entries_if_missing($processingMeta, $meta, $staged, $nomeInicial, $tamanhoStaged, $tipo);
        } catch (Exception $_) {
        }
        if (!empty($meta['log_ids'])) {
            update_log_entries_status($meta['log_ids'], 'processando', null, null, null, null);
        } else {
            error_log('[upload_worker] warning: missing log_ids; DB updates will be skipped until fixed');
        }

        // extract post fields
        $nomenclatura = normalize_nomenclatura_worker((string)($meta['post']['nomenclatura'] ?? ''));
        $nome_funcao = $meta['post']['nome_funcao'] ?? '';
        $numeroImagem = $meta['post']['numeroImagem'] ?? '';
        $primeiraPalavra = $meta['post']['primeiraPalavra'] ?? '';
        $nome_imagem = $meta['post']['nome_imagem'] ?? '';
        $nomeStatus = $meta['post']['status_nome'] ?? '';
        // Normalizar componentes do nome do arquivo removendo acentos
        $nomenclatura_clean = removerTodosAcentos_worker($nomenclatura);
        $primeiraPalavra_clean = removerTodosAcentos_worker($primeiraPalavra);
        $nome_imagem_clean = removerTodosAcentos_worker($nome_imagem);

        $pasta_funcao = mapFuncaoParaPasta($nome_funcao);
        if (!$pasta_funcao) {
            worker_log("Função sem pasta mapeada: {$nome_funcao}", 'ERROR');
            rename($staged, $failedDir . '/' . basename($staged));
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            continue;
        }

        $clientes_base = ['/mnt/clientes/2024', '/mnt/clientes/2025', '/mnt/clientes/2026'];
        $upload_ok = '';
        $sftpConnError = '';
        $attemptedDestinos = [];
        foreach ($clientes_base as $base) {
            $destino_base = $base . '/' . $nomenclatura . '/' . $pasta_funcao;
            $attemptedDestinos[] = $destino_base;
            $connErr = null;
            $sftp = sftp_connect_safe($ftp_host, $ftp_port, $ftp_user, $ftp_pass, $connErr);
            if (!$sftp && $connErr && !$sftpConnError) {
                $sftpConnError = $connErr;
            }
            if ($sftp && @$sftp->is_dir($destino_base)) {
                $upload_ok = $destino_base;
                break;
            }
        }

        if (!$upload_ok) {
            $notFoundMsg = "Destino não encontrado para nomenclatura: $nomenclatura";
            if ($sftpConnError) {
                $notFoundMsg .= " (possível erro de conexão: {$sftpConnError})";
            }
            worker_log($notFoundMsg, 'ERROR');
            error_log('[upload_worker] destinos testados: ' . implode(' | ', $attemptedDestinos));
            append_failed_log($failedDir, $processingMeta, $notFoundMsg . ' | destinos testados: ' . implode(' | ', $attemptedDestinos));
            rename($staged, $failedDir . '/' . basename($staged));
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            // Update DB and publish failure
            ensure_db_connection_local();
            create_arquivo_log_table_if_missing();
            $tamanhoFail = file_exists($staged) ? @filesize($staged) : null;
            try {
                create_log_entries_if_missing($processingMeta, $meta, null, null, $tamanhoFail, $tipo);
            } catch (Exception $_) {
            }
            if (!empty($meta['log_ids'])) {
                update_log_entries_status($meta['log_ids'], 'falha', null, null, null, null);
            }
            publishProgress($redis, $jobId, 0, 'failed', 'Destino remoto não encontrado');
            // Slack failure notification
            $colab = $meta['post']['idcolaborador'] ?? null;
            if ($colab) {
                try {
                    send_slack_notification_for_colaborador($colab, $meta, null, null, false);
                } catch (Exception $_) {
                }
            }
            continue;
        }

        $semAcento = removerTodosAcentos_worker($nome_funcao);
        $processo = strtoupper(mb_substr($semAcento, 0, 3, 'UTF-8'));
        $nome_base = "{$numeroImagem}.{$nomenclatura_clean}-{$primeiraPalavra_clean}-{$tipo}-{$processo}";
        $revisao = $nomeStatus ?: 'R00';
        $remote_dir = $upload_ok;
        $nome_final = "{$nome_base}-{$revisao}.{$ext}";

        // Regras especiais iguais ao uploadFinal.php
        $funcao_normalizada = mb_strtolower($nome_funcao, 'UTF-8');
        if ($pasta_funcao === '03.Models') {
            $nomeImagemSanitizado = sanitizeFilename_worker($nome_imagem);
            $funcao_key = $funcao_normalizada;
            if ($funcao_key === 'alteração' || $funcao_key === 'alteracao') {
                $remote_dir = $remote_dir . "/{$nomeImagemSanitizado}/Final/{$revisao}";
            } else {
                $mapa_sub = [
                    'modelagem' => 'MT',
                    'composição' => 'Comp',
                    'composicao' => 'Comp',
                    'finalização' => 'Final',
                    'finalizacao' => 'Final',
                    'pré-finalização' => 'Final',
                    'pre-finalizacao' => 'Final'
                ];
                $subpasta_funcao = $mapa_sub[$funcao_key] ?? 'OUTROS';
                $remote_dir = $remote_dir . "/{$nomeImagemSanitizado}/{$subpasta_funcao}";
            }
        } elseif ($funcao_normalizada === 'pós-produção' || $funcao_normalizada === 'pos-producao' || $funcao_normalizada === 'pos-produção') {
            // Pós-Produção: nome_final = nome_imagem_revisao.ext em pasta revisao
            $nome_final = "{$nome_imagem_clean}_{$revisao}.{$ext}";
            $remote_dir = $remote_dir . "/{$revisao}";
        } elseif ($funcao_normalizada === 'planta humanizada') {
            $nome_final = "{$nome_imagem_clean}_{$revisao}.{$ext}";
            $remote_dir = $remote_dir . "/{$revisao}/PH";
        }

        // Garantir diretórios remotos existentes
        $sftpPrep = new SFTP($ftp_host, $ftp_port);
        if (!$sftpPrep->login($ftp_user, $ftp_pass)) {
            $lastMsg = 'Falha na autenticação SFTP ao preparar diretórios.';
            worker_log($lastMsg, 'ERROR');
            append_failed_log($failedDir, $processingMeta, $lastMsg);
            rename($staged, $failedDir . '/' . basename($staged));
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            publishProgress($redis, $jobId, 0, 'failed', $lastMsg);
            ensure_db_connection_local();
            if (!empty($meta['log_ids'])) {
                update_log_entries_status($meta['log_ids'], 'falha', null, null, null, null);
            }
            // Slack failure notification
            $colab = $meta['post']['idcolaborador'] ?? null;
            if ($colab) {
                try {
                    send_slack_notification_for_colaborador($colab, $meta, null, null, false);
                } catch (Exception $_) {
                }
            }
            continue;
        }
        if (!ensure_remote_dir_recursive($sftpPrep, $remote_dir)) {
            $lastMsg = 'Não foi possível criar/verificar diretórios remotos: ' . $remote_dir;
            worker_log($lastMsg, 'ERROR');
            append_failed_log($failedDir, $processingMeta, $lastMsg);
            rename($staged, $failedDir . '/' . basename($staged));
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            publishProgress($redis, $jobId, 0, 'failed', $lastMsg);
            ensure_db_connection_local();
            if (!empty($meta['log_ids'])) {
                update_log_entries_status($meta['log_ids'], 'falha', null, null, null, null);
            }
            // Slack failure notification
            $colab = $meta['post']['idcolaborador'] ?? null;
            if ($colab) {
                try {
                    send_slack_notification_for_colaborador($colab, $meta, null, null, false);
                } catch (Exception $_) {
                }
            }
            continue;
        }
        $remote_path = $remote_dir . '/' . $nome_final;
        $windows_path = to_windows_access_path($remote_path);

        // Persist computed destinations early (helps recovery if process is interrupted)
        $meta['remote_path'] = $remote_path;
        $meta['windows_path'] = $windows_path;
        $meta['nome_final'] = $nome_final;
        $meta['tipo'] = $tipo;
        writeMeta($processingMeta, $meta);

        // retry loop with exponential backoff
        $maxAttempts = 5;
        $ok = false;
        $lastMsg = '';
        while ($meta['attempts'] < $maxAttempts && !$ok && !$shutdown) {
            $meta['attempts']++;
            writeMeta($processingMeta, $meta);

            publishProgress($redis, $jobId, 10, 'connecting', 'Conectando ao SFTP');
            // attempt upload with progress callback
            list($ok, $msg) = enviarArquivoSFTP($ftp_host, $ftp_user, $ftp_pass, $staged, $remote_path, $ftp_port, function ($sent, $total) use ($redis, $jobId) {
                $pct = (int) round(($sent / max(1, $total)) * 100);
                // don't spam too frequently: publish at increments of 1% or when reaching 100%
                static $lastPct = null;
                if ($lastPct === null || $pct >= $lastPct + 1 || $pct === 100) {
                    publishProgress($redis, $jobId, $pct, 'uploading', "Transferindo ({$sent}/{$total})");
                    // optional: we skip frequent DB progress updates to reduce writes
                    $lastPct = $pct;
                }
            });

            if ($ok) {
                publishProgress($redis, $jobId, 100, 'done', 'Enviado com sucesso');
                // defer final DB update to finalize block below
            } else {
                publishProgress($redis, $jobId, min(95, 10 + $meta['attempts'] * 15), 'retry', $msg);
                // retry state is transient; no DB write
            }
            $lastMsg = $msg;
            if ($ok) break;
        }

        // after retry loop: finalize
        if ($ok) {
            $enviadoEm = date('Y-m-d H:i:s');
            worker_log("Enviado com sucesso em {$enviadoEm}: {$remote_path}", 'SUCCESS');
            // calcular tamanho final antes de remover o arquivo local
            $tamanho_final = @filesize($staged) ?: null;

            // Persist success result before DB update / cleanup
            $meta['uploaded_remote'] = true;
            $meta['upload_success_at'] = date('c');
            $meta['tamanho_final'] = $tamanho_final;
            $meta['db_updated'] = false;
            writeMeta($processingMeta, $meta);

            // final DB update and Slack notify (status: concluido) BEFORE cleanup
            ensure_db_connection_local();
            if (empty($meta['log_ids'])) {
                // If enqueue failed to insert, create now.
                $meta['log_status'] = 'concluido';
                try {
                    create_log_entries_if_missing($processingMeta, $meta, $windows_path, $nome_final, $tamanho_final ?: null, $tipo);
                } catch (Exception $_) {
                }
            }
            if (!empty($meta['log_ids'])) {
                // salva caminho acessível no Windows (Z:\) no banco
                update_log_entries_status($meta['log_ids'], 'concluido', $windows_path, $nome_final, $tamanho_final ?: null, $tipo);
                $meta['db_updated'] = true;
                writeMeta($processingMeta, $meta);
            } else {
                error_log('[upload_worker] ERROR: upload ok but could not determine log_ids; keeping meta for manual recovery');
                // do not cleanup; allow retry
                continue;
            }

            // remover o arquivo staged para evitar acumulo no VPS; se falhar, mover para `sent` como fallback
            if (file_exists($staged)) {
                if (!@unlink($staged)) {
                    @rename($staged, $processedDir . '/' . basename($staged));
                    error_log("[upload_worker] warning: failed to unlink staged, moved to sent as fallback: {$staged}");
                }
            }

            // remover o arquivo de metadados processing; se falhar, mover para `sent` como fallback
            if (file_exists($processingMeta)) {
                if (!@unlink($processingMeta)) {
                    @rename($processingMeta, $processedDir . '/' . basename($processingMeta));
                    error_log("[upload_worker] warning: failed to unlink processing meta, moved to sent as fallback: {$processingMeta}");
                }
            }

            // Remove any duplicate original meta left behind by old race conditions
            cleanup_duplicate_meta_files($baseDir, $jobId, $processingMeta);

            publishProgress($redis, $jobId, 100, 'done', 'Finalizado e removido localmente');
            // send slack to collaborator if present
            $colab = $meta['post']['idcolaborador'] ?? null;
            if ($colab) {
                try {
                    send_slack_notification_for_colaborador($colab, $meta, $windows_path, null, true);
                } catch (Exception $_) {
                }
            }
        } else {
            $detalheErro = "Falha ao enviar (todas tentativas ou erro irreversível): {$lastMsg}";
            if (!empty($remote_path)) {
                $detalheErro .= " | destino remoto: {$remote_path}";
            }
            if (!empty($meta['attempts'])) {
                $detalheErro .= " | tentativas: {$meta['attempts']}";
            }
            worker_log($detalheErro, 'ERROR');
            append_failed_log($failedDir, $processingMeta, $detalheErro);
            rename($staged, $failedDir . '/' . basename($staged));
            rename($processingMeta, $failedDir . '/' . basename($processingMeta));
            publishProgress($redis, $jobId, 0, 'failed', $lastMsg);
            ensure_db_connection_local();
            if (!empty($meta['log_ids'])) {
                update_log_entries_status($meta['log_ids'], 'falha', null, null, null, null);
            }
            // send slack failure notice
            $colab = $meta['post']['idcolaborador'] ?? null;
            if ($colab) {
                try {
                    send_slack_notification_for_colaborador($colab, $meta, $remote_path, null, false);
                } catch (Exception $_) {
                }
            }
        }
    }

    if (!$daemon) break;
    if (!$shutdown) sleep($sleepWhenIdle);
} while (!$shutdown);

worker_log('Worker finalizado.');

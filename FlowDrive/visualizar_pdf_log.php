<?php
// Visualizador de PDF para registros do arquivo_log (Processos anteriores).
// Busca o arquivo pelo id no banco, valida sessão e faz streaming do PDF.

// Evita corromper o streaming do PDF com warnings/notices.
require_once __DIR__ . '/../config/session_bootstrap.php';
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
@ini_set('log_errors', '1');

// Prevent caching of user-specific pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Tue, 01 Jan 2000 00:00:00 GMT');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Improve robustness for long downloads and background aborts
@ignore_user_abort(true);
@set_time_limit(60);

// Simple file logging to help diagnose intermittent failures
$__vpl_logfile = __DIR__ . '/visualizar_pdf_log.log';
function __vpl_write_log($msg)
{
    global $__vpl_logfile;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $line = '[' . date('c') . '] ' . $ip . ' - ' . trim((string) $msg) . PHP_EOL;
    @file_put_contents($__vpl_logfile, $line, FILE_APPEND | LOCK_EX);
}

// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     header('Location: ../index.html');
//     exit;
// }

include '../conexao.php';

$idlog = isset($_GET['idlog']) ? intval($_GET['idlog']) : 0;
if ($idlog <= 0) {
    http_response_code(400);
    __vpl_write_log('invalid idlog: ' . var_export($_GET, true));
    echo 'Parâmetro idlog inválido.';
    exit;
}

$stmt = $conn->prepare("SELECT id, tipo, caminho, nome_arquivo FROM arquivo_log WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo 'Erro interno (prepare).';
    exit;
}
$stmt->bind_param('i', $idlog);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    http_response_code(404);
    __vpl_write_log('arquivo nao encontrado for id=' . $idlog);
    echo 'Arquivo não encontrado.';
    exit;
}

$row = $res->fetch_assoc();
$stmt->close();
$conn->close();

function normalize_path_slashes($path)
{
    $p = str_replace('\\', '/', (string) $path);
    $p = preg_replace('#/+#', '/', $p);
    return $p;
}

function build_candidate_paths($rawPath)
{
    $raw = (string) $rawPath;
    $norm = normalize_path_slashes($raw);

    $candidates = [];
    if ($raw !== '') $candidates[] = $raw;
    if ($norm !== '' && $norm !== $raw) $candidates[] = $norm;

    // Map common Windows drive path (Z:\...) to NAS mount (/mnt/clientes/...)
    // Example: Z:\2025\ABF_ALM\... -> /mnt/clientes/2025/ABF_ALM/...
    if (preg_match('#^[A-Za-z]:/#', $norm)) {
        $rest = substr($norm, 3); // drop "Z:/"
        $mapped = '/mnt/clientes/' . ltrim($rest, '/');
        $candidates[] = $mapped;
    }

    // De-duplicate
    $out = [];
    foreach ($candidates as $c) {
        $c = trim((string) $c);
        if ($c === '') continue;
        if (!in_array($c, $out, true)) $out[] = $c;
    }
    return $out;
}

function safe_filename($name)
{
    $name = (string) $name;
    $name = preg_replace('/[\r\n\t]+/', ' ', $name);
    $name = preg_replace('/[^A-Za-z0-9._\-\s\(\)\[\]]+/', '_', $name);
    $name = trim($name);
    return $name !== '' ? $name : 'arquivo.pdf';
}

function stream_pdf_from_caminho($row, $download = false)
{
    $tipo = strtoupper(trim($row['tipo'] ?? ''));
    if ($tipo !== 'PDF') {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Este arquivo não é um PDF.';
        exit;
    }

    $nome = $row['nome_arquivo'] ?: ('ArquivoLog_' . (int) ($row['id'] ?? 0));
    $filename = safe_filename($nome);
    if (!preg_match('/\.pdf$/i', $filename)) {
        $filename .= '.pdf';
    }

    $rawCaminho = $row['caminho'] ?? '';
    $candidates = build_candidate_paths($rawCaminho);
    if (count($candidates) === 0) {
        http_response_code(500);
        __vpl_write_log('no candidates for caminho=' . var_export($rawCaminho, true));
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Caminho do arquivo não encontrado.';
        exit;
    }

    // 1) Try local filesystem first
    foreach ($candidates as $p) {
        $isFile = @is_file($p);
        $isReadable = @is_readable($p);
        __vpl_write_log('checking local candidate: ' . $p . ' is_file=' . ($isFile ? '1' : '0') . ' is_readable=' . ($isReadable ? '1' : '0'));
        if ($isFile && $isReadable) {
            // clear any buffered output before sending binary
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            header('X-Content-Type-Options: nosniff');
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');

            $size = @filesize($p);
            if ($size !== false) {
                header('Content-Length: ' . $size);
            }
            $ok = @readfile($p);
            if ($ok === false) {
                __vpl_write_log('readfile failed for ' . $p);
            } else {
                __vpl_write_log('served local file ' . $p . ' size=' . ($size !== false ? $size : 'unknown'));
            }
            exit;
        }
    }

    // 2) Fallback: fetch via SFTP (NAS)
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../config/secure_env.php';
    try {
        $cfg = improov_sftp_config();
    } catch (RuntimeException $e) {
        __vpl_write_log('improov_sftp_config exception: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Configuração SFTP ausente.';
        exit;
    }

    $host = $cfg['host'];
    $port = (int) $cfg['port'];
    $username = $cfg['user'];
    $password = $cfg['pass'];

    $sftp = new \phpseclib3\Net\SFTP($host, $port);
    // try login with a small retry to handle transient network issues
    $loginOk = false;
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        if ($sftp->login($username, $password)) {
            $loginOk = true;
            __vpl_write_log('sftp login success on attempt ' . $attempt . ' host=' . $host . ' user=' . $username);
            break;
        }
        __vpl_write_log('sftp login failed on attempt ' . $attempt . ' host=' . $host . ' user=' . $username);
        if ($attempt === 1) {
            usleep(200000); // 200ms before retry
        }
    }
    if (!$loginOk) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Falha ao conectar no servidor de arquivos.';
        exit;
    }

    $data = false;
    $usedPath = '';
    // try each candidate, with one retry per path to mitigate transient SFTP hiccups
    foreach ($candidates as $p) {
        $p = normalize_path_slashes($p);
        __vpl_write_log('sftp get attempt for path: ' . $p);
        $try = $sftp->get($p);
        if ($try === false) {
            // retry once
            __vpl_write_log('sftp get failed, retrying: ' . $p);
            usleep(150000);
            $try = $sftp->get($p);
        }
        if ($try !== false) {
            $data = $try;
            $usedPath = $p;
            __vpl_write_log('sftp get success for: ' . $p . ' bytes=' . strlen($try));
            break;
        }
        __vpl_write_log('sftp get ultimately failed for: ' . $p);
    }

    if ($data === false) {
        __vpl_write_log('no data returned from sftp for id=' . ($row['id'] ?? 'unknown'));
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Arquivo PDF não encontrado no servidor.';
        exit;
    }

    // Basic sanity check to avoid returning non-PDF bytes as application/pdf
    // Some files may have a few leading whitespace bytes, so search a small prefix.
    $prefix = substr($data, 0, 1024);
    if (strpos($prefix, '%PDF-') === false) {
        __vpl_write_log('invalid pdf prefix for usedPath=' . $usedPath . ' prefix=' . substr($prefix, 0, 200));
        http_response_code(502);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Resposta inválida ao buscar PDF (' . $usedPath . ').';
        exit;
    }

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Content-Length: ' . strlen($data));
    echo $data;
    __vpl_write_log('streamed sftp data for ' . $usedPath . ' bytes=' . strlen($data));
    exit;
}

$raw = isset($_GET['raw']) ? (int) $_GET['raw'] : 0;
if ($raw === 1) {
    $download = isset($_GET['download']) ? (int) $_GET['download'] : 0;
    stream_pdf_from_caminho($row, $download === 1);
}

// Default: simple wrapper page with iframe
$tipo = strtoupper(trim($row['tipo'] ?? ''));
if ($tipo !== 'PDF') {
    http_response_code(400);
    echo 'Este arquivo não é um PDF.';
    exit;
}

$nome = $row['nome_arquivo'] ?: ('ArquivoLog #' . $idlog);
$safeNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
$self = basename($_SERVER['PHP_SELF']);
$iframeSrc = $self . '?idlog=' . urlencode((string) $idlog) . '&raw=1';
$downloadHref = $self . '?idlog=' . urlencode((string) $idlog) . '&raw=1&download=1';

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PDF - <?php echo $safeNome; ?></title>
    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            font-family: Arial, sans-serif;
            background: #0b1220;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 14px;
            color: #e5e7eb;
            background: #111827;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .title {
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn {
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 13px;
        }

        .btn.secondary {
            background: #374151;
        }

        .frame-wrap {
            height: calc(100% - 52px);
        }

        iframe {
            width: 100%;
            height: 100%;
            border: 0;
            background: #0b1220;
        }
    </style>
</head>

<body>
    <div class="topbar">
        <div class="title" title="<?php echo $safeNome; ?>"><?php echo $safeNome; ?></div>
        <div class="actions">
            <a class="btn secondary" href="<?php echo htmlspecialchars($downloadHref, ENT_QUOTES, 'UTF-8'); ?>">Baixar</a>
            <a class="btn" href="<?php echo htmlspecialchars($iframeSrc, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Abrir em nova aba</a>
        </div>
    </div>
    <div class="frame-wrap">
        <iframe src="<?php echo htmlspecialchars($iframeSrc, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo $safeNome; ?>"></iframe>
    </div>
</body>

</html>
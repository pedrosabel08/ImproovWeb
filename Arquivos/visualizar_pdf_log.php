<?php
// Visualizador de PDF para registros do arquivo_log (Processos anteriores).
// Busca o arquivo pelo id no banco, valida sessão e faz streaming do PDF.

// Evita corromper o streaming do PDF com warnings/notices.
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

// if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
//     header('Location: ../index.html');
//     exit;
// }

include '../conexao.php';

$idlog = isset($_GET['idlog']) ? intval($_GET['idlog']) : 0;
if ($idlog <= 0) {
    http_response_code(400);
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
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Caminho do arquivo não encontrado.';
        exit;
    }

    // 1) Try local filesystem first
    foreach ($candidates as $p) {
        if (@is_file($p) && @is_readable($p)) {
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
            @readfile($p);
            exit;
        }
    }

    // 2) Fallback: fetch via SFTP (NAS)
    require_once __DIR__ . '/../vendor/autoload.php';
    $host = getenv('IMPROOV_SFTP_HOST') ?: 'imp-nas.ddns.net';
    $port = (int) (getenv('IMPROOV_SFTP_PORT') ?: 2222);
    $username = getenv('IMPROOV_SFTP_USER') ?: 'flow';
    $password = getenv('IMPROOV_SFTP_PASS') ?: 'flow@2025';

    $sftp = new \phpseclib3\Net\SFTP($host, $port);
    if (!$sftp->login($username, $password)) {
        http_response_code(502);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Falha ao conectar no servidor de arquivos.';
        exit;
    }

    $data = false;
    $usedPath = '';
    foreach ($candidates as $p) {
        $p = normalize_path_slashes($p);
        $try = $sftp->get($p);
        if ($try !== false) {
            $data = $try;
            $usedPath = $p;
            break;
        }
    }

    if ($data === false) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Arquivo PDF não encontrado no servidor.';
        exit;
    }

    // Basic sanity check to avoid returning non-PDF bytes as application/pdf
    // Some files may have a few leading whitespace bytes, so search a small prefix.
    $prefix = substr($data, 0, 1024);
    if (strpos($prefix, '%PDF-') === false) {
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
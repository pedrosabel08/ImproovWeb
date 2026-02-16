<?php
// Visualizador de PDF para a tela de Arquivos.
// Busca o arquivo pelo id no banco, valida sessão e abre em um iframe.

// Prevent caching of user-specific pages
require_once __DIR__ . '/../config/session_bootstrap.php';
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

$idarquivo = isset($_GET['idarquivo']) ? intval($_GET['idarquivo']) : 0;
if ($idarquivo <= 0) {
    http_response_code(400);
    echo 'Parâmetro idarquivo inválido.';
    exit;
}

$stmt = $conn->prepare("SELECT idarquivo, tipo, caminho, nome_interno, nome_original FROM arquivos WHERE idarquivo = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo 'Erro interno (prepare).';
    exit;
}
$stmt->bind_param('i', $idarquivo);
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
    $p = str_replace('\\', '/', (string)$path);
    $p = preg_replace('#/+#', '/', $p);
    return $p;
}

function safe_filename($name)
{
    $name = (string)$name;
    $name = preg_replace('/[\r\n\t]+/', ' ', $name);
    $name = preg_replace('/[^A-Za-z0-9._\-\s\(\)\[\]]+/', '_', $name);
    $name = trim($name);
    return $name !== '' ? $name : 'arquivo.pdf';
}

function detect_base_origin()
{
    $scheme = 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function caminho_para_url_publica($caminho)
{
    if (!$caminho) return null;

    $baseOrigin = getenv('IMPROOV_PUBLIC_BASE') ?: detect_base_origin();
    $appPrefix = getenv('IMPROOV_APP_PREFIX') ?: '/flow/ImproovWeb/';

    // Normalize slashes
    $p = normalize_path_slashes($caminho);

    // Already a URL
    if (preg_match('#^https?://#i', $p)) {
        return $p;
    }

    // Common case: absolute path containing /public_html/
    $needle = '/public_html/';
    $pos = strpos($p, $needle);
    if ($pos !== false) {
        $after = substr($p, $pos + strlen($needle));
        $after = ltrim($after, '/');
        return rtrim($baseOrigin, '/') . '/' . $after;
    }

    // Stored as relative path (e.g. uploads/...) or containing /uploads/
    if (strpos($p, 'uploads/') === 0) {
        return rtrim($baseOrigin, '/') . rtrim($appPrefix, '/') . '/' . $p;
    }

    $posUp = strpos($p, '/uploads/');
    if ($posUp !== false) {
        $after = substr($p, $posUp + 1); // remove leading '/'
        return rtrim($baseOrigin, '/') . rtrim($appPrefix, '/') . '/' . $after;
    }

    return null;
}

function stream_pdf_from_source($row, $download = false)
{
    $tipo = strtoupper(trim($row['tipo'] ?? ''));
    if ($tipo !== 'PDF') {
        http_response_code(400);
        echo 'Este arquivo não é um PDF.';
        exit;
    }

    $nome = $row['nome_original'] ?: ($row['nome_interno'] ?: ('Arquivo_' . (int)$row['idarquivo']));
    $filename = safe_filename($nome);
    if (!preg_match('/\.pdf$/i', $filename)) {
        $filename .= '.pdf';
    }

    $caminho = normalize_path_slashes($row['caminho'] ?? '');
    if ($caminho === '') {
        http_response_code(500);
        echo 'Caminho do arquivo não encontrado.';
        exit;
    }

    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    // 1) Try local filesystem first (if the web server has the mount available)
    if (@is_file($caminho) && @is_readable($caminho)) {
        $size = @filesize($caminho);
        if ($size !== false) {
            header('Content-Length: ' . $size);
        }
        @readfile($caminho);
        exit;
    }

    // 2) Fallback: fetch via SFTP (NAS)
    require_once __DIR__ . '/../vendor/autoload.php';
    $host = getenv('IMPROOV_SFTP_HOST') ?: 'imp-nas.ddns.net';
    $port = (int)(getenv('IMPROOV_SFTP_PORT') ?: 2222);
    $username = getenv('IMPROOV_SFTP_USER') ?: 'flow';
    $password = getenv('IMPROOV_SFTP_PASS') ?: 'flow@2025';

    $sftp = new \phpseclib3\Net\SFTP($host, $port);
    if (!$sftp->login($username, $password)) {
        http_response_code(502);
        echo 'Falha ao conectar no servidor de arquivos.';
        exit;
    }

    $data = $sftp->get($caminho);
    if ($data === false) {
        http_response_code(404);
        echo 'Arquivo PDF não encontrado no servidor.';
        exit;
    }

    header('Content-Length: ' . strlen($data));
    echo $data;
    exit;
}

$tipo = strtoupper(trim($row['tipo'] ?? ''));
$nome = $row['nome_original'] ?: ($row['nome_interno'] ?: ('Arquivo #' . $idarquivo));
$urlPublica = caminho_para_url_publica($row['caminho'] ?? '');

// Raw mode: stream the PDF bytes (works even when there's no public URL)
$raw = isset($_GET['raw']) ? (int)$_GET['raw'] : 0;
if ($raw === 1) {
    $download = isset($_GET['download']) ? (int)$_GET['download'] : 0;
    stream_pdf_from_source($row, $download === 1);
}

if ($tipo !== 'PDF') {
    http_response_code(400);
    echo 'Este arquivo não é um PDF.';
    exit;
}

$safeNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
$safePublicUrl = $urlPublica ? htmlspecialchars($urlPublica, ENT_QUOTES, 'UTF-8') : '';

$self = basename($_SERVER['PHP_SELF']);
$iframeSrc = $self . '?idarquivo=' . urlencode((string)$idarquivo) . '&raw=1';
$downloadHref = $self . '?idarquivo=' . urlencode((string)$idarquivo) . '&raw=1&download=1';

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

        .hint {
            padding: 18px;
            color: #e5e7eb;
        }
    </style>
</head>

<body>
    <div class="topbar">
        <div class="title" title="<?php echo $safeNome; ?>"><?php echo $safeNome; ?></div>
        <div class="actions">
            <a class="btn secondary" href="../Arquivos/index.php">Voltar</a>
            <a class="btn secondary" href="<?php echo htmlspecialchars($downloadHref, ENT_QUOTES, 'UTF-8'); ?>">Baixar</a>
            <?php if (!empty($safePublicUrl)) { ?>
                <a class="btn" href="<?php echo $safePublicUrl; ?>" target="_blank" rel="noopener">Abrir URL pública</a>
            <?php } ?>
        </div>
    </div>

    <div class="frame-wrap">
        <iframe src="<?php echo htmlspecialchars($iframeSrc, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo $safeNome; ?>"></iframe>
        <noscript>
            <div class="hint">JavaScript desabilitado. <a href="<?php echo htmlspecialchars($iframeSrc, ENT_QUOTES, 'UTF-8'); ?>" style="color:#93c5fd">Clique aqui para abrir o PDF</a>.</div>
        </noscript>
    </div>
</body>

</html>
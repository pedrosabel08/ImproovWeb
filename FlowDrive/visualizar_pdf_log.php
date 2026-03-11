<?php
// Visualizador de PDF para registros do arquivo_log.
// Busca o arquivo pelo id no banco e faz streaming do PDF.

require_once __DIR__ . '/../config/session_bootstrap.php';
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
@ini_set('log_errors', '1');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Tue, 01 Jan 2000 00:00:00 GMT');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

@ignore_user_abort(true);
@set_time_limit(120);

// Logging simples para diagnostico
$__vpl_logfile = __DIR__ . '/visualizar_pdf_log.log';
function vpl_log($msg)
{
    global $__vpl_logfile;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
    @file_put_contents(
        $__vpl_logfile,
        '[' . date('c') . '] ' . $ip . ' - ' . trim((string) $msg) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

include '../conexao.php';

$idlog = isset($_GET['idlog']) ? intval($_GET['idlog']) : 0;
if ($idlog <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Parametro invalido.';
    exit;
}

$stmt = $conn->prepare("SELECT id, tipo, caminho, nome_arquivo FROM arquivo_log WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo 'Erro interno.';
    exit;
}
$stmt->bind_param('i', $idlog);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Registro nao encontrado.';
    exit;
}

if (strtoupper(trim($row['tipo'] ?? '')) !== 'PDF') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Este arquivo nao e um PDF.';
    exit;
}

/**
 * Converte caminho Windows (Z:\...) para caminho Linux no NAS (/mnt/clientes/...).
 * Z:\2026\CIB_OCE\02.Projetos\file.pdf  =>  /mnt/clientes/2026/CIB_OCE/02.Projetos/file.pdf
 */
function vpl_map_path(string $raw): string
{
    $p = str_replace('\\', '/', $raw);
    $p = preg_replace('#/+#', '/', $p);
    if (preg_match('#^[A-Za-z]:/#', $p)) {
        $p = '/mnt/clientes/' . ltrim(substr($p, 3), '/');
    }
    return $p;
}

function vpl_safe_filename(string $name): string
{
    $name = preg_replace('/[\r\n\t]+/', ' ', $name);
    $name = preg_replace('/[^A-Za-z0-9._\-\s\(\)\[\]]+/', '_', $name);
    $name = trim($name);
    return $name !== '' ? $name : 'arquivo.pdf';
}

// -----------------------------------------------------------------------------
// Streaming do PDF (raw=1)
// -----------------------------------------------------------------------------
if ((isset($_GET['raw']) ? (int) $_GET['raw'] : 0) === 1) {
    $download = (isset($_GET['download']) ? (int) $_GET['download'] : 0) === 1;

    $nome     = $row['nome_arquivo'] ?: ('ArquivoLog_' . $row['id']);
    $filename = vpl_safe_filename($nome);
    if (!preg_match('/\.pdf$/i', $filename)) {
        $filename .= '.pdf';
    }

    $path = vpl_map_path((string) ($row['caminho'] ?? ''));
    vpl_log('caminho mapeado: ' . $path);

    // 1. Arquivo local -----------------------------------------------------------
    if (@is_file($path) && @is_readable($path)) {
        $size = @filesize($path);
        vpl_log('servindo arquivo local: ' . $path . ' tamanho=' . $size);
        while (ob_get_level() > 0) @ob_end_clean();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        header($download ? 'Cache-Control: private, no-store' : 'Cache-Control: private, max-age=300');
        if ($size !== false) header('Content-Length: ' . $size);
        readfile($path);
        exit;
    }

    vpl_log('arquivo local nao encontrado, buscando via SFTP: ' . $path);

    // Configuracao SFTP
    require_once __DIR__ . '/../config/secure_env.php';
    try {
        $cfg = improov_sftp_config();
    } catch (\Exception $e) {
        vpl_log('erro na configuracao SFTP: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Configuracao SFTP ausente. Contate o administrador.';
        exit;
    }

    $host     = $cfg['host'];
    $port     = (int) $cfg['port'];
    $sftpUser = $cfg['user'];
    $sftpPass = $cfg['pass'];

    $data = null;

    // 2. curl SFTP (metodo principal — libcurl nativo, sem phpseclib/libsodium) --
    if (function_exists('curl_init') && defined('CURLSSH_AUTH_PASSWORD')) {
        $url = "sftp://{$host}:{$port}{$path}";
        vpl_log('tentativa curl SFTP: ' . $url);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL             => $url,
            CURLOPT_USERPWD         => "{$sftpUser}:{$sftpPass}",
            CURLOPT_SSH_AUTH_TYPES  => CURLSSH_AUTH_PASSWORD,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => 120,
            CURLOPT_CONNECTTIMEOUT  => 10,
            // NAS interno — dispensa verificacao de chave do host
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => 0,
        ]);
        $result  = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($result !== false && $curlErr === '' && strlen((string) $result) > 0) {
            $data = $result;
            vpl_log('curl SFTP sucesso: ' . $path . ' bytes=' . strlen($data));
        } else {
            vpl_log('curl SFTP falhou: ' . ($curlErr ?: 'resposta vazia'));
        }
    } else {
        vpl_log('curl SFTP indisponivel (extensao curl ou CURLSSH_AUTH_PASSWORD ausente)');
    }

    // 3. Fallback: phpseclib3 forcando OpenSSL_GCM (evita libsodium) -----------
    if ($data === null) {
        vpl_log('fallback phpseclib3 com OpenSSL_GCM...');
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            // Forca o motor OpenSSL_GCM para cifras AES-GCM — o NAS nao suporta
            // CBC, mas o motor padrao (libsodium) falha em arquivos grandes sobre VPN.
            // setCryptoEngine e estatico e afeta todas as instancias na requisicao.
            if (method_exists('\phpseclib3\Net\SSH2', 'setCryptoEngine')) {
                \phpseclib3\Net\SSH2::setCryptoEngine(
                    \phpseclib3\Crypt\Common\SymmetricKey::ENGINE_OPENSSL_GCM
                );
            }
            $sftp = new \phpseclib3\Net\SFTP($host, $port);
            $sftp->setTimeout(90);
            // Sem restricao de algoritmos — deixa o servidor negociar GCM normalmente
            if (!$sftp->login($sftpUser, $sftpPass)) {
                vpl_log('phpseclib3: falha no login');
            } else {
                vpl_log('phpseclib3: login OK, buscando: ' . $path);
                $result = $sftp->get($path);
                if ($result !== false && strlen((string) $result) > 0) {
                    $data = $result;
                    vpl_log('phpseclib3: sucesso: ' . $path . ' bytes=' . strlen($data));
                } else {
                    vpl_log('phpseclib3: get retornou falso/vazio para: ' . $path);
                }
            }
        } catch (\Exception $e) {
            vpl_log('phpseclib3: excecao: ' . $e->getMessage());
        }
    }

    if ($data === null) {
        vpl_log('arquivo nao encontrado para id=' . $idlog . ' caminho=' . $path);
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Arquivo PDF nao encontrado no servidor.';
        exit;
    }

    if (strpos(substr((string) $data, 0, 1024), '%PDF-') === false) {
        vpl_log('conteudo invalido (nao e PDF) para: ' . $path);
        http_response_code(502);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Resposta invalida recebida do servidor de arquivos.';
        exit;
    }

    while (ob_get_level() > 0) @ob_end_clean();
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    header($download ? 'Cache-Control: private, no-store' : 'Cache-Control: private, max-age=300');
    header('Content-Length: ' . strlen($data));
    echo $data;
    vpl_log('PDF enviado: ' . $path . ' bytes=' . strlen($data));
    exit;
}

// -----------------------------------------------------------------------------
// Pagina HTML com iframe para visualizacao
// -----------------------------------------------------------------------------
$nome         = $row['nome_arquivo'] ?: ('ArquivoLog #' . $idlog);
$safeNome     = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
$self         = basename($_SERVER['PHP_SELF']);
$iframeSrc    = $self . '?idlog=' . urlencode((string) $idlog) . '&raw=1';
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
    <!-- Overlay de carregamento do PDF (exibe enquanto o iframe carrega) -->
    <div id="vpl-pdf-loading" class="vpl-pdf-loading" aria-hidden="true">
        <div class="vpl-pdf-loading-box">
            <div class="vpl-pdf-loading-title">PDF sendo carregado…</div>
            <div class="vpl-pdf-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                <div class="vpl-pdf-progress-fill" style="width:0%"></div>
            </div>
        </div>
    </div>
</body>

</html>

<style>
/* Styles mínimos para o overlay de carregamento (isolados para evitar conflitos) */
.vpl-pdf-loading{position:fixed;left:0;top:52px;right:0;bottom:0;display:flex;align-items:center;justify-content:center;background:rgba(11,18,32,0.6);z-index:9999}
.vpl-pdf-loading[aria-hidden="true"]{display:none}
.vpl-pdf-loading-box{background:#0b1220;border:1px solid rgba(255,255,255,0.06);padding:18px 22px;border-radius:10px;color:#e6eef8;min-width:280px;max-width:480px;box-shadow:0 6px 18px rgba(0,0,0,0.45);text-align:left}
.vpl-pdf-loading-title{font-size:14px;margin-bottom:10px}
.vpl-pdf-progress{height:8px;background:#1f2937;border-radius:6px;overflow:hidden}
.vpl-pdf-progress-fill{height:100%;background:linear-gradient(90deg,#2563eb,#06b6d4);width:0;transition:width .3s ease}
</style>

<script>
(function(){
    var iframe = document.querySelector('iframe');
    var overlay = document.getElementById('vpl-pdf-loading');
    var fill = overlay && overlay.querySelector('.vpl-pdf-progress-fill');
    if (!iframe || !overlay || !fill) return;

    var progress = 0;
    overlay.setAttribute('aria-hidden','false');

    // anima progress até 90% enquanto carrega
    var ticker = setInterval(function(){
        if (progress < 90) {
            progress += Math.ceil(Math.random()*6);
            if (progress > 90) progress = 90;
            fill.style.width = progress + '%';
        }
    }, 300);

    // quando iframe terminar de carregar, completa e remove overlay
    iframe.addEventListener('load', function(){
        clearInterval(ticker);
        fill.style.width = '100%';
        setTimeout(function(){
            overlay.setAttribute('aria-hidden','true');
        }, 350);
    });

    // fallback: timeout para remover overlay caso algo falhe (20s)
    setTimeout(function(){
        if (overlay.getAttribute('aria-hidden') === 'false') {
            clearInterval(ticker);
            overlay.setAttribute('aria-hidden','true');
        }
    }, 20000);
})();
</script>
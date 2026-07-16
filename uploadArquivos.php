<?php
header('Content-Type: application/json');

use phpseclib3\Net\SFTP;

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$__debug = (($_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '') === '1');

function get_debug_token(): string
{
    return (string)(getenv('APP_DEBUG_TOKEN') ?: ($_SERVER['APP_DEBUG_TOKEN'] ?? ''));
}

function request_debug_token(): string
{
    return (string)(
        $_SERVER['HTTP_X_DEBUG_TOKEN']
        ?? $_POST['debug_token']
        ?? $_GET['debug_token']
        ?? ''
    );
}

function is_debug_enabled(): bool
{
    global $__debug;
    if ($__debug) {
        return true;
    }
    $expected = get_debug_token();
    if ($expected === '') {
        return false;
    }
    return hash_equals($expected, request_debug_token());
}

function gen_error_id(): string
{
    try {
        return bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        return (string)time();
    }
}

function log_server_error(string $errorId, array $context): void
{
    $payload = [
        'error_id' => $errorId,
        'time' => date('c'),
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'context' => $context,
    ];

    // 1) Vai pro log do PHP/Apache
    error_log('[uploadArquivos.php] ' . json_encode($payload, JSON_UNESCAPED_UNICODE));

    // 2) Se der, registra também em arquivo local (facilita em hospedagens sem acesso ao error_log)
    $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'uploadArquivos_error.log';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    @file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function json_error(string $message, int $statusCode = 500, array $context = [], ?string $errorId = null): void
{
    $eid = $errorId ?: gen_error_id();
    log_server_error($eid, $context);

    $payload = [
        'error' => $message,
        'error_id' => $eid,
    ];
    if (is_debug_enabled() && $context) {
        $payload['debug'] = $context;
    }
    json_response($payload, $statusCode);
}

set_exception_handler(function (Throwable $e) {
    $eid = gen_error_id();
    json_error('Erro interno no servidor.', 500, [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], $eid);
});

register_shutdown_function(function () {
    $last = error_get_last();
    if (!$last) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($last['type'], $fatalTypes, true)) {
        return;
    }

    $eid = gen_error_id();
    json_error('Erro interno no servidor.', 500, [
        'type' => $last['type'],
        'message' => $last['message'] ?? '',
        'file' => $last['file'] ?? '',
        'line' => $last['line'] ?? '',
    ], $eid);
});

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/config/secure_env.php';
require_once __DIR__ . '/FlowReview/approval_media_schema.php';
require_once __DIR__ . '/FlowReview/ws_notify.php';
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
fr_approval_media_ensure_schema($conn);

$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($vendorAutoload)) {
    json_error('Dependência ausente: vendor/autoload.php não encontrada no servidor.', 500);
}
require_once $vendorAutoload;

// ---------- Dados SFTP VPS ----------
try {
    $ftpCfg = improov_sftp_config('IMPROOV_VPS_SFTP');
    $ftpBaseEnv = improov_env('IMPROOV_VPS_SFTP_REMOTE_PATH');
} catch (RuntimeException $e) {
    json_error('Configuração SFTP VPS ausente no ambiente.', 500);
}

$ftp_host = $ftpCfg['host'];
$ftp_port = $ftpCfg['port'];
$ftp_user = $ftpCfg['user'];
$ftp_pass = $ftpCfg['pass'];
$ftp_base = $ftpBaseEnv;

$ftp_host = trim((string)$ftp_host);
$ftp_user = trim((string)$ftp_user);
$ftp_pass = trim((string)$ftp_pass);
$ftp_base = trim((string)$ftp_base);

$missingFtpEnv = [];
if ($ftp_host === '') {
    $missingFtpEnv[] = 'IMPROOV_VPS_SFTP_HOST';
}
if ($ftp_user === '') {
    $missingFtpEnv[] = 'IMPROOV_VPS_SFTP_USER';
}
if ($ftp_pass === '') {
    $missingFtpEnv[] = 'IMPROOV_VPS_SFTP_PASS';
}
if ($ftp_base === '') {
    $missingFtpEnv[] = 'IMPROOV_VPS_SFTP_REMOTE_PATH';
}

if (!empty($missingFtpEnv)) {
    json_error('Configuração SFTP VPS incompleta no ambiente.', 500, [
        'missing_env' => $missingFtpEnv,
        'sftp_port' => $ftp_port,
    ]);
}

$ftp_base = rtrim(str_replace('\\', '/', $ftp_base), '/');
if (substr($ftp_base, -8) !== '/uploads') {
    $ftp_base .= '/uploads';
}
$ftp_base .= '/';

// ---------- Funções utilitárias ----------
function removerTodosAcentos($str)
{
    return preg_replace(
        ['/[áàãâä]/ui', '/[éèêë]/ui', '/[íìîï]/ui', '/[óòõôö]/ui', '/[úùûü]/ui', '/[ç]/ui'],
        ['a', 'e', 'i', 'o', 'u', 'c'],
        $str
    );
}

function sanitizeFilename($str)
{
    $str = removerTodosAcentos($str);
    $str = preg_replace('/[\/\\:*?"<>|]/', '', $str);
    $str = preg_replace('/\s+/', '_', $str);
    return $str;
}

function getProcesso($nomeFuncao)
{
    $map = [
        'Pré-Finalização' => 'PRE',
        'Pós-Produção'    => 'POS',
    ];
    if (isset($map[$nomeFuncao])) return $map[$nomeFuncao];
    // Depois de remover acentos, o texto fica ASCII, então não depende de mbstring.
    $semAcento = strtoupper(removerTodosAcentos($nomeFuncao));
    return substr($semAcento, 0, 3);
}

function uploadMediaMimeType(string $tmpName): string
{
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($tmpName);
        if (is_string($mime) && $mime !== '') {
            return $mime;
        }
    }
    return 'application/octet-stream';
}

function uploadMediaType(string $filename, string $mimeType): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (strpos($mimeType, 'video/') === 0 || in_array($ext, ['mp4', 'webm', 'mov', 'm4v'], true)) {
        return 'video';
    }
    return 'imagem';
}

function uploadResolveFfmpegCommand(?string &$debug = null): ?string
{
    $ffmpeg = trim((string)improov_env('FFMPEG_PATH', 'ffmpeg'), " \t\n\r\0\x0B\"'");
    $candidates = [];
    if ($ffmpeg !== '') {
        $candidates[] = $ffmpeg;
    }

    $whereCmd = stripos(PHP_OS_FAMILY, 'Windows') !== false ? 'where ffmpeg 2>&1' : 'command -v ffmpeg 2>&1';
    $whereOut = [];
    $whereCode = 1;
    @exec($whereCmd, $whereOut, $whereCode);
    if ($whereCode === 0 && !empty($whereOut[0])) {
        $candidates[] = trim((string)$whereOut[0]);
    }

    $candidates = array_merge($candidates, [
        'C:\\ffmpeg\\bin\\ffmpeg.exe',
        'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
        'C:\\Program Files (x86)\\ffmpeg\\bin\\ffmpeg.exe',
        'C:\\ProgramData\\chocolatey\\bin\\ffmpeg.exe',
        getenv('USERPROFILE') ? rtrim((string)getenv('USERPROFILE'), '\\/') . '\\scoop\\shims\\ffmpeg.exe' : '',
        '/usr/bin/ffmpeg',
        '/usr/local/bin/ffmpeg',
        '/bin/ffmpeg',
    ]);

    $seen = [];
    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate === '' || isset($seen[strtolower($candidate)])) {
            continue;
        }
        $seen[strtolower($candidate)] = true;

        if ($candidate === 'ffmpeg') {
            $versionOut = [];
            $versionCode = 1;
            @exec('ffmpeg -version 2>&1', $versionOut, $versionCode);
            if ($versionCode === 0) {
                return 'ffmpeg';
            }
            continue;
        }

        if (is_file($candidate)) {
            return $candidate;
        }
    }

    $debug = 'ffmpeg não encontrado. Configure FFMPEG_PATH com o caminho completo do executável.';
    return null;
}

function uploadVideoPosterPath(string $videoLocalPath, string $posterBaseName, ?string &$debug = null): ?string
{
    $ffmpeg = uploadResolveFfmpegCommand($debug);
    if (!$ffmpeg) {
        error_log('[uploadArquivos.php] poster video: ' . ($debug ?: 'ffmpeg indisponível'));
        return null;
    }

    $posterLocal = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $posterBaseName . '.jpg';
    if (is_file($posterLocal)) {
        @unlink($posterLocal);
    }

    $ffmpegCmd = preg_match('/[\\\\\/\s]/', $ffmpeg) ? escapeshellarg($ffmpeg) : $ffmpeg;
    $cmd = $ffmpegCmd
        . ' -y -ss 00:00:01 -i ' . escapeshellarg($videoLocalPath)
        . ' -frames:v 1 -q:v 3 ' . escapeshellarg($posterLocal) . ' 2>&1';
    $output = [];
    $code = 1;
    @exec($cmd, $output, $code);

    if ($code !== 0 || !is_file($posterLocal) || filesize($posterLocal) <= 0) {
        $debug = 'ffmpeg falhou ao gerar poster: ' . trim(implode(' ', array_slice($output, -6)));
        error_log('[uploadArquivos.php] poster video: ' . $debug);
        if (is_file($posterLocal)) {
            @unlink($posterLocal);
        }
        return null;
    }

    $debug = 'poster gerado com ' . $ffmpeg;
    return $posterLocal;
}

function ensureSftpDirRecursive(SFTP $sftp, string $path): bool
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '' || $path === '/') {
        return true;
    }

    $parts = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    $current = '';
    foreach ($parts as $part) {
        $current .= '/' . $part;
        if (!$sftp->is_dir($current)) {
            if (!$sftp->mkdir($current)) {
                return false;
            }
        }
    }
    return true;
}

function enviarArquivoSFTP(SFTP $sftp, string $arquivoLocal, string $arquivoRemoto): array
{
    $remoteDir = dirname($arquivoRemoto);
    if (!ensureSftpDirRecursive($sftp, $remoteDir)) {
        return [false, "⚠ Erro ao preparar diretório remoto no SFTP: $remoteDir"];
    }

    if ($sftp->put($arquivoRemoto, $arquivoLocal, SFTP::SOURCE_LOCAL_FILE)) {
        return [true, $arquivoRemoto];
    }

    return [false, "⚠ Erro ao enviar via SFTP: $arquivoRemoto"];
}

// ---------- Parâmetros ----------
// Aceita dataIdFuncoes como int simples OU JSON array [id]
$_dataIdFuncoesRaw = $_POST['dataIdFuncoes'] ?? 0;
if (is_string($_dataIdFuncoesRaw) && strpos($_dataIdFuncoesRaw, '[') !== false) {
    $_dataIdFuncoesArr = json_decode($_dataIdFuncoesRaw, true);
    $dataIdFuncoes = (int)(is_array($_dataIdFuncoesArr) ? ($_dataIdFuncoesArr[0] ?? 0) : 0);
} else {
    $dataIdFuncoes = (int)$_dataIdFuncoesRaw;
}

$numeroImagem  = preg_replace('/\D/', '', $_POST['numeroImagem'] ?? '');
$nomenclatura  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['nomenclatura'] ?? '');
$nomeFuncao    = $_POST['nome_funcao'] ?? '';
$nome_imagem   = $_POST['nome_imagem'] ?? '';
$idimagem      = (int)($_POST['idimagem'] ?? 0);
$tipoTarefaRaw = trim((string)($_POST['tipo_tarefa'] ?? 'imagem'));
$tipoTarefa    = strtolower(removerTodosAcentos($tipoTarefaRaw));
$funcaoAnimacaoId = (int)($_POST['funcao_animacao_id'] ?? 0);
$nomeFuncaoSemAcento = strtolower(removerTodosAcentos((string)$nomeFuncao));
$isAnimacaoUpload = $tipoTarefa === 'animacao'
    || strpos($nomeFuncaoSemAcento, 'animacao') !== false
    || stripos((string)$nomeFuncao, 'Anima') !== false;
if ($isAnimacaoUpload) {
    if ($funcaoAnimacaoId <= 0) {
        $funcaoAnimacaoId = $dataIdFuncoes;
    }
    if ($funcaoAnimacaoId > 0) {
        $dataIdFuncoes = $funcaoAnimacaoId;
    }
}
// Se informado, usa o índice de envio forçado (adiciona ângulos ao envio atual)
$indice_envio_forcado = isset($_POST['indice_envio_forcado']) ? (int)$_POST['indice_envio_forcado'] : 0;

if (!$dataIdFuncoes || !$nomeFuncao) {
    json_error('Parâmetros insuficientes', 400);
}

// Se nomenclatura ou numeroImagem não foram enviados, tenta buscar no banco via idimagem
if ($idimagem > 0 && ($nomenclatura === '' || $numeroImagem === '')) {
    $stLookup = $conn->prepare(
        "SELECT i.imagem_nome, o.nomenclatura
         FROM imagens_cliente_obra i
         LEFT JOIN obra o ON o.idobra = i.obra_id
         WHERE i.idimagens_cliente_obra = ?
         LIMIT 1"
    );
    if ($stLookup) {
        $stLookup->bind_param('i', $idimagem);
        $stLookup->execute();
        $rowLookup = $stLookup->get_result()->fetch_assoc();
        $stLookup->close();
        if ($rowLookup) {
            if ($nomenclatura === '' && !empty($rowLookup['nomenclatura'])) {
                $nomenclatura = preg_replace('/[^a-zA-Z0-9_\-]/', '', $rowLookup['nomenclatura']);
            }
            if ($numeroImagem === '' && !empty($rowLookup['imagem_nome'])) {
                $numeroImagem = preg_replace('/\D/', '', explode('.', $rowLookup['imagem_nome'])[0] ?? '');
            }
            if ($nome_imagem === '' && !empty($rowLookup['imagem_nome'])) {
                $nome_imagem = $rowLookup['imagem_nome'];
            }
        }
    }
}

$idFuncaoImagem = $dataIdFuncoes;
$processo = getProcesso($nomeFuncao);

// ---------- Índice de envio ----------
if ($indice_envio_forcado > 0) {
    // Mantém o índice atual (adicionar ângulos ao mesmo envio)
    $indice_envio = $indice_envio_forcado;
} else {
    if ($isAnimacaoUpload) {
        $stmt = $conn->prepare("SELECT MAX(indice_envio) AS max_indice FROM historico_aprovacoes_imagens WHERE funcao_animacao_id = ?");
    } else {
        $stmt = $conn->prepare("SELECT MAX(indice_envio) AS max_indice FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ?");
    }
    $stmt->bind_param("i", $idFuncaoImagem);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $indice_envio = ($result['max_indice'] ?? 0) + 1;
    $stmt->close();
}

// ---------- Status nome ----------
$stmt2 = $conn->prepare("SELECT s.nome_status FROM status_imagem s
                         JOIN imagens_cliente_obra i ON s.idstatus = i.status_id
                         WHERE i.idimagens_cliente_obra = ?");
$stmt2->bind_param("i", $idimagem);
$stmt2->execute();
$result2 = $stmt2->get_result()->fetch_assoc();
$status_nome = $result2 ? $result2['nome_status'] : null; // evita erro se não encontrar
$status_nome_sanitizado = sanitizeFilename((string)($status_nome ?? ''));
$stmt2->close();

// ---------- Status nome ----------
if ($isAnimacaoUpload) {
    $stmt2 = $conn->prepare("SELECT fa.status AS funcao_status, fa.prazo AS funcao_prazo FROM funcao_animacao fa
                             WHERE fa.id = ?");
} else {
    $stmt2 = $conn->prepare("SELECT fi.status AS funcao_status, fi.prazo AS funcao_prazo FROM funcao_imagem fi
                             WHERE fi.idfuncao_imagem = ?");
}
$stmt2->bind_param("i", $idFuncaoImagem);
$stmt2->execute();
$result2 = $stmt2->get_result()->fetch_assoc();
$funcao_status = $result2 ? $result2['funcao_status'] : null; // evita erro se não encontrar
$funcao_prazo  = $result2 ? $result2['funcao_prazo']  : null; // prazo planejado no momento (SLA)
$funcao_status_sanitizado = sanitizeFilename((string)($funcao_status ?? ''));
$stmt2->close();
if (!$result2) {
    json_error(
        $isAnimacaoUpload ? 'Função de animação não encontrada.' : 'Função de imagem não encontrada.',
        404,
        [
            'tipo_tarefa' => $isAnimacaoUpload ? 'animacao' : 'imagem',
            'funcao_id' => $idFuncaoImagem,
            'nome_funcao' => $nomeFuncao,
        ]
    );
}

// ---------- Conexão SFTP ----------
try {
    $conn_ftp = new SFTP($ftp_host, (int)$ftp_port, 10);
} catch (Throwable $e) {
    json_error('Não foi possível conectar ao servidor SFTP.', 500, ['detail' => $e->getMessage()]);
}
if (!$conn_ftp->login($ftp_user, $ftp_pass)) {
    json_error('Falha na autenticação SFTP.', 500);
}

// ---------- Upload das imagens ----------
if (!isset($_FILES['imagens'])) {
    json_error('Nenhuma imagem recebida', 400);
}

$imagens = $_FILES['imagens'];
$totalImagens = count($imagens['name']);
$imagensEnviadas = [];
$historicoIdsEnviados = [];
$postersEnviados = [];
$nomeImagemSanitizado = sanitizeFilename($nome_imagem);

// Quando adicionando ao índice existente, começa a numeração após as imagens já presentes
$previaOffset = 0;
if ($indice_envio_forcado > 0) {
    if ($isAnimacaoUpload) {
        $stCount = $conn->prepare("SELECT COUNT(*) AS qtd FROM historico_aprovacoes_imagens WHERE funcao_animacao_id = ? AND indice_envio = ?");
    } else {
        $stCount = $conn->prepare("SELECT COUNT(*) AS qtd FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? AND indice_envio = ?");
    }
    if ($stCount) {
        $stCount->bind_param("ii", $idFuncaoImagem, $indice_envio);
        $stCount->execute();
        $rowCount = $stCount->get_result()->fetch_assoc();
        $previaOffset = (int)($rowCount['qtd'] ?? 0);
        $stCount->close();
    }
}


$sqlTipoImagem = "SELECT tipo_imagem FROM imagens_cliente_obra WHERE idimagens_cliente_obra = $idimagem";
$resultTipo = $conn->query($sqlTipoImagem);
if ($resultTipo === false) {
    json_error('Erro ao consultar tipo_imagem no banco.', 500, ['mysql_error' => $conn->error]);
}
$tipoImagem = $resultTipo->fetch_assoc()['tipo_imagem'] ?? '';

// ---------- Detecção de bypass (envia direto ao NAS sem nova aprovação) ----------
// Condição A: Pós-Produção com status anterior "Aprovado com ajustes"
// Condição B: Finalização de planta humanizada
$nomeFuncaoKeyGlobal = strtolower(removerTodosAcentos($nomeFuncao));
$tipoImagemKeyGlobal = strtolower(removerTodosAcentos($tipoImagem));
$funcao_status_norm  = strtolower(removerTodosAcentos((string)($funcao_status ?? '')));

// Impede persistência parcial: a regra de comentários pendentes precisa ser
// avaliada antes de enviar para o SFTP e inserir o histórico da nova prévia.
if (!$isAnimacaoUpload && $funcao_status_norm === 'ajuste') {
    $chkCol = $conn->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comentarios_imagem' AND COLUMN_NAME = 'concluido' LIMIT 1"
    );
    $chkCol->execute();
    $colExists = ($chkCol->get_result()->num_rows > 0);
    $chkCol->close();

    if ($colExists) {
        $stmtLastHai = $conn->prepare(
            "SELECT id FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmtLastHai->bind_param('i', $idFuncaoImagem);
        $stmtLastHai->execute();
        $rowLastHai = $stmtLastHai->get_result()->fetch_assoc();
        $stmtLastHai->close();

        if ($rowLastHai) {
            $lastApImagemId = (int) $rowLastHai['id'];
            $stmtPend = $conn->prepare(
                "SELECT COUNT(*) AS total, SUM(concluido) AS concluidos FROM comentarios_imagem WHERE ap_imagem_id = ?"
            );
            $stmtPend->bind_param('i', $lastApImagemId);
            $stmtPend->execute();
            $rowPend = $stmtPend->get_result()->fetch_assoc();
            $stmtPend->close();

            $totalComents = (int) ($rowPend['total'] ?? 0);
            $concluidosCo = (int) ($rowPend['concluidos'] ?? 0);
            if ($totalComents > 0 && $concluidosCo < $totalComents) {
                $pendentes = $totalComents - $concluidosCo;
                json_error(
                    "Existem {$pendentes} comentÃ¡rio(s) nÃ£o concluÃ­do(s). Conclua todos os ajustes no Flow Review antes de enviar uma nova versÃ£o.",
                    422,
                    ['pendentes' => $pendentes, 'total' => $totalComents, 'concluidos' => $concluidosCo]
                );
            }
        }
    }
}

$isNasDirectBypass = !$isAnimacaoUpload && (
    ($nomeFuncaoKeyGlobal === 'pos-producao' && $funcao_status_norm === 'aprovado com ajustes')
    || ($nomeFuncaoKeyGlobal === 'finalizacao' && $tipoImagemKeyGlobal === 'planta humanizada' && $funcao_status_norm === 'aprovado com ajustes')
);
$arquivosParaNAS = [];

for ($i = 0; $i < $totalImagens; $i++) {
    $numeroPrevia = $previaOffset + $i + 1;

    $imagemAtual = [
        'name'     => $imagens['name'][$i],
        'tmp_name' => $imagens['tmp_name'][$i],
        'error'    => $imagens['error'][$i],
        'size'     => $imagens['size'][$i] ?? null,
    ];

    if ($imagemAtual['error'] !== UPLOAD_ERR_OK) {
        json_error("Erro no upload temporário da imagem {$imagemAtual['name']}", 400);
    }

    // Nome final do arquivo (sem extensão)
    $nomeFuncaoKey = strtolower(removerTodosAcentos($nomeFuncao));
    $tipoImagemKey = strtolower(removerTodosAcentos($tipoImagem));

    if ($nomeFuncaoKey === 'pos-producao' || $nomeFuncaoKey === 'alteracao' || $tipoImagemKey === 'planta humanizada') {
        $nomeFinalSemExt = "{$nomeImagemSanitizado}_{$status_nome_sanitizado}_{$indice_envio}_{$numeroPrevia}";
    } else {
        $nomeFinalSemExt = "{$numeroImagem}.{$nomenclatura}-{$processo}-{$indice_envio}-{$numeroPrevia}";
    }

    $extensao = pathinfo($imagemAtual['name'], PATHINFO_EXTENSION);
    $mimeType = uploadMediaMimeType($imagemAtual['tmp_name']);
    $mediaTipo = uploadMediaType($imagemAtual['name'], $mimeType);
    $tamanhoArquivo = isset($imagemAtual['size']) ? (int)$imagemAtual['size'] : (int)@filesize($imagemAtual['tmp_name']);
    $arquivoRemoto = $ftp_base . $nomeFinalSemExt . "." . $extensao;

    list($ok, $msg) = enviarArquivoSFTP($conn_ftp, $imagemAtual['tmp_name'], $arquivoRemoto);
    if (!$ok) {
        json_error((string)$msg, 500);
    }

    $caminhoBanco = 'uploads/' . $nomeFinalSemExt . "." . $extensao;
    $posterPathBanco = null;
    if ($mediaTipo === 'video') {
        $posterDebug = null;
        $posterLocal = uploadVideoPosterPath($imagemAtual['tmp_name'], $nomeFinalSemExt . '_poster', $posterDebug);
        if ($posterLocal) {
            $posterRemoto = $ftp_base . $nomeFinalSemExt . '_poster.jpg';
            [$okPoster, $msgPoster] = enviarArquivoSFTP($conn_ftp, $posterLocal, $posterRemoto);
            @unlink($posterLocal);
            if ($okPoster) {
                $posterPathBanco = 'uploads/' . $nomeFinalSemExt . '_poster.jpg';
                $postersEnviados[] = [
                    'video' => $caminhoBanco,
                    'poster_path' => $posterPathBanco,
                    'status' => 'ok',
                ];
            } else {
                $postersEnviados[] = [
                    'video' => $caminhoBanco,
                    'poster_path' => null,
                    'status' => 'sftp_error',
                    'detail' => (string)$msgPoster,
                ];
            }
        } else {
            $postersEnviados[] = [
                'video' => $caminhoBanco,
                'poster_path' => null,
                'status' => 'not_generated',
                'detail' => $posterDebug ?: 'poster não gerado',
            ];
        }
    }


    // Salva no banco o caminho remoto
    if ($isAnimacaoUpload) {
        $stmt = $conn->prepare("INSERT INTO historico_aprovacoes_imagens
            (funcao_imagem_id, funcao_animacao_id, imagem, indice_envio, nome_arquivo, media_tipo, mime_type, tamanho, poster_path)
            VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
    } else {
        $stmt = $conn->prepare("INSERT INTO historico_aprovacoes_imagens
            (funcao_imagem_id, funcao_animacao_id, imagem, indice_envio, nome_arquivo, media_tipo, mime_type, tamanho, poster_path)
            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)");
    }
    $stmt->bind_param("isisssis", $idFuncaoImagem, $caminhoBanco, $indice_envio, $nomeFinalSemExt, $mediaTipo, $mimeType, $tamanhoArquivo, $posterPathBanco);
    if ($stmt->execute()) {
        $imagensEnviadas[] = $caminhoBanco;
        $historicoIdsEnviados[] = (int) $conn->insert_id;
        if ($isNasDirectBypass) {
            $arquivosParaNAS[] = [
                'tmp_name'   => $imagemAtual['tmp_name'],
                'extensao'   => $extensao,
                'nome_final' => $nomeFinalSemExt,
            ];
        }
    } else {
        json_error('Erro ao salvar no banco: ' . $stmt->error, 500);
    }
    $stmt->close();
}

// Fecha conexão SFTP
unset($conn_ftp);

if ($isNasDirectBypass) {
    // ---------- Bypass: status → Aprovado + envia direto ao NAS ----------
    $stmt = $conn->prepare(
        "UPDATE funcao_imagem
         SET prazo = NOW(), status = 'Aprovado', requires_file_upload = 0, file_uploaded_at = NOW()
         WHERE idfuncao_imagem = ?"
    );
    $stmt->bind_param("i", $idFuncaoImagem);
    if (!$stmt->execute()) {
        json_error('Erro ao atualizar status para Aprovado: ' . $stmt->error, 500);
    }
    $stmt->close();

    // ---------- Registra evento de entrega no histórico SLA ----------
    {
        $_slaColabId   = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : null;
        $_slaUsuarioId = isset($_SESSION['idusuario'])     ? (int)$_SESSION['idusuario']     : null;
        $_slaOrigem    = 'upload_previa';
        $_slaStatusAnt = $funcao_status;
        $_slaStatusNov = 'Aprovado';
        $stmtSlaHist = $conn->prepare(
            "INSERT INTO funcao_imagem_prazo_historico
                (funcao_imagem_id, prazo_anterior, prazo_novo,
                 alterado_por_colaborador_id, alterado_por_usuario_id,
                 origem, motivo, status_anterior, status_novo)
             VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)"
        );
        if ($stmtSlaHist) {
            $_slaHoje = date('Y-m-d');
            $stmtSlaHist->bind_param('issiisss',
                $idFuncaoImagem, $funcao_prazo, $_slaHoje,
                $_slaColabId, $_slaUsuarioId, $_slaOrigem,
                $_slaStatusAnt, $_slaStatusNov);
            $stmtSlaHist->execute();
            $stmtSlaHist->close();
        }
    }

    // Busca nomenclatura original para montar o caminho no NAS
    $nomenclaturaRaw = $nomenclatura;
    $stmtNasNomen = $conn->prepare(
        "SELECT o.nomenclatura
         FROM funcao_imagem fi
         JOIN imagens_cliente_obra ic ON fi.imagem_id = ic.idimagens_cliente_obra
         JOIN obra o ON ic.obra_id = o.idobra
         WHERE fi.idfuncao_imagem = ?
         LIMIT 1"
    );
    if ($stmtNasNomen) {
        $stmtNasNomen->bind_param("i", $idFuncaoImagem);
        $stmtNasNomen->execute();
        $rowNasNomen = $stmtNasNomen->get_result()->fetch_assoc();
        $stmtNasNomen->close();
        if ($rowNasNomen && !empty($rowNasNomen['nomenclatura'])) {
            $nomenclaturaRaw = $rowNasNomen['nomenclatura'];
        }
    }

    // Envia cada arquivo ao NAS
    $nasLogs = [];
    $nasSent = false;
    try {
        $nasCfg   = improov_sftp_config(); // NAS — prefixo padrão IMPROOV_SFTP
        $nasBases = ['/mnt/clientes/2024', '/mnt/clientes/2025', '/mnt/clientes/2026'];

        foreach ($arquivosParaNAS as $nasArq) {
            $nomeOriginal = $nasArq['nome_final'] . '.' . $nasArq['extensao'];
            // Remove sufixos numéricos de índice/preview: ex. Nome_EF_1_1.jpg → Nome_EF.jpg
            $nomeEnvio = preg_replace('/(_\d+)+(\.([^.]+))$/', '.$3', $nomeOriginal);
            if ($nomeEnvio === $nomeOriginal) {
                $nomeEnvio = $nomeOriginal;
            }
            // Extrai código de revisão: _EF, _P00, _POS etc.
            preg_match_all('/_[A-Z0-9]{2,3}/i', $nomeEnvio, $matchesRev);
            $revisao = !empty($matchesRev[0])
                ? strtoupper(str_replace('_', '', end($matchesRev[0])))
                : 'P00';

            $arquivoEnviado = false;
            foreach ($nasBases as $base) {
                try {
                    $nas = new SFTP($nasCfg['host'], (int)$nasCfg['port']);
                    if (!$nas->login($nasCfg['user'], $nasCfg['pass'])) {
                        $nasLogs[] = "Falha auth NAS ($base).";
                        continue;
                    }
                    $finalizacaoDir = "$base/$nomenclaturaRaw/04.Finalizacao";
                    if (!$nas->is_dir($finalizacaoDir)) {
                        $nasLogs[] = "Diretório não encontrado: $finalizacaoDir";
                        continue;
                    }
                    $revisaoDir = "$finalizacaoDir/$revisao";
                    if (!$nas->is_dir($revisaoDir)) {
                        if (!$nas->mkdir($revisaoDir, -1, true)) {
                            $nasLogs[] = "Falha ao criar $revisaoDir.";
                            continue;
                        }
                    }
                    $remotePath = "$revisaoDir/$nomeEnvio";
                    if ($nas->put($remotePath, $nasArq['tmp_name'], SFTP::SOURCE_LOCAL_FILE)) {
                        $nasLogs[]      = "Enviado ao NAS: $remotePath";
                        $arquivoEnviado = true;
                        $nasSent        = true;
                        break;
                    } else {
                        $nasLogs[] = "Falha ao enviar $nomeEnvio para $base.";
                    }
                } catch (Throwable $e) {
                    $nasLogs[] = "Erro SFTP NAS ($base): " . $e->getMessage();
                }
            }
            if (!$arquivoEnviado) {
                $nasLogs[] = "Arquivo não enviado ao NAS: $nomeOriginal";
            }
        }
    } catch (RuntimeException $e) {
        $nasLogs[] = "Config NAS ausente: " . $e->getMessage();
    }

    // ---------- Atualiza entregas_itens para 'Entrega pendente' (bypass NAS) ----------
    $bypassEntregaLogs = [];
    if ($idimagem > 0) {
        $stmtBypassImg = $conn->prepare("SELECT status_id, obra_id FROM imagens_cliente_obra WHERE idimagens_cliente_obra = ?");
        $stmtBypassImg->bind_param("i", $idimagem);
        $stmtBypassImg->execute();
        $stmtBypassImg->bind_result($bypass_status_id, $bypass_obra_id);
        if ($stmtBypassImg->fetch()) {
            $stmtBypassImg->close();

            // Nome do status da imagem (ex.: P00, R00, EF…)
            $bypass_status_nome = null;
            $stmtBypassSt = $conn->prepare("SELECT nome_status FROM status_imagem WHERE idstatus = ? LIMIT 1");
            if ($stmtBypassSt) {
                $stmtBypassSt->bind_param("i", $bypass_status_id);
                $stmtBypassSt->execute();
                $stmtBypassSt->bind_result($bypass_status_nome);
                $stmtBypassSt->fetch();
                $stmtBypassSt->close();
            }

            // Entrega correspondente ao status + obra
            $stmtBypassEnt = $conn->prepare("SELECT id FROM entregas WHERE status_id = ? AND obra_id = ? ORDER BY id DESC LIMIT 1");
            $stmtBypassEnt->bind_param("ii", $bypass_status_id, $bypass_obra_id);
            $stmtBypassEnt->execute();
            $stmtBypassEnt->bind_result($bypass_entrega_id);
            if ($stmtBypassEnt->fetch()) {
                $stmtBypassEnt->close();

                // Item da entrega para essa imagem
                $stmtBypassItem = $conn->prepare("SELECT id FROM entregas_itens WHERE entrega_id = ? AND imagem_id = ? LIMIT 1");
                $stmtBypassItem->bind_param("ii", $bypass_entrega_id, $idimagem);
                $stmtBypassItem->execute();
                $stmtBypassItem->bind_result($bypass_entrega_item_id);
                if ($stmtBypassItem->fetch()) {
                    $stmtBypassItem->close();

                    $isFinalizacaoBypass = (strtolower(removerTodosAcentos($nomeFuncao)) === 'finalizacao');
                    $isP00Bypass = ($bypass_status_nome === 'P00');

                    if ($isFinalizacaoBypass && $isP00Bypass) {
                        // Para P00: garante coluna e vincula entrega_item_id aos ângulos existentes
                        $colExists = false;
                        if ($chkCol = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'angulos_imagens' AND COLUMN_NAME = 'entrega_item_id'")) {
                            $chkCol->execute();
                            $resChkCol = $chkCol->get_result();
                            $colExists = ($resChkCol && $resChkCol->num_rows > 0);
                            $chkCol->close();
                        }
                        if (!$colExists) {
                            @$conn->query("ALTER TABLE angulos_imagens ADD COLUMN entrega_item_id INT NULL AFTER historico_id");
                            @$conn->query("CREATE INDEX idx_angulos_entrega_item ON angulos_imagens(entrega_item_id)");
                        }
                        if ($upAiBypass = $conn->prepare("UPDATE angulos_imagens ai JOIN historico_aprovacoes_imagens hi ON hi.id = ai.historico_id SET ai.entrega_item_id = ? WHERE ai.imagem_id = ? AND hi.funcao_imagem_id = ?")) {
                            $upAiBypass->bind_param('iii', $bypass_entrega_item_id, $idimagem, $idFuncaoImagem);
                            $upAiBypass->execute();
                            $upAiBypass->close();
                        }
                        if ($upBypass = $conn->prepare("UPDATE entregas_itens SET status = 'Entrega pendente' WHERE id = ?")) {
                            $upBypass->bind_param("i", $bypass_entrega_item_id);
                            $upBypass->execute();
                            $upBypass->close();
                        }
                        $bypassEntregaLogs[] = "P00: entrega_item_id=$bypass_entrega_item_id vinculado aos ângulos.";
                    } else {
                        // Fluxo padrão: usa o último historico da função
                        $stmtBypassHist = $conn->prepare("SELECT id FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? ORDER BY id DESC LIMIT 1");
                        $stmtBypassHist->bind_param("i", $idFuncaoImagem);
                        $stmtBypassHist->execute();
                        $stmtBypassHist->bind_result($bypass_hist_id);
                        if ($stmtBypassHist->fetch()) {
                            $stmtBypassHist->close();
                            $stmtBypassUpd = $conn->prepare("UPDATE entregas_itens SET historico_id = ?, status = 'Entrega pendente' WHERE id = ?");
                            $stmtBypassUpd->bind_param("ii", $bypass_hist_id, $bypass_entrega_item_id);
                            $stmtBypassUpd->execute();
                            $stmtBypassUpd->close();
                            $bypassEntregaLogs[] = "entregas_itens id=$bypass_entrega_item_id atualizado com historico_id=$bypass_hist_id.";
                        } else {
                            $stmtBypassHist->close();
                            $bypassEntregaLogs[] = "Histórico não encontrado para funcao_imagem_id=$idFuncaoImagem.";
                        }
                    }
                } else {
                    $stmtBypassItem->close();
                    $bypassEntregaLogs[] = "entregas_itens não encontrado para entrega_id=$bypass_entrega_id imagem_id=$idimagem.";
                }
            } else {
                $stmtBypassEnt->close();
                $bypassEntregaLogs[] = "Entrega não encontrada para status_id=$bypass_status_id obra_id=$bypass_obra_id.";
            }
        } else {
            $stmtBypassImg->close();
            $bypassEntregaLogs[] = "imagem id=$idimagem não encontrada em imagens_cliente_obra.";
        }
    }

    // ---------- Slack: função já no servidor ----------
    $slackWebhookPos = improov_env('SLACK_WEBHOOK_POS_URL', null);
    if ($slackWebhookPos) {
        $nomeImagemNotif = $nome_imagem ?: $nomeImagemSanitizado ?: 'Imagem';
        $funcaoDisplay   = trim($nomeFuncao);
        $slackMsg = ['text' => "A {$funcaoDisplay} da imagem {$nomeImagemNotif} foi refeita e já está no servidor!"];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $slackWebhookPos);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($slackMsg));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('[uploadArquivos.php] Slack bypass error: ' . curl_error($ch));
        }
        curl_close($ch);
    }

    notifyFlowReviewUpdate($conn, 'media.created', [
        'funcao_imagem_id' => $isAnimacaoUpload ? null : (int) $idFuncaoImagem,
        'funcao_animacao_id' => $isAnimacaoUpload ? (int) $idFuncaoImagem : null,
        'imagem_id' => (int) $idimagem,
        'historico_id' => $historicoIdsEnviados ? (int) end($historicoIdsEnviados) : null,
        'historico_ids' => $historicoIdsEnviados,
        'indice_envio' => (int) $indice_envio,
        'versao' => (int) $indice_envio,
        'media_count' => count($imagensEnviadas),
        'status_novo' => 'Aprovado',
    ]);

    echo json_encode([
        "success"        => "Imagens enviadas e aprovadas automaticamente!",
        "bypass_nas"     => true,
        "nas_enviado"    => $nasSent,
        "nas_logs"       => $nasLogs,
        "entrega_logs"   => $bypassEntregaLogs,
        "indice_envio"   => $indice_envio,
        "imagens"        => $imagensEnviadas,
    ]);
    exit;
}

// ---------- Bloquear reenvio quando há comentários pendentes ----------------
// Aplica-se apenas quando a função estava em "Ajuste" (reenvio após revisão).
// No primeiro envio ($funcao_status_norm não é 'ajuste') o bloqueio não ocorre.
$funcao_status_norm_pre = strtolower(removerTodosAcentos((string)($funcao_status ?? '')));
if (!$isAnimacaoUpload && $funcao_status_norm_pre === 'ajuste') {
    // Verifica se a coluna 'concluido' existe
    $chkCol = $conn->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comentarios_imagem' AND COLUMN_NAME = 'concluido' LIMIT 1"
    );
    $chkCol->execute();
    $colExists = ($chkCol->get_result()->num_rows > 0);
    $chkCol->close();

    if ($colExists) {
        // Busca o último historico_aprovacoes_imagens para esta função
        $stmtLastHai = $conn->prepare(
            "SELECT id FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmtLastHai->bind_param('i', $idFuncaoImagem);
        $stmtLastHai->execute();
        $rowLastHai = $stmtLastHai->get_result()->fetch_assoc();
        $stmtLastHai->close();

        if ($rowLastHai) {
            $lastApImagemId = (int)$rowLastHai['id'];
            $stmtPend = $conn->prepare(
                "SELECT COUNT(*) AS total, SUM(concluido) AS concluidos FROM comentarios_imagem WHERE ap_imagem_id = ?"
            );
            $stmtPend->bind_param('i', $lastApImagemId);
            $stmtPend->execute();
            $rowPend    = $stmtPend->get_result()->fetch_assoc();
            $stmtPend->close();
            $totalComents = (int)($rowPend['total']     ?? 0);
            $concluidosCo = (int)($rowPend['concluidos'] ?? 0);
            if ($totalComents > 0 && $concluidosCo < $totalComents) {
                $pendentes = $totalComents - $concluidosCo;
                json_error(
                    "Existem {$pendentes} comentário(s) não concluído(s). Conclua todos os ajustes no Flow Review antes de enviar uma nova versão.",
                    422,
                    ['pendentes' => $pendentes, 'total' => $totalComents, 'concluidos' => $concluidosCo]
                );
            }
        }
    }
}
// ---------- Fim bloqueio comentários pendentes ------------------------------

// ---------- Atualiza status para Em aprovação sem alterar o prazo corrente ----------
if ($isAnimacaoUpload) {
    $stmt = $conn->prepare("UPDATE funcao_animacao
                            SET status = 'Em aprovação'
                            WHERE id = ?");
} else {
    $stmt = $conn->prepare("UPDATE funcao_imagem
                        SET prazo = NOW(), status = 'Em aprovação', requires_file_upload = 1, file_uploaded_at = NULL
                        WHERE idfuncao_imagem = ?");
}
$stmt->bind_param("i", $idFuncaoImagem);
if (!$stmt->execute()) {
    json_error('Erro ao atualizar status da função: ' . $stmt->error, 500);
}
$statusUpdateAffectedRows = $stmt->affected_rows;
$stmt->close();
if ($statusUpdateAffectedRows === 0) {
    $checkSql = $isAnimacaoUpload
        ? "SELECT 1 FROM funcao_animacao WHERE id = ? LIMIT 1"
        : "SELECT 1 FROM funcao_imagem WHERE idfuncao_imagem = ? LIMIT 1";
    $stmtCheckStatusTarget = $conn->prepare($checkSql);
    if ($stmtCheckStatusTarget) {
        $stmtCheckStatusTarget->bind_param("i", $idFuncaoImagem);
        $stmtCheckStatusTarget->execute();
        $statusTargetExists = $stmtCheckStatusTarget->get_result()->num_rows > 0;
        $stmtCheckStatusTarget->close();
        if (!$statusTargetExists) {
            json_error(
                $isAnimacaoUpload ? 'Função de animação não encontrada ao atualizar status.' : 'Função de imagem não encontrada ao atualizar status.',
                404,
                [
                    'tipo_tarefa' => $isAnimacaoUpload ? 'animacao' : 'imagem',
                    'funcao_id' => $idFuncaoImagem,
                ]
            );
        }
    }
}

if ($isAnimacaoUpload) {
    $_reviewResponsavel = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : null;
    $_reviewColaborador = $_reviewResponsavel ?: null;
    $stmtAnimHist = $conn->prepare(
        "INSERT INTO historico_aprovacoes
            (funcao_imagem_id, funcao_animacao_id, status_anterior, status_novo, colaborador_id, responsavel)
         VALUES (NULL, ?, ?, 'Em aprovação', ?, ?)"
    );
    if ($stmtAnimHist) {
        $stmtAnimHist->bind_param("isii", $idFuncaoImagem, $funcao_status, $_reviewColaborador, $_reviewResponsavel);
        $stmtAnimHist->execute();
        $stmtAnimHist->close();
    }
}

// ---------- Registra evento de entrega no histórico SLA ----------
if (!$isAnimacaoUpload) {
    $_slaColabId   = isset($_SESSION['idcolaborador']) ? (int)$_SESSION['idcolaborador'] : null;
    $_slaUsuarioId = isset($_SESSION['idusuario'])     ? (int)$_SESSION['idusuario']     : null;
    $_slaOrigem    = 'upload_previa';
    $_slaStatusAnt = $funcao_status;
    $_slaStatusNov = 'Em aprovação';
    $stmtSlaHist = $conn->prepare(
        "INSERT INTO funcao_imagem_prazo_historico
            (funcao_imagem_id, prazo_anterior, prazo_novo,
             alterado_por_colaborador_id, alterado_por_usuario_id,
             origem, motivo, status_anterior, status_novo)
         VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?)"
    );
    if ($stmtSlaHist) {
        $_slaHoje = date('Y-m-d');
        $stmtSlaHist->bind_param('issiisss',
            $idFuncaoImagem, $funcao_prazo, $_slaHoje,
            $_slaColabId, $_slaUsuarioId, $_slaOrigem,
            $_slaStatusAnt, $_slaStatusNov);
        $stmtSlaHist->execute();
        $stmtSlaHist->close();
    }
}

// ---------- Notificação Slack: função refeita ----------
// Disparada quando o colaborador re-envia arquivos após um Ajuste
// ou quando a função estava em "Em aprovação" (opção B solicitada).
if (!$isAnimacaoUpload && in_array($funcao_status_norm, ['ajuste', 'em aprovacao'], true)) {
    $slackWebhookPos = improov_env('SLACK_WEBHOOK_POS_URL', null);
    if ($slackWebhookPos) {
        $nomeImagemNotif = $nome_imagem ?: $nomeImagemSanitizado ?: 'Imagem';
        $funcaoDisplay   = trim($nomeFuncao);
        // Determina "refeito/refeita" pela terminação da última palavra
        $palavras      = preg_split('/[^a-zA-ZÀ-ÿ0-9]+/u', $funcaoDisplay, -1, PREG_SPLIT_NO_EMPTY);
        $ultimaPalavra = strtolower(end($palavras) ?: '');
        $terminacoesFemininas = ['a', 'ao', 'cao', 'sao', 'dade', 'agem', 'ncia'];
        $sufixo = 'refeito';
        foreach ($terminacoesFemininas as $term) {
            if (substr(removerTodosAcentos($ultimaPalavra), -strlen($term)) === $term) {
                $sufixo = 'refeita';
                break;
            }
        }
        $slackMsg = ['text' => "{$funcaoDisplay} {$sufixo} para a imagem {$nomeImagemNotif}."];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $slackWebhookPos);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($slackMsg));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        if (curl_errno($ch)) {
            error_log('[uploadArquivos.php] Slack reenvio error: ' . curl_error($ch));
        }
        curl_close($ch);
    }
}

notifyFlowReviewUpdate($conn, 'media.created', [
    'funcao_imagem_id' => $isAnimacaoUpload ? null : (int) $idFuncaoImagem,
    'funcao_animacao_id' => $isAnimacaoUpload ? (int) $idFuncaoImagem : null,
    'imagem_id' => (int) $idimagem,
    'historico_id' => $historicoIdsEnviados ? (int) end($historicoIdsEnviados) : null,
    'historico_ids' => $historicoIdsEnviados,
    'indice_envio' => (int) $indice_envio,
    'versao' => (int) $indice_envio,
    'media_count' => count($imagensEnviadas),
    'status_novo' => 'Em aprovação',
]);

echo json_encode([
    "success"      => "Imagens enviadas com sucesso via SFTP!",
    "indice_envio" => $indice_envio,
    "imagens"      => $imagensEnviadas,
    "posters"      => $postersEnviados
]);

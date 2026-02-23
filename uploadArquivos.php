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

require 'conexao.php';
require_once __DIR__ . '/config/secure_env.php';

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
    $stmt = $conn->prepare("SELECT MAX(indice_envio) AS max_indice FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ?");
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
$nomeImagemSanitizado = sanitizeFilename($nome_imagem);

// Quando adicionando ao índice existente, começa a numeração após as imagens já presentes
$previaOffset = 0;
if ($indice_envio_forcado > 0) {
    $stCount = $conn->prepare("SELECT COUNT(*) AS qtd FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ? AND indice_envio = ?");
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


for ($i = 0; $i < $totalImagens; $i++) {
    $numeroPrevia = $previaOffset + $i + 1;

    $imagemAtual = [
        'name'     => $imagens['name'][$i],
        'tmp_name' => $imagens['tmp_name'][$i],
        'error'    => $imagens['error'][$i]
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
    $arquivoRemoto = $ftp_base . $nomeFinalSemExt . "." . $extensao;

    list($ok, $msg) = enviarArquivoSFTP($conn_ftp, $imagemAtual['tmp_name'], $arquivoRemoto);
    if (!$ok) {
        json_error((string)$msg, 500);
    }

    $caminhoBanco = 'uploads/' . $nomeFinalSemExt . "." . $extensao;


    // Salva no banco o caminho remoto
    $stmt = $conn->prepare("INSERT INTO historico_aprovacoes_imagens (funcao_imagem_id, imagem, indice_envio, nome_arquivo) 
                            VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $idFuncaoImagem, $caminhoBanco, $indice_envio, $nomeFinalSemExt);
    if ($stmt->execute()) {
        $imagensEnviadas[] = $caminhoBanco;
    } else {
        json_error('Erro ao salvar no banco: ' . $stmt->error, 500);
    }
    $stmt->close();
}

// Fecha conexão SFTP
unset($conn_ftp);

// ---------- Atualiza status para Em aprovação (sempre, inclusive ao adicionar ângulos) ----------
$hoje = date('Y-m-d');
$stmt = $conn->prepare("UPDATE funcao_imagem 
                        SET status = 'Em aprovação', prazo = ?, requires_file_upload = 1, file_uploaded_at = NULL 
                        WHERE idfuncao_imagem = ?");
$stmt->bind_param("si", $hoje, $idFuncaoImagem);
if (!$stmt->execute()) {
    json_error('Erro ao atualizar status/prazo: ' . $stmt->error, 500);
}
$stmt->close();

echo json_encode([
    "success"      => "Imagens enviadas com sucesso via SFTP!",
    "indice_envio" => $indice_envio,
    "imagens"      => $imagensEnviadas
]);

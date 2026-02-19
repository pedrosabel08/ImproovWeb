<?php
header('Content-Type: application/json');

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

if (!function_exists('ftp_connect')) {
    json_error('Extensão FTP do PHP não está habilitada no servidor.', 500);
}

require 'conexao.php';
require_once __DIR__ . '/config/secure_env.php';

// ---------- Dados FTP ----------
try {
    $ftpCfg = improov_ftp_config();
} catch (RuntimeException $e) {
    json_error('Configuração FTP ausente no ambiente.', 500);
}

$ftp_host = $ftpCfg['host'];
$ftp_port = $ftpCfg['port'];
$ftp_user = $ftpCfg['user'];
$ftp_pass = $ftpCfg['pass'];
$ftp_base = $ftpCfg['base'];

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

function enviarArquivoFTP($conn_ftp, $arquivoLocal, $arquivoRemoto)
{
    // Ativa modo passivo
    ftp_pasv($conn_ftp, true);

    // tentativa direta
    if (@ftp_put($conn_ftp, $arquivoRemoto, $arquivoLocal, FTP_BINARY)) {
        return [true, $arquivoRemoto];
    }

    // coleta informações de debug
    $pwd = @ftp_pwd($conn_ftp);
    $remoteDir = dirname($arquivoRemoto);
    $remoteFile = basename($arquivoRemoto);
    $remoteDir = rtrim(str_replace('\\', '/', $remoteDir), '/');
    $nlist = @ftp_nlist($conn_ftp, $remoteDir);
    $raw = @ftp_rawlist($conn_ftp, $remoteDir);

    // tenta mudar para o diretório remoto e enviar apenas o nome do arquivo (fallback)
    if (@ftp_chdir($conn_ftp, $remoteDir)) {
        if (@ftp_put($conn_ftp, $remoteFile, $arquivoLocal, FTP_BINARY)) {
            // volta ao diretório original
            if ($pwd) @ftp_chdir($conn_ftp, $pwd);
            return [true, $remoteDir . '/' . $remoteFile];
        } else {
            if ($pwd) @ftp_chdir($conn_ftp, $pwd);
            return [false, "⚠ Erro ao enviar via FTP (put em cwd): $remoteDir/$remoteFile. pwd=$pwd, nlist=" . json_encode($nlist) . ", raw=" . json_encode($raw)];
        }
    }

    return [false, "⚠ Erro ao enviar via FTP: $arquivoRemoto (pwd=$pwd, nlist=" . json_encode($nlist) . ", raw=" . json_encode($raw) . ")"];
}

// ---------- Parâmetros ----------
$dataIdFuncoes = (int)($_POST['dataIdFuncoes'] ?? 0);
$numeroImagem  = preg_replace('/\D/', '', $_POST['numeroImagem'] ?? '');
$nomenclatura  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['nomenclatura'] ?? '');
$nomeFuncao    = $_POST['nome_funcao'] ?? '';
$nome_imagem   = $_POST['nome_imagem'] ?? '';
$idimagem      = (int)($_POST['idimagem'] ?? 0);

if (!$dataIdFuncoes || !$numeroImagem || !$nomenclatura || !$nomeFuncao) {
    json_error('Parâmetros insuficientes', 400);
}

$idFuncaoImagem = $dataIdFuncoes;
$processo = getProcesso($nomeFuncao);

// ---------- Índice de envio ----------
$stmt = $conn->prepare("SELECT MAX(indice_envio) AS max_indice FROM historico_aprovacoes_imagens WHERE funcao_imagem_id = ?");
$stmt->bind_param("i", $idFuncaoImagem);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$indice_envio = ($result['max_indice'] ?? 0) + 1;
$stmt->close();

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

// ---------- Conexão FTP ----------
$conn_ftp = ftp_connect($ftp_host, $ftp_port, 10); // timeout 10s
if (!$conn_ftp) {
    json_error('Não foi possível conectar ao servidor FTP.', 500);
}
if (!ftp_login($conn_ftp, $ftp_user, $ftp_pass)) {
    ftp_close($conn_ftp);
    json_error('Falha na autenticação FTP.', 500);
}

// ---------- Upload das imagens ----------
if (!isset($_FILES['imagens'])) {
    ftp_close($conn_ftp);
    json_error('Nenhuma imagem recebida', 400);
}

$imagens = $_FILES['imagens'];
$totalImagens = count($imagens['name']);
$imagensEnviadas = [];
$nomeImagemSanitizado = sanitizeFilename($nome_imagem);


$sqlTipoImagem = "SELECT tipo_imagem FROM imagens_cliente_obra WHERE idimagens_cliente_obra = $idimagem";
$resultTipo = $conn->query($sqlTipoImagem);
if ($resultTipo === false) {
    ftp_close($conn_ftp);
    json_error('Erro ao consultar tipo_imagem no banco.', 500, ['mysql_error' => $conn->error]);
}
$tipoImagem = $resultTipo->fetch_assoc()['tipo_imagem'] ?? '';


for ($i = 0; $i < $totalImagens; $i++) {
    $numeroPrevia = $i + 1;

    $imagemAtual = [
        'name'     => $imagens['name'][$i],
        'tmp_name' => $imagens['tmp_name'][$i],
        'error'    => $imagens['error'][$i]
    ];

    if ($imagemAtual['error'] !== UPLOAD_ERR_OK) {
        ftp_close($conn_ftp);
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

    list($ok, $msg) = enviarArquivoFTP($conn_ftp, $imagemAtual['tmp_name'], $arquivoRemoto);
    if (!$ok) {
        ftp_close($conn_ftp);
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
        ftp_close($conn_ftp);
        json_error('Erro ao salvar no banco: ' . $stmt->error, 500);
    }
    $stmt->close();
}

// Fecha conexão FTP
ftp_close($conn_ftp);

// ---------- Atualiza status ----------
$hoje = date('Y-m-d');
$stmt = $conn->prepare("UPDATE funcao_imagem 
                        SET status = 'Em aprovação', prazo = ? 
                        WHERE idfuncao_imagem = ?");
$stmt->bind_param("si", $hoje, $idFuncaoImagem);
if (!$stmt->execute()) {
    json_error('Erro ao atualizar status/prazo: ' . $stmt->error, 500);
}
$stmt->close();

echo json_encode([
    "success"      => "Imagens enviadas com sucesso via FTP!",
    "indice_envio" => $indice_envio,
    "imagens"      => $imagensEnviadas
]);

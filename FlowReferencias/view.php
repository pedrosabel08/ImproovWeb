<?php
require_once __DIR__ . '/../config/session_bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/secure_env.php';

use phpseclib3\Net\SFTP;

session_start();
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header('Location: ../index.html');
    exit();
}

include '../conexao.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Parâmetro inválido.';
    exit;
}

$stmt = $conn->prepare("SELECT id, original_name, stored_name, path, mime, size_bytes FROM flow_ref_upload WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();
$conn->close();

if (!$row) {
    http_response_code(404);
    echo 'Arquivo não encontrado.';
    exit;
}

$remotePath = $row['path'];
$downloadName = $row['original_name'] ?: ($row['stored_name'] ?: 'arquivo');
$mime = $row['mime'] ?: 'application/octet-stream';

try {
    $sftpCfg = improov_sftp_config();
} catch (RuntimeException $e) {
    http_response_code(500);
    echo 'Configuração SFTP ausente.';
    exit;
}

$host = $sftpCfg['host'];
$port = $sftpCfg['port'];
$username = $sftpCfg['user'];
$password = $sftpCfg['pass'];

$sftp = new SFTP($host, $port);
if (!$sftp->login($username, $password)) {
    http_response_code(502);
    echo 'Falha ao conectar no SFTP.';
    exit;
}

$tmpLocal = tempnam(sys_get_temp_dir(), 'flowref_');
if (!$tmpLocal) {
    http_response_code(500);
    echo 'Erro ao preparar download.';
    exit;
}

$ok = $sftp->get($remotePath, $tmpLocal);
if (!$ok) {
    @unlink($tmpLocal);
    http_response_code(404);
    echo 'Arquivo não encontrado no NAS.';
    exit;
}

$filesize = filesize($tmpLocal);
if ($filesize === false) $filesize = null;

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
if ($filesize !== null) {
    header('Content-Length: ' . $filesize);
}
header('X-Content-Type-Options: nosniff');

readfile($tmpLocal);
@unlink($tmpLocal);

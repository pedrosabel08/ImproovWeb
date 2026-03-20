<?php
/**
 * upload_comentario_vps.php
 *
 * Helper: recebe um arquivo de imagem vindo de $_FILES e faz upload
 * diretamente para o VPS via SFTP, retornando a URL pública.
 *
 * Uso:
 *   require_once __DIR__ . '/upload_comentario_vps.php';
 *   $url = uploadComentarioVps($_FILES['imagem']);  // retorna string URL ou lança RuntimeException
 */

require_once __DIR__ . '/../config/secure_env.php';
require_once __DIR__ . '/vendor/autoload.php';

use phpseclib3\Net\SFTP;

/**
 * Faz upload do arquivo de comentário para o VPS e retorna a URL pública.
 *
 * @param  array  $fileEntry  Entrada de $_FILES (ex.: $_FILES['imagem'])
 * @return string             URL pública do arquivo no VPS
 * @throws RuntimeException   Se o upload falhar
 */
function uploadComentarioVps(array $fileEntry): string
{
    if ($fileEntry['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erro no upload do arquivo: código ' . $fileEntry['error']);
    }

    $tmpPath  = $fileEntry['tmp_name'];
    $ext      = strtolower(pathinfo($fileEntry['name'], PATHINFO_EXTENSION));
    $filename = uniqid('coment_', true) . ($ext ? '.' . $ext : '');

    $vpsCfg   = improov_sftp_config('IMPROOV_VPS_SFTP');
    $vpsBase  = rtrim((string) improov_env('IMPROOV_VPS_SFTP_REMOTE_PATH'), '/');
    $remoteDir  = $vpsBase . '/uploads/comentarios/';
    $remotePath = $remoteDir . $filename;

    $sftp = new SFTP($vpsCfg['host'], (int) $vpsCfg['port']);
    if (!$sftp->login($vpsCfg['user'], $vpsCfg['pass'])) {
        throw new RuntimeException('Falha ao autenticar no VPS SFTP.');
    }

    // Garante que o diretório remoto existe
    if (!$sftp->is_dir($remoteDir)) {
        $sftp->mkdir($remoteDir, 0755, true);
    }

    if (!$sftp->put($remotePath, $tmpPath, SFTP::SOURCE_LOCAL_FILE)) {
        throw new RuntimeException('Falha ao enviar arquivo para o VPS via SFTP.');
    }

    return 'https://improov.com.br/flow/ImproovWeb/uploads/comentarios/' . $filename;
}

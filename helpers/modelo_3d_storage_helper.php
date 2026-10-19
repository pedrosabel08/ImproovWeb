<?php

require_once __DIR__ . '/../config/secure_env.php';

/** No VPS grava no disco; o ambiente Windows publica no VPS via SFTP já configurado. */
function mp3d_storage_mode(): string
{
    improov_load_env_once();
    $mode = getenv('IMPROOV_MODEL3D_STORAGE') ?: (PHP_OS_FAMILY === 'Windows' ? 'sftp' : 'local');
    if (!in_array($mode, ['local', 'sftp'], true)) {
        throw new RuntimeException('Configuração de armazenamento 3D inválida.');
    }
    return $mode;
}

function mp3d_sftp(): array
{
    static $storage = null;
    if ($storage) {
        return $storage;
    }
    require_once __DIR__ . '/../vendor/autoload.php';
    $config = improov_sftp_config('IMPROOV_VPS_SFTP');
    $root = rtrim(improov_env('IMPROOV_VPS_SFTP_REMOTE_PATH'), '/');
    if ($root === '' || $root[0] !== '/' || str_contains($root, '..')) {
        throw new RuntimeException('Diretório do VPS inválido para modelos 3D.');
    }
    $sftp = new \phpseclib3\Net\SFTP($config['host'], $config['port'], 60);
    // Fingerprint já utilizado pelos scripts de implantação deste projeto.
    $trusted = getenv('IMPROOV_VPS_SFTP_HOST_KEY_SHA256') ?: '4fb4d6a8d5f2d3d1837c9284da43f2a6fd51f16786c3efc1392ad72c46da3170';
    $key = $sftp->getServerPublicHostKey();
    if (!$key || !hash_equals($trusted, hash('sha256', $key)) || !$sftp->login($config['user'], $config['pass'])) {
        throw new RuntimeException('Não foi possível autenticar o armazenamento 3D no VPS.');
    }
    $sftp->setTimeout(300);
    return $storage = [$sftp, $root];
}

function mp3d_publish(string $relative, string $local): bool
{
    if (mp3d_storage_mode() !== 'sftp') {
        return false;
    }
    [$sftp, $root] = mp3d_sftp();
    $base = $root . '/uploads/model-3d';
    $remote = $root . '/' . $relative;
    $directory = dirname($remote);
    if (!$sftp->is_dir($directory) && !$sftp->mkdir($directory, 0755, true)) {
        throw new RuntimeException('Não foi possível preparar a pasta de modelos 3D no VPS.');
    }
    if (!$sftp->file_exists($base . '/.htaccess') && !$sftp->put($base . '/.htaccess', "Options -Indexes\nRequire all denied\n")) {
        throw new RuntimeException('Não foi possível proteger os modelos 3D no VPS.');
    }
    // Nunca sobrescreve um arquivo remoto preexistente.
    if ($sftp->file_exists($remote)) {
        throw new RuntimeException('O arquivo 3D já existe no VPS.');
    }
    $temporary = $remote . '.part-' . bin2hex(random_bytes(6));
    try {
        if (!$sftp->put($temporary, $local, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE) || (int)$sftp->filesize($temporary) !== filesize($local)) {
            throw new RuntimeException('O envio do modelo 3D ao VPS não foi concluído.');
        }
        if (!$sftp->rename($temporary, $remote)) {
            throw new RuntimeException('Não foi possível finalizar o modelo 3D no VPS.');
        }
        return true;
    } finally {
        if ($sftp->file_exists($temporary)) {
            $sftp->delete($temporary, false);
        }
    }
}

function mp3d_remove_published(string $relative): void
{
    if (mp3d_storage_mode() !== 'sftp') {
        return;
    }
    [$sftp, $root] = mp3d_sftp();
    $sftp->delete($root . '/' . $relative, false);
}

function mp3d_cache_from_vps(string $relative): void
{
    if (mp3d_storage_mode() !== 'sftp') {
        return;
    }
    [$sftp, $root] = mp3d_sftp();
    $size = $sftp->filesize($root . '/' . $relative);
    if (!$size || $size > 500 * 1024 * 1024) {
        throw new RuntimeException('Modelo 3D indisponível no VPS.', 404);
    }
    $destination = __DIR__ . '/../' . $relative;
    $directory = dirname($destination);
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Não foi possível preparar o cache do modelo 3D.');
    }
    $base = realpath(__DIR__ . '/../uploads/model-3d');
    if (!$base || !str_starts_with(realpath($directory) . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Pasta do modelo 3D inválida.');
    }
    $temporary = $destination . '.part-' . bin2hex(random_bytes(6));
    try {
        if (!$sftp->get($root . '/' . $relative, $temporary) || filesize($temporary) !== (int)$size || !rename($temporary, $destination)) {
            throw new RuntimeException('Não foi possível carregar o modelo 3D do VPS.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

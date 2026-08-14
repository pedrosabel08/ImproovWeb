<?php

/**
 * SIRE — Restauração de referências do NAS para o VPS.
 *
 * Este utilitário é separado do importador normal (VPS -> NAS). Ele usa
 * referencias_imagens como manifesto e restaura somente arquivos ausentes
 * em /uploads/<nome_arquivo>.jpg.
 *
 * Segurança:
 * - sem --execute, nenhuma cópia é feita (modo simulação);
 * - nunca sobrescreve um arquivo existente no VPS;
 * - valida tamanho e SHA-1 do arquivo no NAS antes do envio;
 * - baixa novamente o arquivo enviado e valida o SHA-1 no VPS;
 * - não altera nenhuma tabela do banco.
 *
 * Uso:
 *   php SIRE/restaurar_referencias.php --dry-run --verbose
 *   php SIRE/restaurar_referencias.php --execute --verbose
 *   php SIRE/restaurar_referencias.php --execute --limit=50
 */

declare(strict_types=1);

define('SIRE_PROJECT_DIR', dirname(__DIR__));

$secureEnv = SIRE_PROJECT_DIR . '/config/secure_env.php';
if (!is_file($secureEnv)) {
    fwrite(STDERR, "ERRO: config/secure_env.php não encontrado.\n");
    exit(1);
}
require $secureEnv;

if (!function_exists('curl_init') || !in_array('sftp', curl_version()['protocols'] ?? [], true)) {
    fwrite(STDERR, "ERRO: o PHP precisa da extensão cURL com suporte a SFTP.\n");
    exit(1);
}

/** Cliente SFTP mínimo usando o cURL já disponível no PHP. */
final class SireSftpClient
{
    private int $lastErrno = 0;
    private string $lastError = '';
    private $infoHandle;
    private $transferHandle;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password
    ) {
        $this->infoHandle = curl_init();
        $this->transferHandle = curl_init();
    }

    private function url(string $path): string
    {
        return 'sftp://' . $this->host . ':' . $this->port . '/' . ltrim($path, '/');
    }

    private function configure($handle, string $url): void
    {
        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_USERPWD => $this->user . ':' . $this->password,
            CURLOPT_SSH_AUTH_TYPES => CURLSSH_AUTH_PASSWORD,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 180,
        ]);
    }

    public function close(): void
    {
        if (is_object($this->infoHandle) || is_resource($this->infoHandle)) {
            curl_close($this->infoHandle);
        }
        if (is_object($this->transferHandle) || is_resource($this->transferHandle)) {
            curl_close($this->transferHandle);
        }
    }

    public function assertReachable(string $path): void
    {
        $this->remoteSize($path);
        // 78 é o retorno normal do cURL para um arquivo remoto inexistente;
        // os demais erros indicam falha de conexão/autenticação/configuração.
        if ($this->lastErrno !== 0 && $this->lastErrno !== 78) {
            throw new RuntimeException(
                'SFTP ' . $this->host . ':' . $this->port . ' indisponível: '
                . $this->lastError
            );
        }
    }

    public function remoteSize(string $path): ?int
    {
        $handle = $this->infoHandle;
        $this->configure($handle, $this->url($path));
        curl_setopt_array($handle, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($handle);
        $error = curl_errno($handle);
        $errorText = curl_error($handle);
        $size = curl_getinfo($handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD_T);

        $this->lastErrno = $error;
        $this->lastError = $errorText;

        if ($error !== 0) {
            return null;
        }
        return max(0, (int) $size);
    }

    public function download(string $remotePath, string $localPath): bool
    {
        $output = fopen($localPath, 'wb');
        if ($output === false) {
            return false;
        }
        $handle = $this->transferHandle;
        $this->configure($handle, $this->url($remotePath));
        curl_setopt_array($handle, [
            CURLOPT_NOBODY => false,
            CURLOPT_UPLOAD => false,
            CURLOPT_INFILE => null,
            CURLOPT_INFILESIZE => 0,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $data) use ($output): int {
                return (int) fwrite($output, $data);
            },
        ]);
        $result = curl_exec($handle);
        $error = curl_errno($handle);
        $errorText = curl_error($handle);
        fclose($output);
        $this->lastErrno = $error;
        $this->lastError = $errorText;
        return $result !== false && $error === 0;
    }

    public function upload(string $localPath, string $remotePath): bool
    {
        $input = fopen($localPath, 'rb');
        if ($input === false) {
            return false;
        }
        $handle = $this->transferHandle;
        $this->configure($handle, $this->url($remotePath));
        curl_setopt_array($handle, [
            CURLOPT_NOBODY => false,
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $input,
            CURLOPT_INFILESIZE => filesize($localPath),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $data): int {
                return strlen($data);
            },
        ]);
        $result = curl_exec($handle);
        $error = curl_errno($handle);
        $errorText = curl_error($handle);
        fclose($input);
        $this->lastErrno = $error;
        $this->lastError = $errorText;
        return $result !== false && $error === 0;
    }
}

$args = array_slice($argv ?? [], 1);
$execute = in_array('--execute', $args, true);
$verbose = in_array('--verbose', $args, true);
$limit = 10000;

foreach ($args as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $match)) {
        $limit = max(1, (int) $match[1]);
    }
}

function restore_log(string $message, string $level = 'INFO'): void
{
    global $verbose;

    if ($level === 'SKIP' && !$verbose) {
        return;
    }

    echo '[' . date('Y-m-d H:i:s') . '] [' . str_pad($level, 5) . '] ' . $message . PHP_EOL;
}

function restore_remote_size(SireSftpClient $sftp, string $path): ?int
{
    return $sftp->remoteSize($path);
}

function restore_is_safe_file_name(string $name): bool
{
    return $name !== ''
        && $name !== '.'
        && $name !== '..'
        && !str_contains($name, '/')
        && !str_contains($name, '\\')
        && !str_contains($name, '..');
}

function restore_is_storage_path(string $path, string $storageBase): bool
{
    $base = rtrim(str_replace('\\', '/', $storageBase), '/');
    $candidate = str_replace('\\', '/', $path);
    return str_starts_with($candidate, $base . '/')
        && !str_contains(substr($candidate, strlen($base) + 1), '../')
        && !str_contains($candidate, '/../');
}

function restore_download(SireSftpClient $sftp, string $remotePath, string $localPath): bool
{
    return $sftp->download($remotePath, $localPath);
}

function restore_upload(SireSftpClient $sftp, string $localPath, string $remotePath): bool
{
    return $sftp->upload($localPath, $remotePath);
}

improov_load_env_once();

$vpsHost = improov_env('IMPROOV_FTP_HOST', '72.60.137.192');
$vpsPort = (int) improov_env('IMPROOV_FTP_PORT', '22');
$vpsUser = improov_env('IMPROOV_FTP_USER', 'root');
$vpsPass = improov_env('IMPROOV_FTP_PASS', '');
$vpsUploads = rtrim(improov_env(
    'IMPROOV_VPS_UPLOADS_PATH',
    '/home/improov/web/improov.com.br/public_html/flow/ImproovWeb/uploads'
), '/');

$storageBase = rtrim(improov_env(
    'SIRE_STORAGE_BASE',
    '/mnt/exchange/_SIRE/storage/imagens'
), '/');
$nasConfig = improov_sftp_config('IMPROOV_SFTP');

$dbHost = improov_env('DB_HOST', '72.60.137.192');
$dbPort = (int) improov_env('DB_PORT', '3306');
$dbUser = improov_env('DB_USERNAME', 'improov');
$dbPass = improov_env('DB_PASSWORD', '');
$dbName = improov_env('DB_DATABASE', 'flowdb');

restore_log('SIRE — Restauração NAS -> VPS');
restore_log('Modo: ' . ($execute ? 'EXECUÇÃO' : 'SIMULAÇÃO (use --execute para copiar)'));
restore_log('Origem NAS: ' . $nasConfig['host'] . ':' . $nasConfig['port']);
restore_log('Destino VPS: ' . $vpsHost . ':' . $vpsPort . ' ' . $vpsUploads);

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 30,
        ]
    );

    $query = $pdo->prepare(
        'SELECT nome_arquivo, caminho_storage, hash_sha1, tamanho_bytes
         FROM referencias_imagens
         ORDER BY id
         LIMIT :limite'
    );
    $query->bindValue(':limite', $limit, PDO::PARAM_INT);
    $query->execute();
    $rows = $query->fetchAll();
} catch (Throwable $exception) {
    restore_log('Erro ao consultar referencias_imagens: ' . $exception->getMessage(), 'FATAL');
    exit(1);
}

$stats = [
    'total' => count($rows),
    'ja_existente' => 0,
    'restaurado' => 0,
    'simulado' => 0,
    'nome_invalido' => 0,
    'origem_invalida' => 0,
    'origem_ausente' => 0,
    'origem_integra' => 0,
    'erro' => 0,
];

if (!$rows) {
    restore_log('Nenhuma referência encontrada.');
    exit(0);
}

try {
    $vps = new SireSftpClient($vpsHost, $vpsPort, $vpsUser, $vpsPass);
    $vps->assertReachable($vpsUploads . '/.__sire_connection_test__.jpg');
    restore_log('SFTP VPS: OK');

    $nas = new SireSftpClient($nasConfig['host'], (int) $nasConfig['port'], $nasConfig['user'], $nasConfig['pass']);
    $nas->assertReachable((string) $rows[0]['caminho_storage']);
    restore_log('SFTP NAS: OK');
} catch (Throwable $exception) {
    restore_log('Erro ao conectar aos servidores: ' . $exception->getMessage(), 'FATAL');
    exit(1);
}

foreach ($rows as $index => $row) {
    $name = trim((string) ($row['nome_arquivo'] ?? ''));
    $source = trim((string) ($row['caminho_storage'] ?? ''));
    $expectedHash = strtolower(trim((string) ($row['hash_sha1'] ?? '')));
    $expectedSize = (int) ($row['tamanho_bytes'] ?? 0);
    $prefix = '[' . ($index + 1) . '/' . count($rows) . '] ' . ($name ?: '(sem nome)');

    if (!restore_is_safe_file_name($name)) {
        restore_log($prefix . ' — nome inválido, ignorado.', 'WARN');
        $stats['nome_invalido']++;
        continue;
    }

    if (!restore_is_storage_path($source, $storageBase)) {
        restore_log($prefix . ' — caminho_storage fora do storage configurado, ignorado.', 'WARN');
        $stats['origem_invalida']++;
        continue;
    }

    // O helper atual do SIRE monta sempre /uploads/<nome_arquivo>.jpg.
    $destination = $vpsUploads . '/' . $name . '.jpg';
    $destinationSize = restore_remote_size($vps, $destination);
    // Mesmo um arquivo de 0 bytes é considerado existente: a restauração
    // nunca sobrescreve automaticamente um caminho já presente no VPS.
    if ($destinationSize !== null) {
        restore_log($prefix . ' — já existe no VPS, não sobrescrito.', 'SKIP');
        $stats['ja_existente']++;
        continue;
    }

    $sourceSize = restore_remote_size($nas, $source);
    if ($sourceSize === null || $sourceSize <= 0) {
        restore_log($prefix . ' — origem ausente ou vazia no NAS.', 'WARN');
        $stats['origem_ausente']++;
        continue;
    }

    if ($expectedSize <= 0 || $sourceSize !== $expectedSize) {
        restore_log($prefix . " — tamanho divergente (NAS={$sourceSize}, banco={$expectedSize}).", 'WARN');
        $stats['erro']++;
        continue;
    }

    $stats['origem_integra']++;
    if (!$execute) {
        restore_log($prefix . ' — seria restaurado.', 'DRY');
        $stats['simulado']++;
        continue;
    }

    $tmpSource = tempnam(sys_get_temp_dir(), 'sire_restore_');
    $tmpVerify = tempnam(sys_get_temp_dir(), 'sire_verify_');
    if ($tmpSource === false || $tmpVerify === false) {
        if ($tmpSource !== false) @unlink($tmpSource);
        if ($tmpVerify !== false) @unlink($tmpVerify);
        restore_log($prefix . ' — não foi possível criar temporários.', 'ERROR');
        $stats['erro']++;
        continue;
    }

    try {
        $downloaded = restore_download($nas, $source, $tmpSource);
        $sourceValid = $downloaded
            && filesize($tmpSource) === $expectedSize
            && strtolower((string) hash_file('sha1', $tmpSource)) === $expectedHash;
        if (!$sourceValid) {
            restore_log($prefix . ' — SHA-1/tamanho do NAS não confere.', 'ERROR');
            $stats['erro']++;
            continue;
        }

        if (!restore_upload($vps, $tmpSource, $destination)) {
            restore_log($prefix . ' — falha ao enviar para o VPS.', 'ERROR');
            $stats['erro']++;
            continue;
        }

        $verified = restore_download($vps, $destination, $tmpVerify)
            && filesize($tmpVerify) === $expectedSize
            && strtolower((string) hash_file('sha1', $tmpVerify)) === $expectedHash;
        if (!$verified) {
            restore_log($prefix . ' — envio concluído, mas validação no VPS falhou.', 'ERROR');
            $stats['erro']++;
            continue;
        }

        restore_log($prefix . ' — restaurado e validado.', 'OK');
        $stats['restaurado']++;
    } finally {
        @unlink($tmpSource);
        @unlink($tmpVerify);
    }
}

restore_log('Resumo final');
foreach ($stats as $key => $value) {
    restore_log('  ' . $key . ': ' . $value);
}

$nas->close();
$vps->close();
$pdo = null;

exit($stats['erro'] > 0 ? 2 : 0);

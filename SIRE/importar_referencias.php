<?php
/**
 * SIRE — Sistema de Importação de Referências de Imagens
 * Script: importar_referencias.php
 *
 * Importa imagens finais JPG do servidor VPS para o storage permanente no NAS,
 * registrando cada arquivo na tabela `referencias_imagens`.
 *
 * Uso:
 *   php importar_referencias.php [--dry-run] [--limit=N] [--verbose]
 *
 * Opções:
 *   --dry-run    Simula o processo sem copiar arquivos nem gravar no banco
 *   --limit=N    Processa no máximo N registros (padrão: 10000)
 *   --verbose    Exibe detalhes extras no terminal
 */

declare(strict_types=1);

// ── Bootstrap ──────────────────────────────────────────────────────────────────

define('SIRE_SCRIPT_DIR', __DIR__);
define('SIRE_PROJECT_DIR', dirname(__DIR__));

$autoload = SIRE_PROJECT_DIR . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "ERRO: vendor/autoload.php não encontrado. Execute `composer install` no diretório do projeto.\n");
    exit(1);
}
require $autoload;

$secureEnv = SIRE_PROJECT_DIR . '/config/secure_env.php';
if (!file_exists($secureEnv)) {
    fwrite(STDERR, "ERRO: config/secure_env.php não encontrado.\n");
    exit(1);
}
require $secureEnv;

use phpseclib3\Net\SFTP;

// ── Argumentos ─────────────────────────────────────────────────────────────────

$args    = array_slice($argv ?? [], 1);
$dryRun  = in_array('--dry-run', $args, true);
$verbose = in_array('--verbose', $args, true);
$limit   = 10000;

foreach ($args as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = max(1, (int) $m[1]);
    }
}

// ── Configurações ──────────────────────────────────────────────────────────────

improov_load_env_once();

// VPS — servidor de origem (onde estão os arquivos /uploads)
$vpsHost         = improov_env('IMPROOV_FTP_HOST',  '72.60.137.192');
$vpsPort         = (int) improov_env('IMPROOV_FTP_PORT',  '22');
$vpsUser         = improov_env('IMPROOV_FTP_USER',  'root');
$vpsPass         = improov_env('IMPROOV_FTP_PASS',  '');
$vpsUploadsPath  = rtrim(improov_env('IMPROOV_VPS_UPLOADS_PATH', '/home/improov/web/improov.com.br/public_html/flow/ImproovWeb/uploads'), '/');

// NAS — storage permanente (destino)
try {
    $nasCfg = improov_sftp_config('IMPROOV_SFTP');
} catch (RuntimeException $e) {
    fwrite(STDERR, "ERRO: configuração SFTP NAS ausente — " . $e->getMessage() . "\n");
    exit(1);
}
$nasStorageBase = rtrim(improov_env('SIRE_STORAGE_BASE', '/mnt/exchange/_SIRE/storage/imagens'), '/');

// Banco de dados
$dbHost = improov_env('DB_HOST',     '72.60.137.192');
$dbPort = (int) improov_env('DB_PORT', '3306');
$dbUser = improov_env('DB_USERNAME', 'improov');
$dbPass = improov_env('DB_PASSWORD', '');
$dbName = improov_env('DB_DATABASE', 'flowdb');

// Log
$logFile = '/var/log/importador_referencias.log';

// ── Funções utilitárias ─────────────────────────────────────────────────────────

/**
 * Registra uma linha de log no arquivo e no terminal.
 */
function sire_log(string $msg, string $level = 'INFO'): void
{
    global $logFile, $verbose;

    $line = '[' . date('Y-m-d H:i:s') . '] [' . str_pad($level, 5) . '] ' . $msg . PHP_EOL;

    // Sempre exibe WARN, ERROR, FATAL, OK e INFO no terminal; SKIP só no verbose
    $showLevels = ['INFO', 'OK', 'WARN', 'ERROR', 'FATAL', 'DRY'];
    if ($verbose || in_array($level, $showLevels, true)) {
        echo $line;
    }

    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Converte um hash SHA-1 em caminho relativo no storage.
 * Exemplo: a1b2c3d4... → a1/b2/a1b2c3d4....jpg
 */
function sire_hash_to_rel_path(string $hash, string $ext): string
{
    return $hash[0] . $hash[1] . '/' . $hash[2] . $hash[3] . '/' . $hash . '.' . $ext;
}

/**
 * Cria diretório no SFTP recursivamente, se não existir.
 */
function sire_mkdir_sftp(SFTP $sftp, string $path): bool
{
    if ($sftp->is_dir($path)) {
        return true;
    }
    return (bool) $sftp->mkdir($path, 0755, true);
}

/**
 * Cria a conexão PDO com o banco de dados.
 */
function sire_create_pdo(
    string $host,
    int $port,
    string $user,
    string $pass,
    string $dbName
): PDO {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::ATTR_TIMEOUT            => 30,
    ]);
}

// ── Início do processamento ────────────────────────────────────────────────────

sire_log('════════════════════════════════════════════════════════════');
sire_log('SIRE — Importador de Referências de Imagens');
sire_log('════════════════════════════════════════════════════════════');
sire_log('Dry-run : ' . ($dryRun ? 'SIM (nenhuma alteração será feita)' : 'NÃO'));
sire_log('Limite  : ' . $limit . ' registros');
sire_log('Destino : ' . $nasStorageBase);
sire_log('Origem  : ' . $vpsHost . ':' . $vpsPort . '  ' . $vpsUploadsPath);

$stats = [
    'total'          => 0,
    'importado'      => 0,
    'ignorado'       => 0,
    'duplicado'      => 0,
    'nao_encontrado' => 0,
    'erro'           => 0,
];

// ── Conexões ──────────────────────────────────────────────────────────────────

$pdo     = null;
$sftpVps = null;
$sftpNas = null;

try {
    sire_log('Conectando ao banco de dados (' . $dbHost . ':' . $dbPort . ')...');
    $pdo = sire_create_pdo($dbHost, $dbPort, $dbUser, $dbPass, $dbName);
    sire_log('Banco de dados: OK');

    sire_log('Conectando ao VPS via SFTP (' . $vpsHost . ':' . $vpsPort . ')...');
    $sftpVps = new SFTP($vpsHost, $vpsPort);
    if (!$sftpVps->login($vpsUser, $vpsPass)) {
        throw new RuntimeException('Falha na autenticação SFTP VPS (' . $vpsHost . ')');
    }
    sire_log('VPS SFTP: OK');

    sire_log('Conectando ao NAS via SFTP (' . $nasCfg['host'] . ':' . $nasCfg['port'] . ')...');
    $sftpNas = new SFTP($nasCfg['host'], $nasCfg['port']);
    if (!$sftpNas->login($nasCfg['user'], $nasCfg['pass'])) {
        throw new RuntimeException('Falha na autenticação SFTP NAS (' . $nasCfg['host'] . ')');
    }
    sire_log('NAS SFTP: OK');

} catch (Throwable $e) {
    sire_log('ERRO na inicialização de conexões: ' . $e->getMessage(), 'FATAL');
    exit(1);
}

// ── Query de seleção ─────────────────────────────────────────────────────────

$querySql = "
    WITH ultimos AS (
        SELECT
            o.nomenclatura,
            hi.nome_arquivo,
            hi.funcao_imagem_id,
            hi.id,
            ROW_NUMBER() OVER (
                PARTITION BY hi.funcao_imagem_id
                ORDER BY hi.id DESC
            ) AS rn
        FROM historico_aprovacoes_imagens hi
        JOIN funcao_imagem fi
            ON hi.funcao_imagem_id = fi.idfuncao_imagem
        JOIN imagens_cliente_obra i
            ON i.idimagens_cliente_obra = fi.imagem_id
        JOIN obra o
            ON o.idobra = i.obra_id
        WHERE
            fi.funcao_id = 5
            AND i.status_id = 6
            AND i.substatus_id = 9
            AND hi.nome_arquivo IS NOT NULL
            AND hi.nome_arquivo != ''
    )
    SELECT
        nomenclatura,
        nome_arquivo,
        funcao_imagem_id
    FROM ultimos
    WHERE rn = 1
    LIMIT :limit
";

sire_log('Executando query de seleção de imagens finais...');

try {
    $stmtQuery = $pdo->prepare($querySql);
    $stmtQuery->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtQuery->execute();
    $registros = $stmtQuery->fetchAll();
    $stats['total'] = count($registros);
    sire_log('Registros encontrados: ' . $stats['total']);
} catch (Throwable $e) {
    sire_log('ERRO ao executar query: ' . $e->getMessage(), 'FATAL');
    exit(1);
}

// ── Prepared statements ──────────────────────────────────────────────────────

$stmtCheckFuncao = $pdo->prepare(
    'SELECT id FROM referencias_imagens WHERE funcao_imagem_id = :fid LIMIT 1'
);
$stmtCheckHash = $pdo->prepare(
    'SELECT id FROM referencias_imagens WHERE hash_sha1 = :hash LIMIT 1'
);
$stmtInsert = $pdo->prepare("
    INSERT INTO referencias_imagens
        (funcao_imagem_id, nomenclatura, nome_arquivo, caminho_origem, caminho_storage,
         hash_sha1, largura, altura, tamanho_bytes)
    VALUES
        (:funcao_imagem_id, :nomenclatura, :nome_arquivo, :caminho_origem, :caminho_storage,
         :hash_sha1, :largura, :altura, :tamanho_bytes)
");

// ── Processamento ─────────────────────────────────────────────────────────────

sire_log('Iniciando processamento...');
sire_log('────────────────────────────────────────────────────────────');

foreach ($registros as $idx => $row) {
    $funcaoId     = (int) ($row['funcao_imagem_id'] ?? 0);
    $nomeArq      = trim((string) ($row['nome_arquivo'] ?? ''));
    $nomenclatura = trim((string) ($row['nomenclatura'] ?? ''));
    $counter      = sprintf('[%d/%d]', $idx + 1, $stats['total']);

    if ($nomeArq === '' || $funcaoId === 0) {
        sire_log("$counter IGNORADO — linha inválida (sem nome ou funcao_id)", 'SKIP');
        $stats['ignorado']++;
        continue;
    }

    sire_log("$counter $nomeArq  (funcao_imagem_id=$funcaoId)");

    try {
        // ── 1. Verificar duplicidade por funcao_imagem_id ──────────────────
        $stmtCheckFuncao->bindValue(':fid', $funcaoId, PDO::PARAM_INT);
        $stmtCheckFuncao->execute();
        if ($stmtCheckFuncao->fetchColumn() !== false) {
            sire_log("  → DUPLICADO (funcao_imagem_id=$funcaoId já registrado)", 'SKIP');
            $stats['duplicado']++;
            continue;
        }

        // ── 2. Localizar arquivo no VPS (tenta .jpg depois .jpeg) ────────────
        // nome_arquivo no banco NÃO contém extensão — tentamos as duas possibilidades
        $ext           = null;
        $caminhoOrigem = null;
        foreach (['jpg', 'jpeg'] as $tentativa) {
            $candidato = $vpsUploadsPath . '/' . $nomeArq . '.' . $tentativa;
            $sz = $sftpVps->filesize($candidato);
            if ($sz !== false && (int) $sz > 0) {
                $ext           = $tentativa;
                $caminhoOrigem = $candidato;
                break;
            }
        }

        if ($ext === null) {
            sire_log("  → NÃO ENCONTRADO: $vpsUploadsPath/{$nomeArq}.jpg (.jpeg)", 'WARN');
            $stats['nao_encontrado']++;
            continue;
        }

        // ── 3. Baixar para arquivo temporário ──────────────────────────────
        $tmpFile = tempnam(sys_get_temp_dir(), 'sire_');
        if ($tmpFile === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário');
        }

        $downloaded = $sftpVps->get($caminhoOrigem, $tmpFile);
        if (!$downloaded || !file_exists($tmpFile) || filesize($tmpFile) <= 0) {
            sire_log("  → ERRO ao baixar arquivo do VPS", 'ERROR');
            @unlink($tmpFile);
            $stats['erro']++;
            continue;
        }

        // ── 5. Calcular SHA-1 ─────────────────────────────────────────────
        $hash = sha1_file($tmpFile);
        if ($hash === false) {
            sire_log("  → ERRO ao calcular SHA-1", 'ERROR');
            @unlink($tmpFile);
            $stats['erro']++;
            continue;
        }

        // ── 6. Verificar duplicidade por hash (conteúdo idêntico) ──────────
        $stmtCheckHash->bindValue(':hash', $hash);
        $stmtCheckHash->execute();
        if ($stmtCheckHash->fetchColumn() !== false) {
            sire_log("  → DUPLICADO (hash=$hash já existe no storage)", 'SKIP');
            @unlink($tmpFile);
            $stats['duplicado']++;
            continue;
        }

        // ── 7. Obter metadados da imagem ──────────────────────────────────
        $imgInfo      = @getimagesize($tmpFile);
        $largura      = $imgInfo !== false ? (int) $imgInfo[0] : null;
        $altura       = $imgInfo !== false ? (int) $imgInfo[1] : null;
        $tamanhoByes  = (int) filesize($tmpFile);

        // ── 8. Calcular caminho de destino ─────────────────────────────────
        $relPath        = sire_hash_to_rel_path($hash, $ext);
        $caminhoStorage = $nasStorageBase . '/' . $relPath;
        $dirStorage     = dirname($caminhoStorage);

        if ($dryRun) {
            sire_log("  → [DRY-RUN] seria copiado: $caminhoStorage  sha1=$hash  {$largura}x{$altura}  {$tamanhoByes}B", 'DRY');
            @unlink($tmpFile);
            $stats['importado']++;
            continue;
        }

        // ── 9. Criar diretório no NAS ──────────────────────────────────────
        if (!sire_mkdir_sftp($sftpNas, $dirStorage)) {
            sire_log("  → ERRO ao criar diretório no NAS: $dirStorage", 'ERROR');
            @unlink($tmpFile);
            $stats['erro']++;
            continue;
        }

        // ── 10. Copiar para o NAS (sem sobrescrever) ───────────────────────
        if ($sftpNas->file_exists($caminhoStorage)) {
            sire_log("  → Arquivo já existe no NAS, ignorando cópia: $caminhoStorage", 'SKIP');
        } else {
            $uploaded = $sftpNas->put($caminhoStorage, $tmpFile, SFTP::SOURCE_LOCAL_FILE);
            if (!$uploaded) {
                sire_log("  → ERRO ao copiar para o NAS: $caminhoStorage", 'ERROR');
                @unlink($tmpFile);
                $stats['erro']++;
                continue;
            }
        }

        @unlink($tmpFile);

        // ── 11. Inserir no banco de dados ──────────────────────────────────
        $stmtInsert->execute([
            ':funcao_imagem_id' => $funcaoId,
            ':nomenclatura'     => $nomenclatura !== '' ? $nomenclatura : null,
            ':nome_arquivo'     => $nomeArq,
            ':caminho_origem'   => $caminhoOrigem,
            ':caminho_storage'  => $caminhoStorage,
            ':hash_sha1'        => $hash,
            ':largura'          => $largura,
            ':altura'           => $altura,
            ':tamanho_bytes'    => $tamanhoByes,
        ]);

        $dim = ($largura !== null && $altura !== null) ? "{$largura}x{$altura}" : 'dim:?';
        sire_log("  → IMPORTADO  sha1=$hash  $dim  {$tamanhoByes}B  → $caminhoStorage", 'OK');
        $stats['importado']++;

    } catch (Throwable $e) {
        sire_log("  → EXCEÇÃO: " . $e->getMessage(), 'ERROR');
        if (isset($tmpFile) && file_exists($tmpFile)) {
            @unlink($tmpFile);
        }
        $stats['erro']++;
    }
}

// ── Resumo final ─────────────────────────────────────────────────────────────

sire_log('════════════════════════════════════════════════════════════');
sire_log('RESUMO FINAL' . ($dryRun ? ' [DRY-RUN]' : ''));
sire_log('════════════════════════════════════════════════════════════');
sire_log(sprintf('Total processado  : %d', $stats['total']));
sire_log(sprintf('Total importado   : %d', $stats['importado']));
sire_log(sprintf('Total ignorado    : %d', $stats['ignorado']));
sire_log(sprintf('Total duplicado   : %d', $stats['duplicado']));
sire_log(sprintf('Não encontrado    : %d', $stats['nao_encontrado']));
sire_log(sprintf('Total com erro    : %d', $stats['erro']));
sire_log('Log salvo em: ' . $logFile);
sire_log('════════════════════════════════════════════════════════════');

exit($stats['erro'] > 0 ? 1 : 0);

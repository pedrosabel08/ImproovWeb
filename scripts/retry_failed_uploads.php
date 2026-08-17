<?php

declare(strict_types=1);

// CLI utilitário: reenvia jobs de uploads/failed para uploads/staging.
//
// Uso:
//   php scripts/retry_failed_uploads.php
//   php scripts/retry_failed_uploads.php --dry-run
//   php scripts/retry_failed_uploads.php --id=upl_698e1205986205.00281749
//   php scripts/retry_failed_uploads.php --limit=10
//   php scripts/retry_failed_uploads.php --daemon --sleep=60

$failedDir  = __DIR__ . '/../uploads/failed';
$stagingDir = __DIR__ . '/../uploads/staging';

if (!is_dir($failedDir)) {
    fwrite(STDERR, "Diretório não encontrado: {$failedDir}\n");
    exit(1);
}
if (!is_dir($stagingDir) && !@mkdir($stagingDir, 0777, true)) {
    fwrite(STDERR, "Não foi possível criar diretório: {$stagingDir}\n");
    exit(1);
}

$opts = getopt('', ['dry-run', 'id:', 'limit:', 'daemon', 'sleep:']);
$dryRun    = isset($opts['dry-run']);
$filterId  = isset($opts['id']) ? trim((string) $opts['id']) : '';
$limit     = isset($opts['limit']) ? max(1, (int) $opts['limit']) : 0;
$daemon    = isset($opts['daemon']);
$sleepSecs = isset($opts['sleep']) ? max(5, (int) $opts['sleep']) : 60;

$shutdown = false;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
}
if (function_exists('pcntl_signal') && defined('SIGTERM') && defined('SIGINT')) {
    pcntl_signal(SIGTERM, static function () use (&$shutdown): void {
        $shutdown = true;
    });
    pcntl_signal(SIGINT, static function () use (&$shutdown): void {
        $shutdown = true;
    });
}

function rlog(string $msg, string $level = 'INFO'): void
{
    echo '[' . date('Y-m-d H:i:s') . "] [{$level}] {$msg}\n";
}

/** Move safely, including across filesystems. Source and destination must differ. */
function safe_move(string $src, string $dst): bool
{
    if ($src === $dst || !is_file($src)) {
        return false;
    }

    $dir = dirname($dst);
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        return false;
    }
    if (@rename($src, $dst)) {
        return true;
    }
    if (!@copy($src, $dst)) {
        return false;
    }
    return @unlink($src);
}

/** Returns the job id represented by a failed metadata file, or null. */
function job_id_from_meta_path(string $path): ?string
{
    $name = basename($path);
    if (preg_match('/^(.+)\\.json(?:\\.processing(?:\\.\\d+)?)?$/', $name, $matches) !== 1) {
        return null;
    }
    return $matches[1];
}

function is_metadata_artifact(string $name): bool
{
    return preg_match('/\\.json(?:\\.processing(?:\\.\\d+)?)?(?:\\.err)?$/', $name) === 1;
}

/**
 * Finds non-metadata files belonging to a job. They are sorted to make an
 * ambiguous situation deterministic, but callers must still reject ambiguity.
 *
 * @return string[]
 */
function physical_files_for_job(string $dir, string $jobId): array
{
    $files = glob($dir . DIRECTORY_SEPARATOR . $jobId . '.*') ?: [];
    $files = array_values(array_filter($files, static function (string $file): bool {
        return is_file($file) && !is_metadata_artifact(basename($file));
    }));
    sort($files, SORT_STRING);
    return $files;
}

function remove_file(string $path, bool $dryRun, string $description): bool
{
    if (!is_file($path)) {
        return true;
    }
    if ($dryRun) {
        rlog("[dry-run] Removeria {$description}: " . basename($path));
        return true;
    }
    if (@unlink($path)) {
        rlog("Removido {$description}: " . basename($path));
        return true;
    }
    rlog("Não foi possível remover {$description}: {$path}", 'WARN');
    return false;
}

/** Removes only artifacts that have an already-confirmed counterpart in staging. */
function cleanup_failed_artifacts(
    string $failedDir,
    string $stagingDir,
    string $jobId,
    string $metaPath,
    bool $dryRun
): void {
    remove_file($metaPath, $dryRun, 'meta antigo de failed');

    foreach (glob($failedDir . DIRECTORY_SEPARATOR . $jobId . '.json*.err') ?: [] as $errFile) {
        if (is_file($errFile)) {
            remove_file($errFile, $dryRun, 'arquivo de erro antigo');
        }
    }

    // A cópia física em failed só é removida se o arquivo de mesmo nome existir
    // em staging. Isso evita apagar um upload que não foi confirmado.
    foreach (physical_files_for_job($failedDir, $jobId) as $failedPhysical) {
        $stagedCounterpart = $stagingDir . DIRECTORY_SEPARATOR . basename($failedPhysical);
        if (is_file($stagedCounterpart)) {
            remove_file($failedPhysical, $dryRun, 'cópia física duplicada de failed');
        } else {
            rlog(
                "Mantendo arquivo em failed sem correspondente confirmado em staging: " . basename($failedPhysical),
                'WARN'
            );
        }
    }
}

/**
 * Creates a temporary, fully serialized metadata file before moving the payload.
 * This catches JSON and write errors before the physical state is changed.
 */
function prepare_staged_meta(string $metaDest, string $encodedMeta): ?string
{
    $temp = $metaDest . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(6));
    if (@file_put_contents($temp, $encodedMeta, LOCK_EX) === false) {
        return null;
    }
    return $temp;
}

/** @return array{processed:int,recovered:int,skipped:int,errors:int} */
function retry_once(
    string $failedDir,
    string $stagingDir,
    string $filterId,
    int $limit,
    bool $dryRun,
    bool &$shutdown
): array {
    $result = ['processed' => 0, 'recovered' => 0, 'skipped' => 0, 'errors' => 0];
    $metaPaths = glob($failedDir . DIRECTORY_SEPARATOR . '*.json*') ?: [];
    sort($metaPaths, SORT_STRING);

    foreach ($metaPaths as $metaPath) {
        if ($shutdown) {
            rlog('Encerramento solicitado; interrompendo ciclo atual.', 'INFO');
            break;
        }
        if (!is_file($metaPath) || str_ends_with($metaPath, '.err')) {
            continue;
        }

        $jobId = job_id_from_meta_path($metaPath);
        if ($jobId === null) {
            rlog('Ignorando meta com nome não reconhecido: ' . basename($metaPath), 'WARN');
            $result['skipped']++;
            continue;
        }
        if ($filterId !== '' && $jobId !== $filterId) {
            continue;
        }
        if ($limit > 0 && $result['processed'] + $result['recovered'] + $result['skipped'] + $result['errors'] >= $limit) {
            break;
        }

        $rawMeta = @file_get_contents($metaPath);
        $meta = $rawMeta === false ? null : json_decode($rawMeta, true);
        if (!is_array($meta)) {
            rlog("Meta inválido para job {$jobId}: " . json_last_error_msg(), 'ERROR');
            $result['errors']++;
            continue;
        }
        try {
            $encodedMeta = json_encode(
                $meta,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
            ) . PHP_EOL;
        } catch (JsonException $e) {
            rlog("Não foi possível serializar meta do job {$jobId}: {$e->getMessage()}", 'ERROR');
            $result['errors']++;
            continue;
        }

        $metaDest = $stagingDir . DIRECTORY_SEPARATOR . $jobId . '.json';
        $stagedPhysicalFiles = physical_files_for_job($stagingDir, $jobId);
        $failedPhysicalFiles = physical_files_for_job($failedDir, $jobId);
        $metaExists = is_file($metaDest);

        if (count($stagedPhysicalFiles) > 1) {
            rlog("Há mais de um arquivo físico em staging para job {$jobId}; nenhuma ação foi tomada.", 'ERROR');
            $result['errors']++;
            continue;
        }
        if (count($failedPhysicalFiles) > 1) {
            rlog("Há mais de um arquivo físico em failed para job {$jobId}; nenhuma ação foi tomada.", 'ERROR');
            $result['errors']++;
            continue;
        }

        $stagedPhysical = $stagedPhysicalFiles[0] ?? null;
        $failedPhysical = $failedPhysicalFiles[0] ?? null;

        if ($metaExists && $stagedPhysical === null) {
            rlog("Meta já existe em staging, mas o arquivo físico não existe para job {$jobId}.", 'ERROR');
            $result['errors']++;
            continue;
        }

        if ($metaExists && $stagedPhysical !== null) {
            rlog("Job {$jobId} já está completo em staging; limpando resíduos em failed.", 'WARN');
            cleanup_failed_artifacts($failedDir, $stagingDir, $jobId, $metaPath, $dryRun);
            $result['skipped']++;
            continue;
        }

        // Se o payload já está em staging, este é o estado parcial recuperável:
        // basta publicar o JSON, sem tentar mover o arquivo para ele mesmo.
        if ($stagedPhysical !== null) {
            rlog("Arquivo já está em staging sem JSON; recuperando meta do job {$jobId}.", 'WARN');
            if ($dryRun) {
                rlog("[dry-run] Criaria meta em staging: " . basename($metaDest));
                cleanup_failed_artifacts($failedDir, $stagingDir, $jobId, $metaPath, true);
                $result['recovered']++;
                continue;
            }
            $tempMeta = prepare_staged_meta($metaDest, $encodedMeta);
            if ($tempMeta === null || !safe_move($tempMeta, $metaDest)) {
                if ($tempMeta !== null) {
                    @unlink($tempMeta);
                }
                rlog("Falha ao criar meta em staging para job {$jobId}.", 'ERROR');
                $result['errors']++;
                continue;
            }
            cleanup_failed_artifacts($failedDir, $stagingDir, $jobId, $metaPath, false);
            $result['recovered']++;
            continue;
        }

        if ($failedPhysical === null) {
            rlog("Arquivo físico não encontrado em failed nem em staging para job {$jobId}.", 'ERROR');
            $result['errors']++;
            continue;
        }

        $stagedDest = $stagingDir . DIRECTORY_SEPARATOR . basename($failedPhysical);
        if ($dryRun) {
            rlog("[dry-run] Prepararia meta e moveria " . basename($failedPhysical) . " para staging (job {$jobId}).");
            rlog("[dry-run] Criaria meta em staging: " . basename($metaDest));
            rlog("[dry-run] Removeria meta e .err relacionados de failed após confirmação.");
            $result['processed']++;
            continue;
        }

        // O JSON é serializado e gravado temporariamente antes da movimentação.
        // Assim um erro de serialização/escrita não deixa o payload parcialmente movido.
        $tempMeta = prepare_staged_meta($metaDest, $encodedMeta);
        if ($tempMeta === null) {
            rlog("Falha ao preparar meta em staging para job {$jobId}; arquivo não foi movido.", 'ERROR');
            $result['errors']++;
            continue;
        }
        if (!safe_move($failedPhysical, $stagedDest)) {
            @unlink($tempMeta);
            rlog("Falha ao mover arquivo de {$failedPhysical} para {$stagedDest}.", 'ERROR');
            $result['errors']++;
            continue;
        }
        if (!safe_move($tempMeta, $metaDest)) {
            // A próxima execução recuperará este estado: físico em staging e meta em failed.
            @unlink($tempMeta);
            rlog("Arquivo movido, mas falhou ao publicar meta; o próximo retry recuperará job {$jobId}.", 'ERROR');
            $result['errors']++;
            continue;
        }

        cleanup_failed_artifacts($failedDir, $stagingDir, $jobId, $metaPath, false);
        rlog("Job {$jobId} reenviado para staging com sucesso.");
        $result['processed']++;
    }

    return $result;
}

do {
    $summary = retry_once($failedDir, $stagingDir, $filterId, $limit, $dryRun, $shutdown);
    rlog(sprintf(
        'Resumo: processados=%d, recuperados=%d, ignorados=%d, erros=%d',
        $summary['processed'],
        $summary['recovered'],
        $summary['skipped'],
        $summary['errors']
    ));

    if (!$daemon || $shutdown) {
        break;
    }
    rlog("Aguardando {$sleepSecs}s para o próximo ciclo.");
    sleep($sleepSecs);
} while (!$shutdown);

rlog('Retry finalizado.');

<?php
// CLI utilitário: reenvia jobs de uploads/failed para uploads/staging
// Uso:
//   php scripts/retry_failed_uploads.php
//   php scripts/retry_failed_uploads.php --dry-run
//   php scripts/retry_failed_uploads.php --id=upl_698e1205986205.00281749

$failedDir = __DIR__ . '/../uploads/failed';
$stagingDir = __DIR__ . '/../uploads/staging';

if (!is_dir($failedDir)) {
    fwrite(STDERR, "Diretório não encontrado: {$failedDir}\n");
    exit(1);
}
if (!is_dir($stagingDir) && !@mkdir($stagingDir, 0777, true)) {
    fwrite(STDERR, "Não foi possível criar diretório: {$stagingDir}\n");
    exit(1);
}

$opts = getopt('', ['dry-run', 'id:', 'limit:']);
$dryRun = isset($opts['dry-run']);
$filterId = isset($opts['id']) ? trim((string)$opts['id']) : '';
$limit = isset($opts['limit']) ? max(1, (int)$opts['limit']) : 0;

function rlog(string $msg, string $level = 'INFO'): void
{
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] [{$level}] {$msg}\n";
}

function safe_move(string $src, string $dst): bool
{
    if (!file_exists($src)) {
        return false;
    }
    $dir = dirname($dst);
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        return false;
    }
    if (@rename($src, $dst)) {
        return true;
    }
    if (@copy($src, $dst)) {
        if (@unlink($src)) {
            return true;
        }
    }
    return false;
}

function extract_job_id_from_meta_name(string $fileName): string
{
    $base = basename($fileName);
    $base = preg_replace('/\.processing\.\d+$/', '', $base);
    $base = preg_replace('/\.processing$/', '', $base);
    $base = preg_replace('/\.json$/', '', $base);
    return (string)$base;
}

function is_meta_candidate(string $path): bool
{
    $name = basename($path);
    if (str_ends_with($name, '.err')) {
        return false;
    }
    return preg_match('/\.json(?:\.processing(?:\.\d+)?)?$/', $name) === 1;
}

function find_staged_file_in_failed(array $meta, string $failedDir, string $jobId): ?string
{
    $fromMeta = $meta['staged_path'] ?? null;
    if (!empty($fromMeta)) {
        $candidate = rtrim($failedDir, '/\\') . DIRECTORY_SEPARATOR . basename((string)$fromMeta);
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    $all = glob(rtrim($failedDir, '/\\') . DIRECTORY_SEPARATOR . $jobId . '.*') ?: [];
    foreach ($all as $f) {
        $bn = basename($f);
        if (preg_match('/\.json(?:\.processing(?:\.\d+)?)?$/', $bn)) {
            continue;
        }
        if (str_ends_with($bn, '.err')) {
            continue;
        }
        if (is_file($f)) {
            return $f;
        }
    }

    return null;
}

$allFailed = glob($failedDir . '/*') ?: [];
$metaFiles = array_values(array_filter($allFailed, 'is_meta_candidate'));

if (empty($metaFiles)) {
    rlog('Nenhum meta de falha encontrado em uploads/failed.', 'WARN');
    exit(0);
}

$processed = 0;
$requeued = 0;
$skipped = 0;
$errors = 0;

foreach ($metaFiles as $metaPath) {
    $jobId = extract_job_id_from_meta_name($metaPath);
    if ($filterId !== '' && $filterId !== $jobId) {
        continue;
    }
    if ($limit > 0 && $processed >= $limit) {
        break;
    }
    $processed++;

    $raw = @file_get_contents($metaPath);
    $meta = $raw ? json_decode($raw, true) : null;
    if (!is_array($meta)) {
        rlog("Meta inválido, ignorando: {$metaPath}", 'ERROR');
        $errors++;
        continue;
    }

    $stagedSrc = find_staged_file_in_failed($meta, $failedDir, $jobId);
    if (!$stagedSrc || !is_file($stagedSrc)) {
        rlog("Arquivo staged não encontrado para job {$jobId}; mantendo em failed.", 'WARN');
        $skipped++;
        continue;
    }

    $stagedDest = $stagingDir . DIRECTORY_SEPARATOR . basename($stagedSrc);
    $metaDest = $stagingDir . DIRECTORY_SEPARATOR . $jobId . '.json';

    if (file_exists($metaDest)) {
        rlog("Já existe meta em staging para job {$jobId}: {$metaDest}", 'WARN');
        $skipped++;
        continue;
    }
    if (file_exists($stagedDest)) {
        rlog("Já existe arquivo staged em staging para job {$jobId}: {$stagedDest}", 'WARN');
        $skipped++;
        continue;
    }

    $meta['attempts'] = 0;
    $meta['log_status'] = 'enfileirado';
    $meta['staged_path'] = $stagedDest;
    unset($meta['uploaded_remote'], $meta['upload_success_at'], $meta['db_updated'], $meta['remote_path'], $meta['windows_path'], $meta['nome_final'], $meta['tamanho_final']);

    if ($dryRun) {
        rlog("[dry-run] Reenfileirar job {$jobId}");
        rlog("[dry-run] staged: {$stagedSrc} -> {$stagedDest}");
        rlog("[dry-run] meta:   {$metaPath} -> {$metaDest}");
        $requeued++;
        continue;
    }

    if (!safe_move($stagedSrc, $stagedDest)) {
        rlog("Falha ao mover staged de {$stagedSrc} para {$stagedDest}", 'ERROR');
        $errors++;
        continue;
    }

    $encodedMeta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($encodedMeta === false) {
        rlog("Falha ao serializar meta para job {$jobId}", 'ERROR');
        // rollback staged
        safe_move($stagedDest, $stagedSrc);
        $errors++;
        continue;
    }

    if (@file_put_contents($metaDest, $encodedMeta) === false) {
        rlog("Falha ao escrever meta em staging: {$metaDest}", 'ERROR');
        // rollback staged
        safe_move($stagedDest, $stagedSrc);
        $errors++;
        continue;
    }

    if (!@unlink($metaPath)) {
        rlog("Não foi possível remover meta antigo de failed: {$metaPath}", 'WARN');
    }

    rlog("Job reenfileirado com sucesso: {$jobId}", 'SUCCESS');
    $requeued++;
}

rlog('Resumo do reprocessamento:');
rlog("- Processados: {$processed}");
rlog("- Reenfileirados: {$requeued}", 'SUCCESS');
rlog("- Ignorados: {$skipped}", $skipped > 0 ? 'WARN' : 'INFO');
rlog("- Erros: {$errors}", $errors > 0 ? 'ERROR' : 'INFO');

exit($errors > 0 ? 2 : 0);

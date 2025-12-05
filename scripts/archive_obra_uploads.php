<?php
// Arquivo: scripts/archive_obra_uploads.php
// Objetivo: quando uma obra for marcada como "Finalizada", mover todos os arquivos nos diretórios de uploads
// que contenham a nomenclatura especificada para uma pasta de arquivamento, permitindo remoção posterior.
// Uso (CLI): php scripts/archive_obra_uploads.php --nomenclatura="OBRA_XYZ" [--dry-run]
// Padrões: percorre toda a pasta uploads (recursivo), sem limitar por subpastas.

declare(strict_types=1);

$root = dirname(__DIR__);
$uploadsRoot = $root . DIRECTORY_SEPARATOR . 'uploads';
$archiveBase = $uploadsRoot . DIRECTORY_SEPARATOR . 'archive';

function usage(): void
{
    echo "\nUso: php scripts/archive_obra_uploads.php --nomenclatura=<NOME> [--dry-run]\n";
    echo "\nDescrição:\n";
    echo "  Move arquivos relacionados à nomenclatura informada em toda a pasta uploads para uploads/archive/<nomenclatura>/<timestamp>.\n";
    echo "  Em modo --dry-run, apenas lista o que seria movido.\n\n";
}

function ensure_dir(string $path): bool
{
    if (is_dir($path)) return true;
    return @mkdir($path, 0777, true);
}

function safe_move(string $src, string $destDir): bool
{
    if (!file_exists($src)) return false;
    if (!ensure_dir($destDir)) {
        fprintf(STDERR, "[archive] Erro ao criar diretório destino: %s\n", $destDir);
        return false;
    }
    $dest = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . basename($src);
    if (@rename($src, $dest)) return true;
    if (@copy($src, $dest)) {
        if (@unlink($src)) return true;
        fprintf(STDERR, "[archive] Copiado mas não conseguiu excluir origem: %s\n", $src);
        return false;
    }
    fprintf(STDERR, "[archive] Falha ao mover %s -> %s\n", $src, $dest);
    return false;
}

function format_bytes(int $bytes): string
{
    $units = ['B','KB','MB','GB','TB'];
    $i = 0;
    $val = (float)$bytes;
    while ($val >= 1024 && $i < count($units) - 1) {
        $val /= 1024;
        $i++;
    }
    return sprintf('%.2f %s', $val, $units[$i]);
}

// Parse args
$opts = getopt('', ['nomenclatura:', 'dry-run']);
if (!$opts || empty($opts['nomenclatura'])) {
    usage();
    exit(1);
}

$nomenclatura = (string)$opts['nomenclatura'];
$dryRun = isset($opts['dry-run']);

$timestamp = date('Ymd_His');
$archiveTarget = $archiveBase . DIRECTORY_SEPARATOR . $nomenclatura . DIRECTORY_SEPARATOR . $timestamp;

if ($dryRun) {
    echo "[archive] Modo dry-run: nenhuma alteração será feita.\n";
}

echo "[archive] Nomenclatura: {$nomenclatura}\n";
echo "[archive] Destino: {$archiveTarget}\n";
echo "[archive] Escopo: uploads (recursivo)\n";

// Caminhar recursivamente em uploads
function iter_uploads(string $root): Generator
{
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $fileInfo) {
        yield $fileInfo->getPathname();
    }
}

$moved = 0;
$listed = 0;
$totalBytes = 0;

foreach (iter_uploads($uploadsRoot) as $path) {
    $base = basename($path);
    // Critério: apenas pelo nome do arquivo conter a nomenclatura
    $matches = stripos($base, $nomenclatura) !== false;

    if (!$matches) continue;

    $listed++;
    $size = @filesize($path);
    $sizeText = is_int($size) ? format_bytes($size) : 'tamanho desconhecido';
    $totalBytes += is_int($size) ? $size : 0;
    echo ($dryRun ? '[DRY] ' : '') . "[archive] Encontrado: {$path} (" . $sizeText . ")\n";
    if ($dryRun) continue;

    if (safe_move($path, $archiveTarget)) {
        $moved++;
    }
}

echo "[archive] Total listado: {$listed}. Movidos: {$moved}. Tamanho total: " . format_bytes($totalBytes) . "\n";
if (!$dryRun) {
    echo "[archive] Arquivos arquivados em: {$archiveTarget}\n";
}

exit(0);

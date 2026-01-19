<?php
/**
 * Atualiza automaticamente tags <script src="<?php echo asset_url('...'); ?>"> e <link rel="stylesheet" href="<?php echo asset_url('...'); ?>">
 * em arquivos .php que renderizam HTML, adicionando cache-busting via asset_url().
 *
 * Uso:
 *   php tools/versionize_assets.php
 */

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Erro: não foi possível localizar a raiz do projeto.\n");
    exit(1);
}

$excludeDirs = [
    $root . DIRECTORY_SEPARATOR . 'vendor',
    $root . DIRECTORY_SEPARATOR . 'cache',
    $root . DIRECTORY_SEPARATOR . 'logs',
    $root . DIRECTORY_SEPARATOR . 'uploads',
    $root . DIRECTORY_SEPARATOR . 'Backup',
    $root . DIRECTORY_SEPARATOR . 'node_modules',
];

function is_excluded(string $path, array $excludeDirs): bool
{
    foreach ($excludeDirs as $dir) {
        if ($dir !== '' && str_starts_with($path, $dir . DIRECTORY_SEPARATOR)) {
            return true;
        }
    }
    return false;
}

function should_process(string $content): bool
{
    // Só mexe em páginas HTML (evita endpoints JSON/AJAX)
    $lower = strtolower($content);
    if (strpos($lower, '<!doctype html') === false && strpos($lower, '<html') === false) {
        return false;
    }

    return (strpos($lower, '<script') !== false) || (strpos($lower, 'rel="stylesheet"') !== false) || (strpos($lower, "rel='stylesheet'") !== false);
}

function ensure_version_include(string $content): string
{
    // Se já inclui, não mexe
    if (strpos($content, 'config/version.php') !== false) {
        return $content;
    }

    // Insere após a primeira tag <?php
    $pos = strpos($content, '<?php');
    if ($pos === false) {
        return $content;
    }

    $insert = "\n" . "require_once rtrim(\$_SERVER['DOCUMENT_ROOT'] ?? '', '/\\\\') . '/flow/ImproovWeb/config/version.php';\n";

    // Se DOCUMENT_ROOT não existir (caso raro), não quebra: o require vai falhar.
    // Como isso roda em páginas web com HTML, DOCUMENT_ROOT costuma existir.

    $afterPhp = $pos + 5;
    return substr($content, 0, $afterPhp) . $insert . substr($content, $afterPhp);
}

function needs_version(string $url): bool
{
    $u = trim($url);
    if ($u === '') return false;

    // já dinâmico
    if (str_contains($u, '<?php')) return false;

    // externo/data/blob
    $lower = strtolower($u);
    if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://') || str_starts_with($lower, '//')) return false;
    if (str_starts_with($lower, 'data:') || str_starts_with($lower, 'blob:')) return false;

    // já tem v=
    if (preg_match('/(^|[?&])v=/', $u)) return false;

    return true;
}

function php_asset_url(string $url): string
{
    // Usa aspas simples no PHP; escapa o que for necessário
    $safe = str_replace("'", "\\'", $url);
    return "<?php echo asset_url('{$safe}'); ?>";
}

function rewrite_assets(string $content, array &$stats): string
{
    $patterns = [
        // <script ... src="<?php echo asset_url('...'); ?>">
        '/(<script\b[^>]*\bsrc=)(["\"])([^"\"]+)(\2)/i',
        // <script ... src='<?php echo asset_url('...'); ?>'>
        '/(<script\b[^>]*\bsrc=)([\'\"])([^\'\"]+)(\2)/i',
        // <link ... rel="stylesheet" ... href="<?php echo asset_url('...'); ?>">
        '/(<link\b[^>]*\brel=(["\"])stylesheet\2[^>]*\bhref=)(["\"])\s*([^"\"]+)\3/i',
        // <link ... rel='stylesheet' ... href='<?php echo asset_url('...'); ?>'>
        '/(<link\b[^>]*\brel=([\'\"])stylesheet\2[^>]*\bhref=)([\'\"])\s*([^\'\"]+)\3/i',
    ];

    // scripts
    $content = preg_replace_callback($patterns[0], function ($m) use (&$stats) {
        $url = $m[3];
        if (!needs_version($url)) return $m[0];
        $stats['scripts']++;
        return $m[1] . $m[2] . php_asset_url($url) . $m[2];
    }, $content);

    $content = preg_replace_callback($patterns[1], function ($m) use (&$stats) {
        $url = $m[3];
        if (!needs_version($url)) return $m[0];
        $stats['scripts']++;
        return $m[1] . $m[2] . php_asset_url($url) . $m[2];
    }, $content);

    // links
    $content = preg_replace_callback($patterns[2], function ($m) use (&$stats) {
        $prefix = $m[1];
        $quote = $m[3];
        $url = $m[4];
        if (!needs_version($url)) return $m[0];
        $stats['styles']++;
        return $prefix . $quote . php_asset_url($url) . $quote;
    }, $content);

    $content = preg_replace_callback($patterns[3], function ($m) use (&$stats) {
        $prefix = $m[1];
        $quote = $m[3];
        $url = $m[4];
        if (!needs_version($url)) return $m[0];
        $stats['styles']++;
        return $prefix . $quote . php_asset_url($url) . $quote;
    }, $content);

    return $content;
}

$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);

$totalFiles = 0;
$changedFiles = 0;
$totalScripts = 0;
$totalStyles = 0;

foreach ($rii as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) continue;
    if (strtolower($file->getExtension()) !== 'php') continue;

    $path = $file->getRealPath();
    if ($path === false) continue;

    if (is_excluded($path, $excludeDirs)) continue;

    $totalFiles++;
    $original = file_get_contents($path);
    if ($original === false) continue;

    if (!should_process($original)) continue;

    $stats = ['scripts' => 0, 'styles' => 0];

    $updated = ensure_version_include($original);
    $updated = rewrite_assets($updated, $stats);

    if ($updated !== $original) {
        if (file_put_contents($path, $updated) === false) {
            fwrite(STDERR, "Falha ao escrever: {$path}\n");
            continue;
        }
        $changedFiles++;
        $totalScripts += $stats['scripts'];
        $totalStyles += $stats['styles'];
        echo "Atualizado: " . str_replace($root . DIRECTORY_SEPARATOR, '', $path) . " (js:+{$stats['scripts']}, css:+{$stats['styles']})\n";
    }
}

echo "\nResumo:\n";
echo "Arquivos analisados: {$totalFiles}\n";
echo "Arquivos alterados: {$changedFiles}\n";
echo "Scripts versionados: {$totalScripts}\n";
echo "CSS versionados: {$totalStyles}\n";

<?php
/**
 * Gera relatório de contagem de linhas por arquivo e por módulo (top-level folder).
 * Ignora arquivos listados pelo Git (.gitignore) se o diretório for um repositório Git.
 * Uso:
 *   php scripts/lines_report.php
 * Saída: impresso no stdout; redirecione para arquivo se desejar.
 */

function repo_root()
{
    return realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
}

function git_list_files($root)
{
    $cwd = $root;
    $out = [];
    $rc = null;
    exec('git -C ' . escapeshellarg($cwd) . ' rev-parse --is-inside-work-tree 2>&1', $out, $rc);
    if ($rc !== 0 || trim(implode("\n", $out)) !== 'true') {
        return null;
    }
    $files = [];
    exec('git -C ' . escapeshellarg($cwd) . ' ls-files --cached --others --exclude-standard 2>&1', $files, $rc);
    if ($rc !== 0) {
        return null;
    }
    return $files;
}

function parse_gitignore($root)
{
    $p = $root . DIRECTORY_SEPARATOR . '.gitignore';
    $patterns = [];
    if (!file_exists($p)) return $patterns;
    $lines = file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $ln) {
        $ln = trim($ln);
        if ($ln === '' || strpos($ln, '#') === 0) continue;
        $patterns[] = $ln;
    }
    return $patterns;
}

function match_simple_patterns($rel, $patterns)
{
    foreach ($patterns as $p) {
        if (substr($p, -1) === '/') {
            if (strpos($rel, rtrim($p, '/')) === 0) return true;
        }
        if (fnmatch($p, $rel) || fnmatch($p . '/*', $rel)) return true;
    }
    return false;
}

function fallback_walk($root)
{
    $patterns = parse_gitignore($root);
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fileinfo) {
        $path = $fileinfo->getPathname();
        $rel = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
        if (strpos($rel, '.git' . DIRECTORY_SEPARATOR) === 0) continue;
        if (!empty($patterns) && match_simple_patterns($rel, $patterns)) continue;
        $files[] = $rel;
    }
    return $files;
}

function count_lines($fullpath)
{
    $cnt = 0;
    $fp = @fopen($fullpath, 'rb');
    if (!$fp) return 0;
    while (!feof($fp)) {
        fgets($fp);
        $cnt++;
    }
    fclose($fp);
    return $cnt;
}

$root = repo_root();
$files = git_list_files($root);
if ($files === null) {
    $files = fallback_walk($root);
}

$filtered = [];
foreach ($files as $f) {
    $full = $root . DIRECTORY_SEPARATOR . $f;
    if (!is_file($full)) continue;
    $parts = explode(DIRECTORY_SEPARATOR, $f);
    if (isset($parts[0]) && strtolower($parts[0]) === 'backup') continue;
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if ($ext === 'jpg' || $ext === 'jpeg') continue;
    $filtered[] = $f;
}

$module_stats = [];
$total_files = 0;
$total_lines = 0;

foreach ($filtered as $rel) {
    $full = $root . DIRECTORY_SEPARATOR . $rel;
    $lines = count_lines($full);
    $total_files++;
    $total_lines += $lines;
    $parts = explode(DIRECTORY_SEPARATOR, $rel);
    $module = count($parts) > 1 ? $parts[0] : ($parts[0] !== '' ? $parts[0] : 'root');
    if (!isset($module_stats[$module])) $module_stats[$module] = ['lines' => 0, 'files' => 0, 'list' => []];
    $module_stats[$module]['lines'] += $lines;
    $module_stats[$module]['files'] += 1;
    $module_stats[$module]['list'][] = [$rel, $lines];
}

// sort modules by lines desc
uasort($module_stats, function($a, $b) { return $b['lines'] - $a['lines']; });

echo "Relatório de linhas por arquivo e por módulo\n";
echo "Raiz do repositório: $root\n";
echo "Arquivos considerados (ignora .gitignore quando há Git): " . count($filtered) . "\n";
echo "Total de arquivos: $total_files  Total de linhas: $total_lines\n\n";

echo "Ranking de módulos por linhas:\n";
$i = 1;
foreach ($module_stats as $mod => $s) {
    printf("%2d. %-30s — %8d linhas  / %5d arquivos\n", $i, $mod, $s['lines'], $s['files']);
    $i++;
}

echo "\nTop 10 arquivos por linhas (global):\n";
$all = [];
foreach ($module_stats as $s) {
    foreach ($s['list'] as $t) $all[] = $t;
}
usort($all, function($a, $b) { return $b[1] - $a[1]; });
for ($k = 0; $k < min(10, count($all)); $k++) {
    printf("%8d  %s\n", $all[$k][1], $all[$k][0]);
}

<?php
$root = realpath(__DIR__ . '/..');
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

$sessionFiles = [];
$changed = [];
$skipped = [];

function file_depth(string $rel): int {
    $dir = dirname($rel);
    if ($dir === '.' || $dir === '') {
        return 0;
    }
    return substr_count(str_replace('\\', '/', $dir), '/') + 1;
}

function bootstrap_line_for_depth(int $depth): string {
    if ($depth === 0) {
        return "require_once __DIR__ . '/config/session_bootstrap.php';";
    }
    if ($depth === 1) {
        return "require_once __DIR__ . '/../config/session_bootstrap.php';";
    }
    return "require_once dirname(__DIR__, {$depth}) . '/config/session_bootstrap.php';";
}

function has_real_session_start(string $code): bool {
    $tokens = token_get_all($code);
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            continue;
        }

        if ($token[0] === T_STRING && strtolower($token[1]) === 'session_start') {
            $j = $i + 1;
            while ($j < $count) {
                $next = $tokens[$j];
                if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $j++;
                    continue;
                }
                break;
            }

            if ($j < $count && $tokens[$j] === '(') {
                return true;
            }
        }
    }

    return false;
}

function find_insert_offset(string $code): int {
    if (!preg_match('/\A<\?php\s*/', $code, $open)) {
        return 0;
    }

    $offset = strlen($open[0]);
    $remaining = substr($code, $offset);

    if (preg_match('/\A(?:\/\/[^\n]*\n|\/\*.*?\*\/\s*|#.*\n|\s+)*/s', $remaining, $m)) {
        $offset += strlen($m[0]);
        $remaining = substr($code, $offset);
    }

    if (preg_match('/\Adeclare\s*\(.*?\)\s*;\s*/s', $remaining, $declare)) {
        $offset += strlen($declare[0]);
        $remaining = substr($code, $offset);
    }

    if (preg_match('/\A(?:\/\/[^\n]*\n|\/\*.*?\*\/\s*|#.*\n|\s+)*/s', $remaining, $m2)) {
        $offset += strlen($m2[0]);
        $remaining = substr($code, $offset);
    }

    if (preg_match('/\Anamespace\s+[^;{]+(?:;|\{)\s*/s', $remaining, $namespace)) {
        $offset += strlen($namespace[0]);
    }

    return $offset;
}

function rel_asset_prefix(int $depth): string {
    return str_repeat('../', $depth);
}

foreach ($it as $file) {
    if (!$file->isFile()) {
        continue;
    }

    if (strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $fullPath = $file->getPathname();
    $rel = str_replace('\\', '/', substr($fullPath, strlen($root) + 1));

    $code = file_get_contents($fullPath);
    if ($code === false) {
        continue;
    }

    if (!has_real_session_start($code)) {
        continue;
    }

    $sessionFiles[] = $rel;

    if ($rel === 'config/session_bootstrap.php') {
        $skipped[] = ['file' => $rel, 'reason' => 'self-bootstrap file'];
        continue;
    }

    $original = $code;
    $insertions = [];
    $depth = file_depth($rel);
    $hasBootstrap = (strpos($code, 'session_bootstrap.php') !== false);

    if (!$hasBootstrap) {
        $line = bootstrap_line_for_depth($depth);
        $offset = find_insert_offset($code);
        if ($offset > 0) {
            $prefix = substr($code, 0, $offset);
            $suffix = substr($code, $offset);
            $glue = (substr($prefix, -1) === "\n" || $prefix === '') ? '' : "\n";
            $code = $prefix . $glue . $line . "\n" . $suffix;
            $insertions[] = $line;
        }
    }

    if (strtolower(basename($rel)) === 'index.php') {
        $hasModalCss = (strpos($code, 'modalSessao.css') !== false);
        $hasModalJs = (strpos($code, 'controleSessao.js') !== false);
        $hasAssetUrl = (strpos($code, 'asset_url(') !== false);
        $prefixPath = rel_asset_prefix($depth);

        if (!$hasModalCss && stripos($code, '</head>') !== false) {
            $cssPath = $prefixPath . 'css/modalSessao.css';
            $cssLine = $hasAssetUrl
                ? "    <link rel=\"stylesheet\" href=\"<?php echo asset_url('{$cssPath}'); ?>\">"
                : "    <link rel=\"stylesheet\" href=\"{$cssPath}\">";
            $code = preg_replace('/<\/head>/i', $cssLine . "\n</head>", $code, 1);
            $insertions[] = $cssLine;
        }

        if (!$hasModalJs && stripos($code, '</body>') !== false) {
            $jsPath = $prefixPath . 'script/controleSessao.js';
            $jsLine = $hasAssetUrl
                ? "    <script src=\"<?php echo asset_url('{$jsPath}'); ?>\"></script>"
                : "    <script src=\"{$jsPath}\"></script>";
            $code = preg_replace('/<\/body>/i', $jsLine . "\n</body>", $code, 1);
            $insertions[] = $jsLine;
        }
    }

    if ($code !== $original) {
        file_put_contents($fullPath, $code);
        $changed[] = ['file' => $rel, 'insertions' => $insertions];
    }
}

sort($sessionFiles);
usort($changed, fn($a, $b) => strcmp($a['file'], $b['file']));

$out = [
    'sessionFiles' => $sessionFiles,
    'changed' => $changed,
    'skipped' => $skipped,
];

file_put_contents(__DIR__ . '/session_apply_report.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode([
    'sessionFilesCount' => count($sessionFiles),
    'changedCount' => count($changed),
    'skippedCount' => count($skipped),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

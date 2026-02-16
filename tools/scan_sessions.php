<?php
$root = realpath(__DIR__ . '/..');
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$result = [];

foreach ($it as $file) {
    if (!$file->isFile()) {
        continue;
    }
    if (strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $code = file_get_contents($path);
    if ($code === false) {
        continue;
    }

    $tokens = token_get_all($code);
    $hasSessionStart = false;
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            continue;
        }
        if ($token[0] !== T_STRING || strtolower($token[1]) !== 'session_start') {
            continue;
        }

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
            $hasSessionStart = true;
            break;
        }
    }

    if (!$hasSessionStart) {
        continue;
    }

    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $result[] = [
        'file' => $rel,
        'hasBootstrap' => (strpos($code, 'session_bootstrap.php') !== false),
        'isIndex' => (strtolower(basename($path)) === 'index.php'),
        'hasModalCss' => (strpos($code, 'modalSessao.css') !== false),
        'hasModalJs' => (strpos($code, 'controleSessao.js') !== false),
        'hasAssetUrl' => (strpos($code, 'asset_url(') !== false),
    ];
}

usort($result, fn($a, $b) => strcmp($a['file'], $b['file']));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

<?php
/**
 * Simula exatamente o que visualizar_pdf_log.php faz para buscar o PDF via SFTP,
 * usando as mesmas funções de build_candidate_paths + loop SFTP (após o fix).
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/secure_env.php';

function normalize_path_slashes($path) {
    $p = str_replace('\\', '/', (string)$path);
    return preg_replace('#/+#', '/', $p);
}

function build_candidate_paths($rawPath) {
    $raw = (string)$rawPath;
    $norm = normalize_path_slashes($raw);
    $candidates = [];
    if ($raw !== '') $candidates[] = $raw;
    if ($norm !== '' && $norm !== $raw) $candidates[] = $norm;
    if (preg_match('#^[A-Za-z]:/#', $norm)) {
        $rest = substr($norm, 3);
        $candidates[] = '/mnt/clientes/' . ltrim($rest, '/');
    }
    $out = [];
    foreach ($candidates as $c) {
        $c = trim((string)$c);
        if ($c === '' || in_array($c, $out, true)) continue;
        $out[] = $c;
    }
    return $out;
}

$caminho = 'Z:\\2026\\CIB_OCE\\02.Projetos\\1.CIB_OCE-Hall-PDF-FIL-R00.pdf';
$candidates = build_candidate_paths($caminho);
echo "Candidates:\n";
foreach ($candidates as $i => $c) echo "  [$i] $c\n";

$cfg = improov_sftp_config();
$sftp = new \phpseclib3\Net\SFTP($cfg['host'], $cfg['port']);
if (!$sftp->login($cfg['user'], $cfg['pass'])) die("\nSFTP login failed\n");
echo "\nSFTP login OK. CWD=" . $sftp->pwd() . "\n\n";

echo "Tentando candidatos (pulando Windows paths):\n";
$found = false;
foreach ($candidates as $p) {
    $p = normalize_path_slashes($p);
    if (preg_match('#^[A-Za-z]:#', $p)) {
        echo "  SKIP (windows): $p\n";
        continue;
    }
    $data = $sftp->get($p, false, 0, 128);
    if ($data !== false) {
        echo "  OK: $p (primeiros bytes: " . substr($data, 0, 8) . ")\n";
        $found = true;
        break;
    } else {
        echo "  FAIL: $p\n";
    }
}
echo $found ? "\nRESULT: PDF encontrado!\n" : "\nRESULT: PDF NÃO encontrado.\n";

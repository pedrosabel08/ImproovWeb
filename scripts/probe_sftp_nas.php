<?php
/**
 * probe_sftp_nas.php — Descobre a estrutura de caminhos no NAS via SFTP.
 * Uso: php scripts/probe_sftp_nas.php
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/secure_env.php';

$cfg = improov_sftp_config();
echo "Conectando em {$cfg['host']}:{$cfg['port']} user={$cfg['user']}\n";

$sftp = new \phpseclib3\Net\SFTP($cfg['host'], $cfg['port']);
if (!$sftp->login($cfg['user'], $cfg['pass'])) {
    die("SFTP login falhou.\n");
}

echo "CWD inicial: " . $sftp->pwd() . "\n\n";

echo "=== Listagem raiz '/' ===\n";
$root = $sftp->nlist('/');
if ($root !== false) {
    foreach ($root as $item) echo "  " . $item . "\n";
} else {
    echo "  (falhou)\n";
}

echo "\n=== Listagem de /mnt ===\n";
$mnt = $sftp->nlist('/mnt');
if ($mnt !== false) {
    foreach ($mnt as $item) {
        $full = '/mnt/' . $item;
        echo "  " . $item . ($sftp->is_dir($full) ? "/ " : " ") . "\n";
    }
}

echo "\n=== Listagem CWD ('.') ===\n";
$cwd_list = $sftp->nlist('.');
if ($cwd_list !== false) {
    foreach ($cwd_list as $item) {
        echo "  " . $item . ($sftp->is_dir($item) ? "/" : "") . "\n";
    }
}

// Tenta o arquivo específico com vários prefixos
$relativo = '2026/CIB_OCE/02.Projetos/1.CIB_OCE-Hall-PDF-FIL-R00.pdf';
echo "\n=== Buscando arquivo a partir do CWD: $relativo ===\n";
$prefixos = ['', 'clientes/', 'volume1/clientes/', 'volume1/'];
foreach ($prefixos as $pfx) {
    $full = $pfx . $relativo;
    $stat = $sftp->stat($full);
    echo ($stat !== false ? "[FOUND] " : "[ --- ] ") . $full . "\n";
}

// Lista subpastas de /mnt/clientes se existir
foreach (['/mnt/clientes', '/mnt/clientes/2026'] as $p) {
    if ($sftp->is_dir($p)) {
        echo "\n=== Listagem $p ===\n";
        foreach ($sftp->nlist($p) as $f) echo "  $f\n";
    }
}

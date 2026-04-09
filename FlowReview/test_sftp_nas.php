<?php
/**
 * Script de diagnóstico SFTP — rode apenas no VPS, remova depois.
 * URL: /FlowReview/test_sftp_nas.php
 */
// Carrega os dois .env na mesma ordem que revisarTarefa.php faz
require_once __DIR__ . '/../config/secure_env.php';
require_once __DIR__ . '/vendor/autoload.php';

use phpseclib3\Net\SFTP;
use Dotenv\Dotenv;

// 1º: FlowReview/.env (mesma prioridade que revisarTarefa.php)
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: text/plain; charset=utf-8');

try {
    $cfg = improov_sftp_config(); // usa IMPROOV_SFTP_*
} catch (RuntimeException $e) {
    die("ERRO config: " . $e->getMessage());
}

echo "Host : {$cfg['host']}\n";
echo "Port : {$cfg['port']}\n";
echo "User : {$cfg['user']}\n";
// Mostra a senha real para diagnosticar (apague o arquivo após o teste!)
echo "Pass : [{$cfg['pass']}]\n\n";

echo "Conectando via phpseclib...\n";
try {
    $sftp = new SFTP($cfg['host'], $cfg['port'], 10);
    if (!$sftp->login($cfg['user'], $cfg['pass'])) {
        echo "FALHOU: login rejeitado (usuário/senha incorretos).\n";
        exit;
    }
    echo "Login OK!\n";
    $list = $sftp->nlist('/mnt/clientes');
    echo "Conteúdo de /mnt/clientes:\n";
    print_r($list);
} catch (Throwable $e) {
    echo "EXCECAO: " . $e->getMessage() . "\n";
}

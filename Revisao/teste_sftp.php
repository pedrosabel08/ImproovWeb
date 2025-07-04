<?php
require_once __DIR__ . '/vendor/autoload.php';

use phpseclib3\Net\SFTP;

$sftp = new SFTP('example.com'); // só para testar instância
echo "SFTP instanciado com sucesso!";

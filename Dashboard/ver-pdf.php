<?php
require __DIR__ . '/../vendor/autoload.php'; // ajuste o caminho se necessário

use phpseclib3\Net\SFTP;

// Configurações SFTP
$host = 'imp-nas.ddns.net';
$usuario = 'flow';
$senha = 'flow@2025';
$porta = 2222;

// Recebe nomenclatura e nome do arquivo por GET
$nomenclatura = $_GET['nomenclatura'] ?? '';
$arquivo = basename($_GET['arquivo'] ?? '');

if (!$nomenclatura || !$arquivo) {
    http_response_code(400);
    exit('Nomenclatura ou nome de arquivo não especificado.');
}

// Pastas base para busca (ordem preferida)
$clientes_base = ['/mnt/clientes/2024', '/mnt/clientes/2025', '/mnt/clientes/2026'];
$pasta_funcao = '02.Projetos'; // Se quiser parametrizar, ajuste aqui ou receba por GET

// Conecta ao SFTP
$sftp = new SFTP($host, $porta);
if (!$sftp->login($usuario, $senha)) {
    http_response_code(403);
    exit('Falha na autenticação SFTP.');
}

// Procura o arquivo nas bases
$conteudoPdf = false;
foreach ($clientes_base as $base) {
    $caminhoCompleto = "$base/$nomenclatura/$pasta_funcao/$arquivo";
    if ($sftp->is_file($caminhoCompleto)) {
        $conteudoPdf = $sftp->get($caminhoCompleto);
        if ($conteudoPdf !== false) {
            break;
        }
    }
}

if ($conteudoPdf === false) {
    http_response_code(404);
    exit('Arquivo não encontrado no SFTP.');
}

// Envia o PDF para o navegador
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $arquivo . '"');
echo $conteudoPdf;

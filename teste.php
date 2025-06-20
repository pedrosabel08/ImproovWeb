<?php
require 'vendor/autoload.php';

use phpseclib\Net\SSH2;
use phpseclib\Net\SCP;
use phpseclib\Exception\UnableToConnectException;

function enviarArquivoSCP($host, $usuario, $senha, $arquivoLocal, $arquivoRemoto)
{
    $porta = 2222;

    if (!file_exists($arquivoLocal)) {
        return "❌ Arquivo local não encontrado: $arquivoLocal";
    }

    try {
        $ssh = new SSH2($host, $porta);
        if (!$ssh->login($usuario, $senha)) {
            return "❌ Falha na autenticação SSH.";
        }

        // Garante que o diretório remoto exista
        $diretorio = dirname($arquivoRemoto);
        $ssh->exec("mkdir -p " . escapeshellarg($diretorio));

        // Envia o arquivo via SCP
        $scp = new SCP($ssh);
        $scp->put($arquivoRemoto, $arquivoLocal, SCP::SOURCE_LOCAL_FILE);

        return "✅ Arquivo enviado com sucesso via SCP!";
    } catch (UnableToConnectException $e) {
        return "❌ Erro ao conectar ao servidor SSH: " . $e->getMessage();
    } catch (Exception $e) {
        return "⚠ Ocorreu um erro inesperado: " . $e->getMessage();
    }
}

// Exemplo de uso:
echo enviarArquivoSCP(
    "imp-nas.ddns.net",
    "flow",
    "flow@2025",
    "./assets/teste.pdf",
    "/mnt/clientes/02/abc/oi.pdf"
);

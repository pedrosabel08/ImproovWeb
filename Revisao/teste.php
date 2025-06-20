<?php
require '../vendor/autoload.php';

use phpseclib3\Net\SFTP;
use phpseclib3\Exception\UnableToConnectException;

function enviarArquivoSFTP($host, $usuario, $senha, $arquivoLocal, $arquivoRemoto)
{
    $porta = 2222; // Porta SFTP personalizada

    if (!file_exists($arquivoLocal)) {
        return "❌ Arquivo local não encontrado: $arquivoLocal";
    }

    try {
        $sftp = new SFTP($host, $porta);
        if (!$sftp->login($usuario, $senha)) {
            return "❌ Falha na autenticação SFTP.";
        }

        // Garante que o diretório remoto exista
        $diretorio = dirname($arquivoRemoto);
        if (!$sftp->is_dir($diretorio)) {
            $sftp->mkdir($diretorio, -1, true); // Recursivo
        }

        // Envia o arquivo
        if ($sftp->put($arquivoRemoto, file_get_contents($arquivoLocal))) {
            return "✅ Arquivo enviado com sucesso via SFTP!";
        } else {
            return "⚠ Erro ao enviar o arquivo via SFTP.";
        }
    } catch (UnableToConnectException $e) {
        return "❌ Erro ao conectar ao servidor SFTP: " . $e->getMessage();
    } catch (Exception $e) {
        return "⚠ Ocorreu um erro inesperado: " . $e->getMessage();
    }
}

// Exemplo de uso:
echo enviarArquivoSFTP(
    "imp-nas.ddns.net",
    "flow",
    "flow@2025",
    "../assets/teste.pdf",
    "/mnt/clientes/2025/ROM_MAE/02.Projetos/teste2.pdf"
);

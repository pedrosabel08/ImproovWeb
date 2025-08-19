<?php

header("Access-Control-Allow-Origin: https://improov.com.br");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require __DIR__ . '/vendor/autoload.php';

use phpseclib3\Net\SFTP;
use phpseclib3\Exception\UnableToConnectException;

header('Content-Type: application/json');

// Dados SFTP
$ftp_user = "flow";
$ftp_pass = "flow@2025";
$ftp_host = "imp-nas.ddns.net";
$ftp_port = 2222;

// Recebe dados do POST
$nomenclatura = $_POST['nomenclatura'] ?? '';
$nome_pasta = $_POST['nome_pasta'] ?? '';
$arquivo = $_FILES['arquivo_acomp'] ?? null;

if (empty($nomenclatura) || empty($nome_pasta) || !$arquivo || empty($arquivo['tmp_name'])) {
    echo json_encode(['error' => 'Dados insuficientes ou arquivo não enviado']);
    exit;
}

// Sanitiza nome da pasta
function removerTodosAcentos($str)
{
    return preg_replace(
        ['/[áàãâä]/ui', '/[éèêë]/ui', '/[íìîï]/ui', '/[óòõôö]/ui', '/[úùûü]/ui', '/[ç]/ui'],
        ['a', 'e', 'i', 'o', 'u', 'c'],
        $str
    );
}
function sanitizeFilename($str)
{
    $str = removerTodosAcentos($str);
    $str = preg_replace('/[\/\\\:*?"<>|]/', '', $str);
    $str = preg_replace('/\s+/', '_', $str);
    return $str;
}

$nome_pasta = sanitizeFilename($nome_pasta);

try {
    $sftp = new SFTP($ftp_host, $ftp_port);
    if (!$sftp->login($ftp_user, $ftp_pass)) {
        echo json_encode(['error' => 'Falha na autenticação SFTP']);
        exit;
    }

    $bases = [
        "/mnt/clientes/2024/$nomenclatura",
        "/mnt/clientes/2025/$nomenclatura"
    ];

    $base_encontrada = '';
    foreach ($bases as $base) {
        if ($sftp->is_dir($base)) {
            $base_encontrada = $base;
            break;
        }
    }

    if (!$base_encontrada) {
        echo json_encode(['error' => "Nomenclatura '$nomenclatura' não encontrada em 2024 nem 2025"]);
        exit;
    }

    $destino_base = "$base_encontrada/05.Exchange/01.Input/$nome_pasta";

    // Cria apenas a pasta do acompanhamento, não a nomenclatura
    if (!$sftp->is_dir($destino_base)) {
        $sftp->mkdir($destino_base, -1, true);
        $sftp->chmod(0777, $destino_base);
    }

    $nome_arquivo = sanitizeFilename($arquivo['name']);
    $remote_path = "$destino_base/$nome_arquivo";

    if ($sftp->put($remote_path, $arquivo['tmp_name'], SFTP::SOURCE_LOCAL_FILE)) {
        echo json_encode(['success' => true, 'destino' => $remote_path]);
    } else {
        echo json_encode(['error' => 'Erro ao enviar arquivo via SFTP']);
    }
} catch (UnableToConnectException $e) {
    echo json_encode(['error' => 'Erro ao conectar ao servidor SFTP: ' . $e->getMessage()]);
} catch (\Exception $e) {
    echo json_encode(['error' => 'Erro inesperado: ' . $e->getMessage()]);
}

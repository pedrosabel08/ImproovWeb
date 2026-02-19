<?php
header("Access-Control-Allow-Origin: https://improov.com.br");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/secure_env.php';
include '../conexao.php';

use phpseclib3\Net\SFTP;
use phpseclib3\Exception\UnableToConnectException;

header('Content-Type: application/json');

try {
    $sftpCfg = improov_sftp_config();
} catch (RuntimeException $e) {
    echo json_encode(['error' => 'Configuração SFTP ausente no ambiente']);
    exit;
}

$ftp_user = $sftpCfg['user'];
$ftp_pass = $sftpCfg['pass'];
$ftp_host = $sftpCfg['host'];
$ftp_port = $sftpCfg['port'];

// Recebe dados do front
$obra_id = $_POST['obra_id'] ?? null;
$arquivos = $_FILES['arquivos'] ?? null;

if (!$obra_id || !$arquivos || empty($arquivos['tmp_name'][0])) {
    echo json_encode(['error' => 'Obra ou arquivos não enviados']);
    exit;
}

// Função para sanitizar nomes de arquivo
function sanitizeFilename($str)
{
    $str = preg_replace('/[áàãâä]/ui', 'a', $str);
    $str = preg_replace('/[éèêë]/ui', 'e', $str);
    $str = preg_replace('/[íìîï]/ui', 'i', $str);
    $str = preg_replace('/[óòõôö]/ui', 'o', $str);
    $str = preg_replace('/[úùûü]/ui', 'u', $str);
    $str = preg_replace('/[ç]/ui', 'c', $str);
    $str = preg_replace('/[\/\\\:*?"<>|]/', '', $str);
    $str = preg_replace('/\s+/', '_', $str);
    return $str;
}

try {
    $sftp = new SFTP($ftp_host, $ftp_port);
    if (!$sftp->login($ftp_user, $ftp_pass)) {
        echo json_encode(['error' => 'Falha na autenticação SFTP']);
        exit;
    }

    // Consulta a nomenclatura da obra no banco
    $stmt = $conn->prepare("SELECT nomenclatura FROM obra WHERE idobra = ?");
    $stmt->bind_param("i", $obra_id);
    $stmt->execute();
    $stmt->bind_result($nomenclatura);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => "Obra ID $obra_id não encontrada"]);
        exit;
    }
    $stmt->close();

    $nomenclatura = sanitizeFilename($nomenclatura);

    // Busca a pasta da obra em 2024 ou 2025
    $base2024 = "/mnt/clientes/2024/$nomenclatura/05.Exchange/01.Input";
    $base2025 = "/mnt/clientes/2025/$nomenclatura/05.Exchange/01.Input";

    if ($sftp->is_dir($base2024)) {
        $destino = $base2024;
    } elseif ($sftp->is_dir($base2025)) {
        $destino = $base2025;
    } else {
        echo json_encode(['error' => "Nomenclatura '$nomenclatura' não encontrada em 2024 nem 2025"]);
        exit;
    }

    $pendentesDir = "$destino/Arquivos_Pendentes";

    // Cria a pasta se não existir
    if (!$sftp->is_dir($pendentesDir)) {
        if (!$sftp->mkdir($pendentesDir, -1, true)) { // -1 = padrão de permissão, true = recursivo
            echo json_encode(['error' => "Não foi possível criar a pasta 'Arquivos_Pendentes'"]);
            exit;
        }
    }


    // Salva cada arquivo na pasta de destino
    $enviados = [];
    for ($i = 0; $i < count($arquivos['name']); $i++) {
        $nome_original = $arquivos['name'][$i];
        $tmp_name = $arquivos['tmp_name'][$i];
        $tipo = $arquivos['type'][$i];

        $nome_sanitizado = sanitizeFilename($nome_original);
        $remote_path = "$pendentesDir/$nome_sanitizado"; // envia para a nova pasta

        if ($sftp->put($remote_path, $tmp_name, SFTP::SOURCE_LOCAL_FILE)) {
            $enviados[] = $remote_path;

            // Insere registro no banco
            $stmtIns = $conn->prepare("INSERT INTO arquivos 
                (obra_id, nome_original, caminho, tipo, status, origem, recebido_por, recebido_em, revisado_em, revisado_por) 
                VALUES (?, ?, ?, ?, 'pendente', 'WhatsApp', 1, NOW(), NULL, NULL)");
            $stmtIns->bind_param("isss", $obra_id, $nome_sanitizado, $remote_path, $tipo);
            $stmtIns->execute();
            $stmtIns->close();
        }
    }

    echo json_encode(['success' => true, 'arquivos_enviados' => $enviados]);
} catch (UnableToConnectException $e) {
    echo json_encode(['error' => 'Erro ao conectar ao servidor SFTP: ' . $e->getMessage()]);
} catch (\Exception $e) {
    echo json_encode(['error' => 'Erro inesperado: ' . $e->getMessage()]);
}

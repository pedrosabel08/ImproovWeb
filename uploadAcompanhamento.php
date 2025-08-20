<?php
header("Access-Control-Allow-Origin: https://improov.com.br");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require __DIR__ . '/vendor/autoload.php';
include 'conexao.php';

use phpseclib3\Net\SFTP;
use phpseclib3\Exception\UnableToConnectException;

header('Content-Type: application/json');

$ftp_user = "flow";
$ftp_pass = "flow@2025";
$ftp_host = "imp-nas.ddns.net";
$ftp_port = 2222;

$nomenclatura = $_POST['nomenclatura'] ?? 'TES_TES';
$tipo_imagem = $_POST['tipo_imagem'] ?? '';
$tipo_arquivo = $_POST['tipo_arquivo'] ?? '';
$descricao = $_POST['descricao'] ?? '';
$observacao = $_POST['observacao'] ?? '';
$data_recebimento = $_POST['data_recebimento'] ?? '';
$responsavel = $_POST['responsavel'] ?? '';
$arquivo = $_FILES['arquivo_acomp'] ?? null;
$replace = isset($_POST['replace']) ? true : false;

if (empty($nomenclatura) || empty($tipo_imagem) || empty($tipo_arquivo) || empty($descricao) || !$arquivo || empty($arquivo['tmp_name'])) {
    echo json_encode(['error' => 'Dados insuficientes ou arquivo não enviado']);
    exit;
}

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

$descricao = sanitizeFilename($descricao);
$tipo_arquivo = sanitizeFilename($tipo_arquivo);

try {
    $sftp = new SFTP($ftp_host, $ftp_port);
    if (!$sftp->login($ftp_user, $ftp_pass)) {
        echo json_encode(['error' => 'Falha na autenticação SFTP']);
        exit;
    }

    // Busca ano e monta caminho base
    $base = "/mnt/clientes/2024/$nomenclatura/05.Exchange/01.Input";
    $ano = "2024";
    if (!$sftp->is_dir($base)) {
        $base = "/mnt/clientes/2025/$nomenclatura/05.Exchange/01.Input";
        $ano = "2025";
        if (!$sftp->is_dir($base)) {
            echo json_encode(['error' => "Nomenclatura '$nomenclatura' não encontrada em 2024 nem 2025"]);
            exit;
        }
    }

    // Caminhos do tipo de arquivo, atual e histórico
    $pastaTipoArquivo = "$base/$tipo_arquivo";
    $pastaAtual = "$pastaTipoArquivo/Atual/$descricao";
    $pastaHistorico = "$pastaTipoArquivo/Historico/$descricao";

    // Cria pasta do tipo de arquivo se não existir
    if (!$sftp->is_dir($pastaTipoArquivo)) {
        $sftp->mkdir($pastaTipoArquivo, -1, true);
        $sftp->chmod(0777, $pastaTipoArquivo);
    }

    // Verifica se já existe pasta Atual
    if ($sftp->is_dir($pastaAtual)) {
        if (!$replace) {
            echo json_encode([
                'confirm_replace' => true,
                'message' => "Já existe $pastaAtual. Deseja substituir e mover a versão anterior para o histórico?"
            ]);
            exit;
        } else {
            // Move arquivos existentes para histórico, incrementando versão
            if (!$sftp->is_dir($pastaHistorico)) {
                $sftp->mkdir($pastaHistorico, -1, true);
                $sftp->chmod(0777, $pastaHistorico);
            }
            $arquivosAtuais = $sftp->nlist($pastaAtual);
            $versao = 1;
            foreach ($arquivosAtuais as $file) {
                if ($file == '.' || $file == '..') continue;
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                $novoNome = $descricao . "_v" . $versao . "." . $ext;
                $sftp->rename("$pastaAtual/$file", "$pastaHistorico/$novoNome");
                $versao++;
            }
        }
    } else {
        // Cria pasta Atual se não existir
        $sftp->mkdir($pastaAtual, -1, true);
        $sftp->chmod(0777, $pastaAtual);
    }

    $nome_arquivo = sanitizeFilename($arquivo['name']);
    $remote_path = "$pastaAtual/$nome_arquivo";

    if ($sftp->put($remote_path, $arquivo['tmp_name'], SFTP::SOURCE_LOCAL_FILE)) {

        // Calcula versão (se substituição, pega maior versão do histórico e soma 1)
        $versao = 1;
        if ($replace) {
            $sqlVer = "SELECT MAX(versao) AS max_v FROM recebimento_arquivos WHERE descricao = ? AND obra_id = ? AND colaborador_id = ?";
            $stmtVer = $conn->prepare($sqlVer);
            $stmtVer->bind_param("sii", $descricao, $obra_id, $colaborador_id);
            $obra_id = 74; // ajuste conforme seu fluxo
            $colaborador_id = 1; // ajuste conforme seu fluxo
            $stmtVer->execute();
            $stmtVer->bind_result($max_v);
            if ($stmtVer->fetch() && $max_v) $versao = $max_v + 1;
            $stmtVer->close();
        }

        $stmt = $conn->prepare("INSERT INTO recebimento_arquivos 
        (obra_id, colaborador_id, tipo_arquivo, descricao, nome_arquivo, caminho, data_upload, versao, status) 
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'atual')");
        $obra_id = 74; // ajuste conforme seu fluxo
        $colaborador_id = 1; // ajuste conforme seu fluxo
        $stmt->bind_param(
            "iissssi",
            $obra_id,
            $colaborador_id,
            $tipo_arquivo,
            $descricao,
            $nome_arquivo,
            $remote_path,
            $versao
        );
        $stmt->execute();
        $stmt->close();
        $conn->close();

        echo json_encode(['success' => true, 'destino' => $remote_path]);
    } else {
        echo json_encode(['error' => 'Erro ao enviar arquivo via SFTP']);
    }
} catch (UnableToConnectException $e) {
    echo json_encode(['error' => 'Erro ao conectar ao servidor SFTP: ' . $e->getMessage()]);
} catch (\Exception $e) {
    echo json_encode(['error' => 'Erro inesperado: ' . $e->getMessage()]);
}